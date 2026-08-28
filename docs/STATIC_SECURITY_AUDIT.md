# Bitmomo Intelligence Products — Static Security Audit

**Scope:** the Integration Hardening Phase brief asked for a static audit of both new plugins
(`bitmomo-regime`, `bitmomo-watchtower`) across nine named risk categories, with an explicit
instruction to fix only concrete defects and to make no aesthetic refactor. This document is
that audit. It covers every file under each plugin's `includes/` (the only code that runs),
cross-checked against the admin-only UI files, and is grounded in the same real, tested code
this whole phase has been built against — not a generic WordPress checklist.

**Method:** every finding below was produced by directly grepping and reading the plugin
source (not guessed from memory of what the plugins "should" contain), and the one fix applied
is proven by a new, passing, executable test. No file outside `bitmomo-regime/`,
`bitmomo-watchtower/`, `docs/`, and `scripts/` was touched.

## Summary

| Category | Finding | Status |
|---|---|---|
| Public CPT exposure | All 3 custom post types fully locked down | Clean — no fix needed |
| REST exposure | Zero REST routes registered by either plugin | Clean — no fix needed |
| Unescaped output | Zero unescaped `echo` in either admin screen | Clean — no fix needed |
| Direct external calls | Zero HTTP/curl/socket calls in either plugin's code | Clean — no fix needed |
| Hidden cron | Zero `wp_schedule_event`/cron hooks registered | Clean — no fix needed |
| Direct Telegram/LLM calls | Zero — only doc-comment mentions as explicit non-goals | Clean — no fix needed |
| Unbounded DB growth | All 3 append-only CPTs have no retention/archiving mechanism | **Real gap — documented, not fixed** |
| Unsafe meta writes | One inconsistency found: `Alert_Outbox::enqueue()` skipped enum validation | **Fixed** |
| Accidental bitmomo-pro/bitmomo-ai dependency | Zero code-level references — only doc-comment analogies | Clean — no fix needed |

## 1. Public CPT exposure

Both plugins register a total of three custom post types, all headless, private, and
append-only:

| CPT slug | Plugin | Class |
|---|---|---|
| `bm_regime_state` | bitmomo-regime | `Bitmomo_Regime_State_Store` |
| `bm_watchtower_thesis` | bitmomo-watchtower | `Bitmomo_Watchtower_Thesis_Store` |
| `bm_watchtower_alert` | bitmomo-watchtower | `Bitmomo_Watchtower_Alert_Outbox` |

Every one of the three `register_post_type()` calls uses the identical, fully-locked-down flag
set — confirmed by direct comparison of all three `register_post_type()` argument arrays:

```
'public'              => false,
'show_ui'             => false,
'show_in_menu'        => false,
'publicly_queryable'  => false,
'exclude_from_search' => true,
'show_in_rest'        => false,
'has_archive'         => false,
'rewrite'             => false,
'query_var'           => false,
'capability_type'     => 'post',
```

None of these post types is browsable on the front end, appears in `WP_Query` front-end
results, exposes a REST endpoint, or shows up in `wp-admin`'s post-type menu. **Clean — no
fix needed.**

## 2. REST exposure

`grep -rn "register_rest_route\|rest_api_init"` across both plugins' `includes/` returns zero
matches, and `show_in_rest => false` on all three CPTs means WordPress's own generic REST
controller never auto-registers a route for them either. Neither plugin adds any REST surface
of any kind. **Clean — no fix needed.**

## 3. Unescaped output

Both admin diagnostics screens (`class-bitmomo-regime-admin.php`,
`class-bitmomo-watchtower-admin.php`) were grepped for every `echo` statement, filtering out
lines that already call `esc_html`/`esc_attr`/`esc_url`/`wp_kses` or emit a literal `<` (static
markup with no interpolated data). Zero unfiltered matches in either file — every dynamic value
these screens print is escaped before output. **Clean — no fix needed.**

## 4. Direct external calls

