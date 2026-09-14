<?php

/**
 * Read-only live discovery adapter for X recent search and YouTube search.list.
 *
 * The class does not own an HTTP implementation. A caller must inject a
 * callable, making network access explicit and keeping CI fully offline.
 */
final class Bitmomo_Live_Ingestion_V1 {
    const BATCH_VERSION = 'live-ingestion-batch-v1';
    const INGESTION_VERSION = 'live-ingestion-v1';
    const X_ENDPOINT = 'https://api.x.com/2/tweets/search/recent';
    const YOUTUBE_ENDPOINT = 'https://www.googleapis.com/youtube/v3/search';
    const X_POST_READ_USD = 0.005;

    private static $x_queries = [
        'decision' => '(BTC OR Bitcoin) (signal OR signals OR sinyal OR "long or short" OR "long atau short") -is:retweet',
        'analysis' => '(BTC OR Bitcoin) ("analysis today" OR "analisa hari ini" OR "analisis hari ini" OR "next move" OR "arah btc") -is:retweet',
        'structure' => '(BTC OR Bitcoin) (support OR resistance OR breakout OR "why btc" OR "why bitcoin" OR "kenapa btc" OR "kenapa bitcoin") -is:retweet',
    ];

    private static $youtube_queries = [
        'btc long or short',
        'bitcoin analysis today',
        'sinyal bitcoin hari ini',
        'ai bitcoin research',
    ];

    public static function discover(array $credentials, callable $http_get, array $state = [], array $config = [], $now = null) {
        $now = self::now($now);
        $config = self::normalize_config($config);

        $observations = [];
        $diagnostics = [];
        $usage = [
            'x_requests' => 0,
            'x_posts_returned' => 0,
            'x_estimated_read_cost_usd' => 0.0,
            'youtube_search_calls' => 0,
            'youtube_results_returned' => 0,
        ];
        $state_hints = [
            'x_since_id' => self::nullable_text($state['x_since_id'] ?? null),
            'youtube_published_after' => self::nullable_text($state['youtube_published_after'] ?? null),
        ];

        self::discover_x(
            $credentials,
            $http_get,
            $state,
            $config,
            $observations,
            $diagnostics,
            $usage,
            $state_hints
        );

        self::discover_youtube(
            $credentials,
            $http_get,
            $state,
            $config,
            $now,
            $observations,
            $diagnostics,
            $usage,
            $state_hints
        );

        $deduped = self::deduplicate_observations($observations);
        $usage['x_estimated_read_cost_usd'] = round($usage['x_posts_returned'] * self::X_POST_READ_USD, 6);

        return [
            'batch_version' => self::BATCH_VERSION,
            'ingestion_version' => self::INGESTION_VERSION,
            'mode' => 'read_only',
            'generated_at' => gmdate('c', $now),
            'safety' => [
                'write_endpoints_present' => false,
                'browser_scraping_permitted' => false,
                'credentials_persisted' => false,
                'credentials_exposed_in_output' => false,
                'auto_send_permitted' => false,
                'auto_publish_permitted' => false,
            ],
            'budget' => [
                'x_max_clusters_per_run' => $config['x_max_clusters'],
                'x_max_results_per_cluster' => $config['x_max_results'],
                'x_max_posts_requested_per_run' => $config['x_max_clusters'] * $config['x_max_results'],
                'x_theoretical_max_read_cost_usd' => round($config['x_max_clusters'] * $config['x_max_results'] * self::X_POST_READ_USD, 6),
                'youtube_max_search_calls_per_run' => $config['youtube_max_calls'],
                'youtube_max_results_per_call' => $config['youtube_max_results'],
            ],
            'usage' => $usage,
            'counts' => [
                'observations_before_dedupe' => count($observations),
                'observations' => count($deduped['observations']),
                'duplicates' => count($deduped['duplicates']),
                'source_errors' => count(array_filter($diagnostics, function ($row) {
                    return is_array($row) && ($row['status'] ?? '') === 'error';
                })),
            ],
            'observations' => $deduped['observations'],
            'duplicates' => $deduped['duplicates'],
            'diagnostics' => $diagnostics,
            'state_hints' => $state_hints,
        ];
    }

    public static function run_acquisition(
        array $credentials,
        callable $http_get,
        array $research_items,
        array $state = [],
        array $config = [],
        array $cooldown_state = [],
        array $interaction_context = [],
        array $formats = [],
        $now = null
    ) {
        $now = self::now($now);
        $batch = self::discover($credentials, $http_get, $state, $config, $now);
        $run = Bitmomo_Acquisition_Orchestrator_V1::run(
            $research_items,
            $batch['observations'],
            $cooldown_state,
            $interaction_context,
            $formats,
            $now
        );

        return [
            'ingestion' => $batch,
            'acquisition_run' => $run,
        ];
    }

