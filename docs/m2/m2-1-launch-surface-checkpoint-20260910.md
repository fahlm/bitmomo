# M2.1 launch-surface checkpoint — 2026-09-10

Scope: staging homepage and `/pro/` only. Production was not read or written.

Canonical input note: `docs/ENGINEERING_STRATEGY.md` was requested but is not present on branch `chore/m1-production-baseline-20260910`. The available M1/M0 engineering context was read from `docs/ENGINEERING_CONTEXT.md` and M1 evidence documents on this branch.

## Deterministic staging checks

Staging host: `https://seagreen-snail-158456.hostingersite.com`

| Gate | Homepage | `/pro/` | Result |
| --- | --- | --- | --- |
| Horizontal overflow at 390/768/1024/1440 | `scrollWidth == clientWidth` at all widths; overflow count 0 | `scrollWidth == clientWidth` at all widths; overflow count 0 | PASS |
| Founding/primary conversion tap targets >=44px | Primary hero and whitelist CTAs pass; desktop header `BITMOMO PRO` measures 42px high; newsletter submit measures 40px high | Primary founding CTAs pass; desktop header `BITMOMO PRO` measures 42px high; newsletter submit measures 40px high | BLOCKED |
| Figure formatting and provenance | BTC figure formatting/provenance text present on live homepage | No unrelated page inspected; `/pro/` has commercial copy and whitelist surface, no live BTC figure requiring provenance found in scoped DOM | PASS for observed scoped figures |
| Data loading/empty/error/stale states | Live DOM shows current/fresh, unavailable, and delayed-style copy paths in source; explicit loading/empty/error/stale contract is not fully render-verifiable on scoped live surface | No deterministic evidence for loading/empty/error/stale state behavior on `/pro/` | BLOCKED |
| Page title | `Bitmomo | Insight AI & Crypto Terbaru Indonesia` | `Bitmomo Pro - seagreen-snail-158456.hostingersite.com` | BLOCKED |
| Whitelist flow | Email/name/consent/submit form controls present; no side-effect submit performed | Email/name/consent/submit form controls present; no side-effect submit performed | PASS for non-submitting flow presence |
| M1 staging parity | Live homepage hero meta shows `CONFIDENCE` and `UPDATED`; M1 canonical branch restored `DRIVER`, but that is not live on staging | Not applicable from scoped audit | BLOCKED |

## Failed gates to carry into M2

1. Conversion tap target baseline is not clean because the desktop header `BITMOMO PRO` CTA is 42px high at 1024/1440, and the newsletter submit button is 40px high where counted as conversion.
2. Data state contract is incomplete for M2 gate purposes: loading/empty/error/stale states are not all deterministically observable on the scoped live surfaces.
3. `/pro/` page title exposes the staging host: `Bitmomo Pro - seagreen-snail-158456.hostingersite.com`.
4. Staging does not yet reflect the M1 canonical hero `DRIVER` restoration on the homepage.

## Production

PRODUCTION CHANGED: NO