`grep -rEn "wp_remote_(get|post|request|head)|curl_init|file_get_contents\(\s*['"]http|fsockopen|fopen\(\s*['"]http"`
across both plugins' `includes/` returns zero matches. Neither plugin makes an HTTP request,
opens a socket, or reads a remote URL anywhere in its actual code — this is by design (both
are meant to be pure domain logic that a future Codex-built adapter feeds), and it is
confirmed, not assumed. **Clean — no fix needed.**

## 5. Hidden cron

`grep -rn "wp_schedule_event\|wp_next_scheduled\|wp_clear_scheduled_hook"` across both plugins'
`includes/` returns zero matches. Neither plugin schedules anything — running the classifier,
the detector pipeline, or any periodic job is entirely Codex's future runtime responsibility.
**Clean — no fix needed.**

## 6. Direct Telegram/LLM calls

Zero occurrences of Telegram or LLM/AI API calls in either plugin's actual code. The only hits
for "telegram" (case-insensitive, across all `.php` files) are doc-comments and one admin-UI
string that explicitly states *no* Telegram credentials or send logic exist in the plugin (see
`class-bitmomo-watchtower-admin.php`). Same for LLM/AI references — every hit is a doc comment
describing what does *not* exist yet, never a real call. **Clean — no fix needed.**

## 7. Unbounded DB growth — real gap, documented, not fixed

All three append-only CPTs (`bm_regime_state`, `bm_watchtower_thesis`, `bm_watchtower_alert`)
write a new post on every classification/transition/alert and never delete or archive an old
one. Confirmed directly: none of `retention`, `archiv*`, `delete_expired`, `cleanup`, or
`wp_delete_post` appears anywhere in either plugin's `includes/` (the three `has_archive` hits
found by that grep are the unrelated `register_post_type` display flag, already covered in
finding 1, and are set to `false`).

This is not a bug — both plugins were deliberately built as pure, append-only history stores,
and Regime's own `Bitmomo_Regime_History::for_frontend()` and Watchtower's `get_history()`
methods depend on that full history being queryable. But left running indefinitely with no
retention policy, `wp_posts`/`wp_postmeta` will grow without bound: Regime writes roughly one
record per classification run, and Watchtower writes one thesis record per transition plus one
alert record per queued alert. At a plausible cadence (e.g. an hourly classification job) this
is a slow, not urgent, but real operational concern — thousands of rows a year per site, all of
it in `wp_postmeta` given each record's fields are meta, not post content.

**No fix is applied here.** A real retention mechanism (a scheduled pruning job, a hard cap on
rows-per-CPT, or an export-then-delete archival flow) is a product/ops decision — how much
history to keep, and who owns running the prune — not something this audit should decide
unilaterally, and it is explicitly the kind of "new capability" (a cron job) the brief's Task 11
scope (fix concrete defects, no aesthetic refactor) and the phase's overall "no live runtime
work" instruction rule out. **Flagged here for the founder/Codex to decide on and build.**

## 8. Unsafe meta writes — one inconsistency found and fixed

All post-meta writes in both plugins go through exactly three code paths — the three CPT store
classes above — and every one of them shares the same general shape: build a `$state` array
from validated input, then loop `update_post_meta()` over a fixed `META_KEYS` allowlist (never
writing an arbitrary caller-supplied key). Each was checked individually:

- **`Bitmomo_Regime_State_Store::save()`** (bitmomo-regime) — calls
  `Bitmomo_Regime_Input::validate( $raw_input )` and returns early on failure *before* building
  `$state` or touching `update_post_meta()`. Every field of `$state` comes from the validated,
  normalized result. **Safe.**
- **`Bitmomo_Watchtower_Thesis_Store::save()`** (bitmomo-watchtower) — calls
  `Bitmomo_Watchtower_Thesis::validate( $raw_thesis )` and returns early on failure the same
  way, before any meta write. **Safe.**
