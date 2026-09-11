# M2 release reliability record

## Canonical lineage

The M2 replacement release branch starts at current `main` and merges the
approved M2 commit `86e0c7423353f3385c0d6353128306bde52d8bce` with a merge
commit. PR #70 remains untouched and must not be merged.

Runtime packaging is controlled by `config/production-runtime.json`. The
artifact builder must reject unclassified files, missing required files, file
count drift, and any `tests/` path in the output.

## Production rollback evidence

Read-only backup capture ID: `20260911T090710Z`.

- Managed-files snapshot: `production-managed-files.tar.gz`
- Managed-files SHA-256: `17cc0b735250df4e75a9fb5cea19e52982b632c2d65144d739edcea71698c360`
- Database snapshot: `production-database.sql.gz`
- Database SHA-256: `4c27bcda7be6a53f5e9a43a5746177fafcef0b8e37c465794774aa9aaa7e5732`
- Production-equivalent runtime artifact SHA-256: `cee5a8137381e108519b3391f366478de6e5086d0a16febfae2980a1d24699d5`
- Storage: protected local release-backup directory outside the repository
- File archive integrity and clean extraction: passed
- Database gzip integrity: passed
- Hostinger provider restore flow: not verified from this environment

The snapshots can contain credentials or private customer data. They must
never be committed, uploaded as a CI artifact, or copied to an untrusted host.

## Rollback procedure

1. Stop the release and record the failed release SHA and remote manifest.
2. Confirm the backup ID and both SHA-256 values above before extraction.
3. Restore managed files from the protected snapshot to a temporary server
   directory and compare its per-file manifest before replacing live files.
4. Restore the database only when the incident requires database rollback and
   only from the matching database snapshot. A code-only incident must not
   overwrite newer customer data.
5. Re-run the managed runtime comparison and require `missing=0`, `changed=0`,
   and `unexpected=0`.
6. Run the homepage and `/pro/` smoke checks. Purge caches only with separate
   authorization.

## Staging re-baseline plan

Current staging is untrusted. Its read-only backup capture ID is
`20260911T090832Z-staging`; file and database archives passed gzip integrity.

Before a staging deployment:

1. Require `blog_public=0` and verify an HTTP `noindex` response.
2. Set `BITMOMO_AI_AUTO_PUBLISH` to false. It was true at baseline capture.
3. Keep checkout/payment configuration empty.
4. Establish an explicit staging-only outbound-email kill switch before form
   submission tests; the current plugin calls `wp_mail()` directly.
5. Decide whether the configured inbound AI webhook remains enabled for
   diagnostics or is disabled during parity testing.
6. Deploy only the reviewed runtime artifact, never the repository tree.
7. Delete source-only files inside the managed directories only after the
   staging backup identifiers and hashes are rechecked.
8. Compare the deployed files to the artifact manifest and require
   `missing=0`, `changed=0`, and `unexpected=0`.

No staging reconciliation should proceed while steps 2, 4, and 5 are
unresolved.

## Runtime-state decision

Current code stores the latest generation result, latest global quality gate,
and latest valid preview separately. `free_projection()` nevertheless requires
the global gate to pass, so a blocked newer attempt makes a still-valid older
preview unavailable.

The minimum compatible correction would:

1. Add a dedicated `bitmomo_ai_latest_attempt` record with edition, timestamp,
   completeness, missing inputs, source observations, age, and gate reasons.
2. Bind quality-gate status to each valid preview when that preview is stored.
3. Validate the latest preview against its own quality and real timestamp,
   rather than against the newer global attempt gate.
4. Keep the existing six-hour fresh and thirty-hour maximum display ages.
5. Continue failing closed when the snapshot is partial, invalid, fabricated,
   or older than thirty hours.

This changes customer-visible fail-closed behavior and therefore requires a
CTO semantic decision before implementation. Expected files are:

- `website/wp-content/plugins/bitmomo-ai/bitmomo-ai.php`
- `website/wp-content/plugins/bitmomo-ai/includes/class-bitmomo-ai-scheduler.php`
- `website/wp-content/plugins/bitmomo-ai/includes/class-bitmomo-ai-binance.php`
- `website/wp-content/plugins/bitmomo-ai/tests/test-bitmomo-ai-runtime-state.php`
- existing public-projection and public-adapter contract tests

No runtime semantic correction has been deployed to staging or production.
