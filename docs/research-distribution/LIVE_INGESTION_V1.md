# Bitmomo Live Read-Only Ingestion V1

Status: P6 CONTRACT / READ-ONLY / NO PLATFORM WRITES  
Batch contract: `live-ingestion-batch-v1`  
Tracks: #156  
Depends on: PR #155 Acquisition Orchestrator and all upstream research-distribution contracts.

## Purpose

P6 is the first layer allowed to obtain fresh public demand observations from external platforms. It remains strictly read-only and feeds the existing P1-P5 engine without changing any scoring, evidence, content, engagement, or attribution semantics.

## Verified platform surfaces

### X

V1 targets only:

`GET https://api.x.com/2/tweets/search/recent`

The official recent-search surface searches public Posts from the last seven days and supports query operators, pagination, and up to 100 Posts per request. Bitmomo deliberately uses a much smaller cap.

Current official pay-per-use pricing charges Post reads per resource returned. V1 therefore treats result volume as a cost budget, not just a rate-limit concern.

### YouTube

V1 targets only:

`GET https://www.googleapis.com/youtube/v3/search`

with `part=snippet` and `type=video`.

The official `search.list` endpoint supports query terms, publication-time filters, ordering, region/language relevance hints, and up to 50 results per call. Since June 2026, `search.list` has its own granular quota bucket; the documented default is 100 calls/day with one search-quota unit per call.

## Default query budget

### X

Three clustered queries:

1. decision demand: signal/sinyal/long-or-short;
2. analysis demand: analysis/analisa/next-move/arah;
3. structure/explanation demand: support/resistance/breakout/why/kenapa.

Each cluster requests exactly 10 Posts in V1. Therefore the default and hard V1 ceiling is 30 returned Posts requested per run before dedupe.

At the currently documented $0.005/Post read rate, that corresponds to a theoretical maximum of $0.15 in Post-read resources per fully populated run. This figure is surfaced for operator visibility and must be reviewed if X pricing changes.

### YouTube

Up to four clustered searches per run, defaulting to:

- `btc long or short`
- `bitcoin analysis today`
- `sinyal bitcoin hari ini`
- `ai bitcoin research`

Each search requests at most 10 video results. V1 therefore consumes at most four `search.list` calls per run, well below the documented default 100-call daily bucket when run at modest cadence.

## Credential boundary

Runtime caller may provide:

- `x_bearer_token`
- `youtube_api_key`

Credentials are passed only to the injected HTTP client request. They are never returned in:

- observations;
- diagnostics;
- usage;
- state hints;
- P5 acquisition output.

P6 does not persist credentials.

If credentials for one source are missing, that source emits `credentials_missing`; the other source can continue.

## Network boundary

P6 contains no native cURL/Guzzle/browser implementation. The caller must inject a callable HTTP client.

This makes network use explicit and allows CI to execute with deterministic fake responses and zero external calls/cost.

A future runtime wrapper may provide the HTTP implementation, but it must preserve endpoint allow-listing and read-only behavior.

## X normalization

X Post resources become P1-compatible observations containing:

- source `x`;
- Post id as source id;
- canonical status URL;
- surface `post`;
- Post text;
- SHA-256-derived opaque author ref rather than raw author id;
- `created_at`.

Caller-supplied `x_since_id` is propagated to every cluster. V1 returns the highest observed Post id as the next state hint.

## YouTube normalization

YouTube video search results become P1-compatible observations containing:

- source `youtube`;
- video id;
- canonical watch URL;
- surface `search_result`;
- title and description;
- SHA-256-derived opaque channel ref rather than raw channel id;
- `published_at`.

Caller may supply `youtube_published_after`, region, and relevance-language hints. Without a checkpoint, V1 defaults to a 24-hour lookback, bounded to at most seven days.

## Deduplication

P6 deduplicates public resources by `source + resource id` before P5 handoff. Duplicates remain visible in diagnostics.

This is separate from X billing deduplication and should not be interpreted as a billing guarantee.

## Partial failure

Each source/query is isolated. Transport/HTTP failure on one X cluster or one YouTube query:

- creates an explicit diagnostic;
- does not synthesize data;
- does not block successful observations from other queries/sources.

## P5 handoff

`run_acquisition()` passes the normalized observation batch directly to `Bitmomo_Acquisition_Orchestrator_V1`.

P6 cannot override P5 safety:

- engagement remains human-review-only;
- auto-send remains OFF;
- owned content remains draft-only;
- auto-publish remains OFF;
- no production mutation occurs.

## Repository / production boundary

Implementation/tests live under:

- `research/live-ingestion/includes/`
- `research/live-ingestion/tests/`

P6 does not add:

- WordPress/runtime files;
- database state;
- scheduler/cron;
- platform write endpoints;
- browser scraping;
- production analytics instrumentation.

## Next gate

After P6 source acceptance, the remaining blocker for a true live read-only trial is runtime credential/access provisioning plus an explicit operator cost ceiling. The first live trial should be manually invoked, low-volume, and should persist neither credentials nor observations until observed quality/cost is reviewed.
