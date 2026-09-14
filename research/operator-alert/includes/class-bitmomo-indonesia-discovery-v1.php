<?php

/**
 * Indonesia-first read-only discovery profile for operator opportunities.
 *
 * This is intentionally narrower than generic P6 ingestion. It uses one
 * Indonesian X recent-search query for the fast reply lane and a small
 * Indonesian YouTube search set for owned-content demand. No write endpoint
 * exists here.
 */
final class Bitmomo_Indonesia_Discovery_V1 {
    const PROFILE_VERSION = 'indonesia-discovery-v1';
    const X_ENDPOINT = 'https://api.x.com/2/tweets/search/recent';
    const YOUTUBE_ENDPOINT = 'https://www.googleapis.com/youtube/v3/search';
    const X_POST_READ_USD = 0.005;
    const X_LOOKBACK_SECONDS = 3600;
    const YOUTUBE_LOOKBACK_SECONDS = 21600;

    private static $x_query = '(BTC OR Bitcoin) (sinyal OR "long atau short" OR "naik atau turun" OR entry OR analisa OR analisis OR prediksi OR "kenapa btc" OR "kenapa bitcoin" OR support OR resistance OR breakout) lang:id -is:retweet -giveaway -airdrop -"join vip" -"vip signal"';

    private static $youtube_queries = [
        'analisis bitcoin hari ini',
        'sinyal btc hari ini',
    ];

    public static function discover(array $credentials, callable $http_get, array $state = [], $now = null) {
        $now = self::now($now);
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
            'youtube_published_after' => self::canonical_time($state['youtube_published_after'] ?? null),
        ];

        self::discover_x($credentials, $http_get, $state, $now, $observations, $diagnostics, $usage, $state_hints);
        self::discover_youtube($credentials, $http_get, $state, $now, $observations, $diagnostics, $usage, $state_hints);

        $deduped = self::deduplicate($observations);
        $usage['x_estimated_read_cost_usd'] = round($usage['x_posts_returned'] * self::X_POST_READ_USD, 6);

