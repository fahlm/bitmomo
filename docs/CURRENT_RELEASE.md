# Current Release — Whitelist V1

## Production objective
Launch the Founding Membership whitelist quickly without reopening accepted source work.

## Accepted candidate
- RC snapshot: `rc-whitelist-v1-20260916-02`
- Commit: `c33d128d5681909337ffc8b0811647012532fe9e`
- Tree: `8977918a0f619054641b13b3b0bbf9eb4cb04a74`
- Full Release run: `35012592515` — PASS
- Artifact ID: `10414093076`
- ZIP SHA-256: `632a4ea567f0bd1902d5267abeea32b5c8950a909bf4d17333ad662078913b1b`
- Runtime TAR SHA-256: `e0cce650571fe510643bf371eb12f202e60a282dc26291f12d62898b715c6ce3`
- Managed runtime files: `118`

## Acceptance state
- Source: PASS / frozen
- Artifact: PASS
- Staging parity/readiness: PASS
- Browser/accessibility: PASS
- Checkout: OFF — expected for whitelist
- WhatsApp: OFF — expected
- Production: explicit approval pending

## GitHub Actions cost posture
Repository `main` is in **zero-cost hosted-runner mode**. PR/push/scheduled hosted Actions are disabled and the remaining manual workflow entries are hard-locked with a false job condition.

Engineers validate locally:

```bash
bash scripts/bitmomo-check.sh quick
bash scripts/bitmomo-check.sh test
bash scripts/bitmomo-check.sh full <exact-40-char-sha>   # release engineer only
bash scripts/bitmomo-check.sh smoke <base-url>
```

Do not re-enable hosted Actions merely for development convenience. If continuous monitoring is required, run it outside GitHub-hosted CI.

## Next action
Production promotion must use the exact accepted artifact above without rebuilding. Required cutover: backup/rollback point → verify artifact identity → deploy exact artifact → purge cache/CDN → parity/routes → checkout/WhatsApp remain OFF → one real internal `wp_mail()` whitelist canary → BTC freshness → analytics receipt.

If production-only failure appears, rollback or create one focused fix and a new RC. Never patch the accepted RC in place.

After production verification: converge accepted release source to `main`, close #135, retire temporary release/RC branches, then resume post-release work from current `main`.