    private static function discover_x(
        array $credentials,
        callable $http_get,
        array $state,
        array $config,
        array &$observations,
        array &$diagnostics,
        array &$usage,
        array &$state_hints
    ) {
        $token = self::secret($credentials['x_bearer_token'] ?? null);
        if ($token === null) {
            $diagnostics[] = ['source' => 'x', 'status' => 'error', 'code' => 'credentials_missing'];
            return;
        }

        $queries = array_slice(self::$x_queries, 0, $config['x_max_clusters'], true);
        $highest_id = self::nullable_text($state['x_since_id'] ?? null);

        foreach ($queries as $cluster => $query) {
            $params = [
                'query' => $query,
                'max_results' => $config['x_max_results'],
                'tweet.fields' => 'created_at,author_id,lang',
            ];
            if (!empty($state['x_since_id'])) $params['since_id'] = (string) $state['x_since_id'];

            $request = [
                'method' => 'GET',
                'url' => self::X_ENDPOINT,
                'query' => $params,
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ];

            $usage['x_requests']++;
            try {
                $response = call_user_func($http_get, $request);
            } catch (Throwable $e) {
                $diagnostics[] = ['source' => 'x', 'cluster' => $cluster, 'status' => 'error', 'code' => 'transport_error'];
                continue;
            }

            if (!self::response_ok($response)) {
                $diagnostics[] = [
                    'source' => 'x',
                    'cluster' => $cluster,
                    'status' => 'error',
                    'code' => 'http_error',
                    'http_status' => self::status($response),
                ];
                continue;
            }

            $json = self::json_body($response);
            $rows = is_array($json['data'] ?? null) ? $json['data'] : [];
            $rows = array_slice($rows, 0, $config['x_max_results']);
            $usage['x_posts_returned'] += count($rows);

            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $id = self::text($row['id'] ?? '');
                $text = self::text($row['text'] ?? '');
                $created = self::text($row['created_at'] ?? '');
                if ($id === '' || $text === '' || $created === '') continue;
                $author = self::text($row['author_id'] ?? '');

                $observations[] = [
                    'source' => 'x',
                    'payload' => [
                        'id' => $id,
                        'url' => 'https://x.com/i/web/status/' . rawurlencode($id),
                        'surface' => 'post',
                        'text' => $text,
                        'author_ref' => $author === '' ? '' : self::hashed_ref('xanon', $author),
                        'created_at' => $created,
                    ],
                    'ingestion' => ['cluster' => $cluster],
                ];

                if ($highest_id === null || self::numeric_string_compare($id, $highest_id) > 0) $highest_id = $id;
            }

            $diagnostics[] = [
                'source' => 'x',
                'cluster' => $cluster,
                'status' => 'ok',
                'returned' => count($rows),
            ];
        }