        return [
            'profile_version' => self::PROFILE_VERSION,
            'market_profile' => 'indonesia',
            'mode' => 'read_only',
            'generated_at' => gmdate('c', $now),
            'safety' => [
                'social_write_endpoints_present' => false,
                'auto_send_permitted' => false,
                'auto_publish_permitted' => false,
                'credentials_exposed_in_output' => false,
            ],
            'targeting' => [
                'x_language_operator' => 'lang:id',
                'youtube_region' => 'ID',
                'youtube_relevance_language' => 'id',
                'audience_percentage_claim_permitted' => false,
                'note' => 'Language/region signals improve targeting but do not reveal follower geography.',
            ],
            'budget' => [
                'x_requests_per_poll' => 1,
                'x_max_results_per_poll' => 10,
                'x_theoretical_max_read_cost_usd' => 0.05,
                'youtube_search_calls_per_poll' => 2,
                'youtube_max_results_per_call' => 10,
            ],
            'usage' => $usage,
            'observations' => $deduped['observations'],
            'duplicates' => $deduped['duplicates'],
            'diagnostics' => $diagnostics,
            'state_hints' => $state_hints,
        ];
    }

    private static function discover_x(array $credentials, callable $http_get, array $state, $now, array &$observations, array &$diagnostics, array &$usage, array &$state_hints) {
        $token = self::secret($credentials['x_bearer_token'] ?? null);
        if ($token === null) {
            $diagnostics[] = ['source' => 'x', 'status' => 'error', 'code' => 'credentials_missing'];
            return;
        }

        $params = [
            'query' => self::$x_query,
            'max_results' => 10,
            'start_time' => gmdate('Y-m-d\\TH:i:s\\Z', $now - self::X_LOOKBACK_SECONDS),
            'tweet.fields' => 'created_at,author_id,lang,public_metrics',
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
            $diagnostics[] = ['source' => 'x', 'status' => 'error', 'code' => 'transport_error'];
            return;
        }
        if (!self::response_ok($response)) {
            $diagnostics[] = ['source' => 'x', 'status' => 'error', 'code' => 'http_error', 'http_status' => self::status($response)];
            return;
        }

        $json = self::json_body($response);
        $rows = array_slice(is_array($json['data'] ?? null) ? $json['data'] : [], 0, 10);
        $highest_id = self::nullable_text($state['x_since_id'] ?? null);

        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $id = self::text($row['id'] ?? '');
            $text = self::text($row['text'] ?? '');
            $created = self::canonical_time($row['created_at'] ?? null);
            $lang = strtolower(self::text($row['lang'] ?? ''));
            if ($id === '' || $text === '' || $created === null) continue;
            if ($lang !== '' && $lang !== 'id') continue;
            $age = max(0, $now - strtotime($created));
            if ($age > self::X_LOOKBACK_SECONDS) continue;

            $author = self::text($row['author_id'] ?? '');
            $metrics = is_array($row['public_metrics'] ?? null) ? $row['public_metrics'] : [];
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
                'targeting' => [
                    'market_profile' => 'indonesia',
                    'query_lane' => 'x_indonesia_decision_demand',
                    'platform_language' => $lang ?: 'id_query_inferred',
                    'public_metrics' => [
                        'like_count' => self::nonnegative_int($metrics['like_count'] ?? 0),
                        'reply_count' => self::nonnegative_int($metrics['reply_count'] ?? 0),
                        'retweet_count' => self::nonnegative_int($metrics['retweet_count'] ?? 0),
                        'quote_count' => self::nonnegative_int($metrics['quote_count'] ?? 0),
                    ],
                ],
            ];
            $usage['x_posts_returned']++;
            if ($highest_id === null || self::numeric_string_compare($id, $highest_id) > 0) $highest_id = $id;
        }

        if ($highest_id !== null) $state_hints['x_since_id'] = $highest_id;
        $diagnostics[] = ['source' => 'x', 'status' => 'ok', 'returned' => $usage['x_posts_returned']];
    }

    private static function discover_youtube(array $credentials, callable $http_get, array $state, $now, array &$observations, array &$diagnostics, array &$usage, array &$state_hints) {
        $key = self::secret($credentials['youtube_api_key'] ?? null);
        if ($key === null) {
            $diagnostics[] = ['source' => 'youtube', 'status' => 'skipped', 'code' => 'credentials_missing'];
            return;
        }

        $published_after = self::canonical_time($state['youtube_published_after'] ?? null);
        if ($published_after === null || strtotime($published_after) < ($now - self::YOUTUBE_LOOKBACK_SECONDS)) {
            $published_after = gmdate('Y-m-d\\TH:i:s\\Z', $now - self::YOUTUBE_LOOKBACK_SECONDS);
        }
        $latest = $published_after;

        foreach (self::$youtube_queries as $index => $query) {
            $request = [
                'method' => 'GET',
                'url' => self::YOUTUBE_ENDPOINT,
                'query' => [
                    'part' => 'snippet',
                    'type' => 'video',
                    'q' => $query,
                    'maxResults' => 10,
                    'order' => 'date',
                    'publishedAfter' => $published_after,
                    'regionCode' => 'ID',
                    'relevanceLanguage' => 'id',
                    'key' => $key,
                ],
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
                $diagnostics[] = ['source' => 'youtube', 'query_index' => $index, 'status' => 'error', 'code' => 'http_error', 'http_status' => self::status($response)];
                continue;
            }

            $json = self::json_body($response);
            $rows = array_slice(is_array($json['items'] ?? null) ? $json['items'] : [], 0, 10);
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $video_id = self::text($row['id']['videoId'] ?? '');
                $snippet = is_array($row['snippet'] ?? null) ? $row['snippet'] : [];
                $title = self::text($snippet['title'] ?? '');
                $description = self::text($snippet['description'] ?? '');
                $published = self::canonical_time($snippet['publishedAt'] ?? null);
                if ($video_id === '' || $title === '' || $published === null) continue;
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
                    'targeting' => [
                        'market_profile' => 'indonesia',
                        'query_lane' => 'youtube_indonesia_search_demand',
                        'region_code' => 'ID',
                        'relevance_language' => 'id',
                    ],
                ];
                $usage['youtube_results_returned']++;
                if (strcmp($published, $latest) > 0) $latest = $published;
            }
            $diagnostics[] = ['source' => 'youtube', 'query_index' => $index, 'status' => 'ok', 'returned' => count($rows)];
        }
        $state_hints['youtube_published_after'] = $latest;
    }

    private static function deduplicate(array $observations) {
        $unique = [];
        $duplicates = [];
        $seen = [];
        foreach ($observations as $index => $row) {
            if (!is_array($row)) continue;
            $source = self::text($row['source'] ?? '');
            $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
            $id = self::text($payload['id'] ?? '');
            if ($source === '' || $id === '') continue;
            $key = $source . '|' . $id;
            if (isset($seen[$key])) {
                $duplicates[] = ['source' => $source, 'source_ref' => $id, 'kept_index' => $seen[$key], 'duplicate_index' => $index];
                continue;
            }
            $seen[$key] = $index;
            $unique[] = $row;
        }
        return ['observations' => $unique, 'duplicates' => $duplicates];
    }

    private static function hashed_ref($prefix, $raw) {
        return $prefix . '_' . substr(hash('sha256', (string) $raw), 0, 24);
    }

    private static function canonical_time($value) {
        if (!is_string($value) || trim($value) === '') return null;
        $ts = strtotime($value);
        return $ts ? gmdate('Y-m-d\\TH:i:s\\Z', $ts) : null;
    }

    private static function response_ok($response) { return is_array($response) && self::status($response) >= 200 && self::status($response) < 300; }
    private static function status($response) { return is_array($response) ? (int) ($response['status'] ?? 0) : 0; }
    private static function json_body($response) {
        if (!is_array($response)) return [];
        if (is_array($response['json'] ?? null)) return $response['json'];
        $decoded = json_decode((string) ($response['body'] ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }
    private static function secret($value) { $value = self::text($value); return $value === '' ? null : $value; }
    private static function nullable_text($value) { $value = self::text($value); return $value === '' ? null : $value; }
    private static function text($value) { return is_scalar($value) ? trim((string) $value) : ''; }
    private static function nonnegative_int($value) { return max(0, (int) $value); }
    private static function now($now) { return $now === null ? time() : (is_numeric($now) ? (int) $now : time()); }
    private static function numeric_string_compare($a, $b) {
        $a = ltrim((string) $a, '0'); $b = ltrim((string) $b, '0');
        if (strlen($a) !== strlen($b)) return strlen($a) <=> strlen($b);
        return strcmp($a, $b);
    }
}
