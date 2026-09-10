# M1 two-target deploy runbook

The M1 deploy path is target-explicit and fail-closed.

- Staging target: `config/deploy-targets/staging.json`
- Production target: `config/deploy-targets/production.json`
- Optional local secret/config overrides: copy `config/deploy-secrets.example.env` outside git and fill host aliases or roots there.
- Production baseline manifest: `docs/m1/production-managed-manifest-20260910.json`
- Staging remote manifest: `.bitmomo-m0-deploy-manifest.json`
- Production remote manifest name reserved for future writes: `.bitmomo-production-deploy-manifest.json`

Dry-run examples:

```sh
python3 scripts/bitmomo-deploy.py dry-run --target staging
python3 scripts/bitmomo-deploy.py dry-run --target production
```

Production writes are disabled in the committed production target and still require this exact phrase if enabled later:

```text
I UNDERSTAND THIS WRITES BITMOMO PRODUCTION
```

The tool exits non-zero when remote drift, unclassified extras, or classified-extra hash mismatches are found. It reports missing/update candidates without writing during dry-run.

PRODUCTION CHANGED: NO