        if ($highest_id !== null) $state_hints['x_since_id'] = $highest_id;
    }

    private static function discover_youtube(
        array $credentials,
        callable $http_get,
        array $state,
        array $config,
        $now,
        array &$observations,
        array &$diagnostics,
        array &$usage,
        array &$state_hints
    ) {
        $key = self::secret($credentials['youtube_api_key'] ?? null);
        if ($key === null) {
            $diagnostics[] = ['source' => 'youtube', 'status' => 'error', 'code' => 'credentials_missing'];
            return;
        }

        $queries = array_slice(self::$youtube_queries, 0, $config['youtube_max_calls']);
        $published_after = self::youtube_published_after($state, $config, $now);
        $latest_published = self::nullable_text($state['youtube_published_after'] ?? null);

        foreach ($queries as $index => $query) {
            $params = [
                'part' => 'snippet',
                'type' => 'video',
                'q' => $query,
                'maxResults' => $config['youtube_max_results'],
                'order' => 'date',
                'publishedAfter' => $published_after,
                'key' => $key,
            ];
            if ($config['youtube_region'] !== null) $params['regionCode'] = $config['youtube_region'];
            if ($config['youtube_language'] !== null) $params['relevanceLanguage'] = $config['youtube_language'];

            $request = [
                'method' => 'GET',
                'url' => self::YOUTUBE_ENDPOINT,
                'query' => $params,
                'headers' => [],
            ];

            $usage['youtube_search_calls']++;
            try {
                $response = call_user_func($http_get, $request);
            } catch (Throwable $e) {
                $diagnostics[] = ['source' => 'youtube', 'query_index' => $index, 'status' => 'error', 'code' => 'transport_error'];
                continue;
            }

            if (!self::response_ok($response)) {
                $diagnostics[] = [
                    'source' => 'youtube',
                    'query_index' => $index,
                    'status' => 'error',
                    'code' => 'http_error',
                    'http_status' => self::status($response),
                ];
                continue;
            }

            $json = self::json_body($response);
            $rows = is_array($json['items'] ?? null) ? $json['items'] : [];
            $rows = array_slice($rows, 0, $config['youtube_max_results']);
            $usage['youtube_results_returned'] += count($rows);

            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $video_id = self::text($row['id']['videoId'] ?? '');
                $snippet = is_array($row['snippet'] ?? null) ? $row['snippet'] : [];
                $title = self::text($snippet['title'] ?? '');
                $description = self::text($snippet['description'] ?? '');
                $published = self::text($snippet['publishedAt'] ?? '');
                if ($video_id === '' || $title === '' || $published === '') continue;
                $channel_id = self::text($snippet['channelId'] ?? '');

                $observations[] = [
                    'source' => 'youtube',
                    'payload' => [
                        'id' => $video_id,
                        'url' => 'https://www.youtube.com/watch?v=' . rawurlencode($video_id),
                        'surface' => 'search_result',
                        'title' => $title,
                        'description' => $description,
                        'author_ref' => $channel_id === '' ? '' : self::hashed_ref('ytanon', $channel_id),
                        'published_at' => $published,
                    ],
                    'ingestion' => ['query' => $query],
                ];

                if ($latest_published === null || strcmp($published, $latest_published) > 0) $latest_published = $published;
            }

            $diagnostics[] = [
                'source' => 'youtube',
                'query_index' => $index,
                'status' => 'ok',
                'returned' => count($rows),
            ];
        }

        if ($latest_published !== null) $state_hints['youtube_published_after'] = $latest_published;
    }

    private static function normalize_config(array $config) {
        $x_clusters = self::bounded_int($config['x_max_clusters'] ?? 3, 1, 3);
        $x_results = self::bounded_int($config['x_max_results'] ?? 10, 10, 10);
        $yt_calls = self::bounded_int($config['youtube_max_calls'] ?? 4, 1, 4);
        $yt_results = self::bounded_int($config['youtube_max_results'] ?? 10, 1, 10);
        $lookback = self::bounded_int($config['youtube_lookback_hours'] ?? 24, 1, 168);

        $region = self::nullable_text($config['youtube_region'] ?? null);
        if ($region !== null && !preg_match('/^[A-Z]{2}$/', strtoupper($region))) $region = null;
        if ($region !== null) $region = strtoupper($region);

        $language = self::nullable_text($config['youtube_language'] ?? null);
        if ($language !== null && !preg_match('/^[A-Za-z]{2,3}(-[A-Za-z]{2,8})?$/', $language)) $language = null;

        return [
            'x_max_clusters' => $x_clusters,
            'x_max_results' => $x_results,
            'youtube_max_calls' => $yt_calls,
            'youtube_max_results' => $yt_results,
            'youtube_lookback_hours' => $lookback,
            'youtube_region' => $region,
            'youtube_language' => $language,
        ];
    }

    private static function youtube_published_after(array $state, array $config, $now) {
        $fallback = gmdate('c', $now - ($config['youtube_lookback_hours'] * 3600));
        $candidate = self::nullable_text($state['youtube_published_after'] ?? null);
        if ($candidate === null) return $fallback;
        $ts = strtotime($candidate);
        if (!$ts || $ts > $now) return $fallback;
        $floor = $now - (168 * 3600);
        if ($ts < $floor) return gmdate('c', $floor);
        return gmdate('c', $ts);
    }

    private static function deduplicate_observations(array $observations) {
        $unique = [];
        $duplicates = [];
        $seen = [];
        foreach ($observations as $index => $row) {
            if (!is_array($row)) continue;
            $source = self::key($row['source'] ?? '');
            $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
            $id = self::text($payload['id'] ?? '');
            if ($source === '' || $id === '') continue;
            $key = $source . ':' . $id;
            if (isset($seen[$key])) {
                $duplicates[] = ['source' => $source, 'source_ref' => $id, 'kept_index' => $seen[$key], 'duplicate_index' => $index];
                continue;
            }
            $seen[$key] = $index;
            $unique[] = $row;
        }
        return ['observations' => $unique, 'duplicates' => $duplicates];
    }

    private static function response_ok($response) {
        return is_array($response) && self::status($response) >= 200 && self::status($response) < 300 && is_array($response['json'] ?? null);
    }

    private static function status($response) {
        return is_array($response) && is_numeric($response['status'] ?? null) ? (int) $response['status'] : 0;
    }

    private static function json_body($response) {
        return is_array($response['json'] ?? null) ? $response['json'] : [];
    }

    private static function hashed_ref($prefix, $value) {
        return $prefix . '_' . substr(hash('sha256', $value), 0, 24);
    }

    private static function numeric_string_compare($a, $b) {
        $a = ltrim((string) $a, '0');
        $b = ltrim((string) $b, '0');
        if ($a === '') $a = '0';
        if ($b === '') $b = '0';
        if (strlen($a) !== strlen($b)) return strlen($a) <=> strlen($b);
        return strcmp($a, $b);
    }

    private static function bounded_int($value, $min, $max) {
        $value = is_numeric($value) ? (int) $value : $min;
        return max($min, min($max, $value));
    }

    private static function secret($value) {
        if (!is_scalar($value)) return null;
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function nullable_text($value) {
        if ($value === null || $value === '') return null;
        return self::text($value);
    }

    private static function key($value) {
        $value = strtolower(self::text($value));
        return preg_replace('/[^a-z0-9_\-]/', '', $value);
    }

    private static function text($value) {
        if (!is_scalar($value)) return '';
        $value = strip_tags((string) $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private static function now($now) {
        if ($now === null) return time();
        return is_numeric($now) ? (int) $now : time();
    }
}