- **`Bitmomo_Watchtower_Alert_Outbox::enqueue()`** (bitmomo-watchtower) — **was the exception.**
  Unlike the two classes above, it never called a `::validate()` method with a real enum check
  — it only did an `empty()` non-emptiness check on `alert_class`/`transition_type`, then wrote
  whatever string it was given straight into `update_post_meta()`. A caller (including a
  future, not-yet-written Codex adapter) that passed a misspelled or unregistered
  `alert_class`/`transition_type` would have had it silently persisted forever, with no
  rejection and no signal — a real gap relative to how `Bitmomo_Watchtower_Event_Input::
  validate()` and `Bitmomo_Watchtower_Thesis::validate()` both already enforce their enum
  fields against `Bitmomo_Watchtower_Taxonomy`'s registered value lists.

  **Fix applied** (`includes/class-bitmomo-watchtower-alert-outbox.php`, `enqueue()`): two new
  checks were added immediately after the existing non-empty check, validating `alert_class`
  against `Bitmomo_Watchtower_Alert_Policy::ALERT_CLASSES` and `transition_type` against
  `Bitmomo_Watchtower_State_Transition_Engine::TYPES` — both enums already defined inside this
  same plugin, loaded before `Alert_Outbox` in the plugin's own `require_once` order, so this
  introduces no new dependency, no new file, and no cross-plugin coupling. Both checks
  short-circuit with the exact same `{success:false, errors:[...], record:null}` shape the
  method already used for its other validation failure, so no caller's error-handling contract
  changes.

  This is a small, safe, isolated fix directly inside the one method found to be inconsistent
  with the fail-closed pattern used everywhere else in both codebases — not a retune of any
  threshold, not a new capability, not a behavior change for any real caller (every caller in
  this codebase today, `Bitmomo_Watchtower_Orchestrator::process()`, already only ever passes
  real, registered enum values — see `CANONICAL_FIELD_MAPPING.md`/`ALERT_POLICY_SAFETY_REVIEW.md`
  for what that orchestrator does upstream of this call).

  **Test:** `tests/test-bitmomo-watchtower-alert-outbox-contract.php` (new, 10/10 assertions
  passing) proves: a positive control with real enum values still succeeds unchanged; an
  unregistered `alert_class` is rejected; an unregistered `transition_type` is rejected; a
  value that is real but belongs to the *other* enum is still rejected (proving the two checks
  are independent, not "is this string known anywhere in the plugin"); every real
  `ALERT_CLASSES` value and every real `State_Transition_Engine::TYPES` value is still accepted
  (proving the fix adds no false positives); and the pre-existing empty-value rejection still
  fires first, unchanged.

## 9. Accidental bitmomo-pro / bitmomo-ai dependency

`grep -rn` for `bitmomo-pro`, `bitmomo_pro`, `Bitmomo_Pro`, `bitmomo-ai`, `bitmomo_ai`,
`Bitmomo_Ai`/`Bitmomo_AI` across both plugins' `includes/` returns several hits — every single
one is a doc-comment sentence describing an *architectural analogy* ("mirrors how bitmomo-pro
isolates side-effecting code from pure domain logic," "same trust boundary as bitmomo-pro's
launch readiness screen," "same convention as bitmomo-regime and bitmomo-pro") — never a
`require`, `use`, class instantiation, function call, or filter/action hook name that would
create an actual runtime dependency. Cross-checked against `CANONICAL_FIELD_MAPPING.md` (Task
6), which independently confirms neither plugin reads from or writes to anything `bitmomo-pro`
or `bitmomo-ai` owns. **Clean — no fix needed. Zero cross-plugin dependency exists today.**

## What was and wasn't changed

**One file was modified by this audit:**
`bitmomo-watchtower/includes/class-bitmomo-watchtower-alert-outbox.php` — the `enqueue()`
enum-validation fix described in finding 8, above. **One file was added:**
`bitmomo-watchtower/tests/test-bitmomo-watchtower-alert-outbox-contract.php` — the 10-assertion
test proving that fix. Nothing else in either plugin was touched by this audit. The unbounded
DB growth finding (7) is reported, not fixed, per the brief's explicit "no aesthetic refactor,
fix only concrete defects" instruction — a retention mechanism is a new capability (most likely
a cron job), not a defect fix, and is out of scope for this phase.
