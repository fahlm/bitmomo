# Bitmomo AI

Conditional automated publishing foundation for AI Market Insight and Bitcoin Signal.

## Phase AI-2j

- The daily Binance scheduler publishes automatically when at least six of the seven quality checks pass.
- Freshness, completeness, reference price, zone ordering, bias/score consistency, and invalidation consistency are hard blockers; they can never be waived by the 6/7 threshold.
- The only currently tolerated soft failure is excessive operational-level distance, which produces a `degraded` quality status and remains visible in diagnostics.
- Every publication attempt is recorded in a bounded 30-entry audit log.
- Auto-publish is disabled by default. Define `BITMOMO_AI_AUTO_PUBLISH` as `true` in `wp-config.php` only after production shadow validation and explicit approval; set it to `false` for an immediate kill switch.
- TradingView webhook submissions remain draft-only; conditional auto-publish is limited to the scheduled Binance pipeline.

## Phase AI-1

- Custom post types for insights and signals.
- REST-visible structured signal metadata.
- Draft-first editorial workflow for manual and TradingView inputs.
- Shortcodes: `[bitmomo_market_insights limit="3"]` and `[bitmomo_bitcoin_signal]`.
- No API keys or provider credentials stored in the repository.

## Phase AI-2a

- TradingView webhook with a server-side secret, freshness checks, deduplication, and rate limiting.
- Deterministic five-axis scoring that only creates editorial drafts.
- Multi-timeframe policy: 4H primary, 1H tactical confirmation, and 1D regime guardrail.

## Phase AI-2b

- Free, read-only Binance public-data adapter; no Binance account or API key required.
- Fetches 1H/4H/1D futures candles, mark/index basis, funding history, and open-interest history.
- WordPress daily schedule targets 19:10 WIB and conditionally auto-publishes the Binance edition.
- Manual staging test is available under **Tools → Bitmomo AI**.
- For reliable timing, configure the hosting cron to call WordPress cron every five minutes.

## Phase AI-2c

- Five auditable axes with normalized `-100..+100` directional scores (volatility uses `0..100` intensity).
- Volatility combines 4H ATR, daily percentile regime, and 4H Bollinger Band Width.
- Direction combines 4H ADX/+DI/-DI with 1H, 4H, and 1D alignment.
- Carry uses perpetual funding and mark/index basis.
- Structure evaluates 4H swing breaks/trend structure with a daily guardrail.
- Crowding combines 24H open-interest change, global long/short accounts, and taker buy/sell ratio.
- Binance calculations ignore the still-open candle, and every axis stores a concise reason alongside raw inputs.

## Phase AI-2d

- Indonesian editorial report with a headline, multi-timeframe context, five responsive axis cards, bull/bear scenarios, and bias invalidation.
- Diagnostics retain a fresh five-axis preview even when daily draft deduplication correctly prevents another post.
- A seven-check quality gate blocks stale or incomplete data, overlapping operational levels, and bias/invalidation contradictions. A distant operational level is the only non-critical check eligible for the later 6/7 policy.
- A forward-validation ledger evaluates each daily bias against the following 24-hour close, high, low, support/resistance tests, and risk-level event; only outcomes observed in the 22–27 hour window are scored.
- The editorial release gate requires acceptable quality and analysis no more than 180 minutes old. Manual and TradingView submissions require editor approval; the scheduled Binance path may use a one-time internal auto-publish authorization.
- The editor now shows an Indonesian pre-release checklist, a visible expiry countdown, and a warning during the final 60 minutes before data becomes too old to publish.
- WordPress administrators receive an internal notice when a new draft needs review, is nearing expiry, has expired, is blocked, or is ready for manual release; no email or external notification is sent.
- Audience copy now follows the actual volatility score, uses explicit breakout language for support/resistance, and includes the approved neutral caution: “Hati-hati jika ingin mengejar pergerakan harga.”
- The high-volatility caution is appended consistently across confirmed, rejected, and still-testing support/resistance states.
- Automation health reports the next and last run in WIB, detects a missing schedule or a run overdue after 19:30 WIB, identifies the WP-Cron trigger mode, and warns administrators when intervention is required.

## Phase AI-2e

- Data-quality guard records source, completeness, age, last closed candle, and missing optional fields.
- Optional crowding endpoints degrade to `partial` instead of stopping the entire daily analysis.
- Risk engine uses nearby 4H support/resistance plus an ATR buffer and warns editors when invalidation is more than 5% away.

## Phase AI-2f

- Responsive staging dashboard for bias, confidence, data quality, five axes, risk levels, scenarios, and update time.
- Dashboard shortcode is restricted to the exact staging hostname unless viewed by an administrator.
- Diagnostics can safely create or update the no-index staging preview page without publishing a signal draft.

## Phase AI-2g

- Audience-first editorial dashboard hides indicator jargon and presents a plain-language conclusion, suggested posture, reasons, price areas, and two scenarios.
- Technical inputs remain stored in the editor preview and post metadata for auditability.

## Phase AI-2h

- Restores the concise editorial copy approved for a general audience.
- Adds a four-card Market Pulse with plain-language labels and three-step visual scales for direction, activity, risk, and confidence.

## Phase AI-2i

- Daily operational zones now use confirmed 1H pivots with an ATR distance guard; 4H remains trend context rather than the displayed trading range.
- Public copy describes conditions and possibilities instead of issuing action-oriented recommendations.

## Publishing cadence

- Normal edition: use the fully closed 4H candle at 19:00 WIB and target draft readiness at 19:10 WIB.
- Major US macro days during daylight saving time: keep the normal pre-release edition and optionally create a short reaction update at 19:40-19:50 WIB.
- Major US macro days during standard time: the normal edition remains a pre-release scenario because common 08:30 ET data arrives at 20:30 WIB.
- Scheduled Binance content publishes automatically only when explicitly enabled and after the conditional release gate. Manual and TradingView-generated content remain drafts by default.

## TradingView webhook

Define a random token of at least 32 characters in `wp-config.php`:

`define('BITMOMO_AI_WEBHOOK_TOKEN', 'replace-with-a-random-token');`

Send JSON to:

`https://example.com/wp-json/bitmomo-ai/v1/tradingview/TOKEN`

The endpoint validates freshness and required axes, rejects duplicates, evaluates deterministic multi-timeframe rules, and creates a Bitcoin Signal draft. Webhook content never publishes automatically.

TradingView source and setup instructions are in `docs/tradingview/`.
