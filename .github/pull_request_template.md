# Outcome

<!-- 2–5 sentences: what changed, why, and the user/system outcome. -->

## Scope and ownership

- **Base:** `main` unless there is a real code dependency
- **Depends on:** None / #PR
- **Canonical owner/files:**
- **Production impact:** None / indirect / direct
- **Out of scope:**
- **Risk class:** A / B / C

Risk classes:
- **A — source-only / low runtime risk:** docs, isolated research/tooling, behavior-preserving refactor.
- **B — public/runtime behavior:** theme, BTC/public adapters, caching, assets, user-visible behavior.
- **C — money/access/data integrity:** entitlement, payment/checkout, writes/migrations, canonical market-data semantics.

If the base is not `main`, explain the code-level dependency here. A frozen/accepted `release/*` or `rc-*` is not a normal development base. Preserved post-release work targeting the historical Whitelist release must remain Draft and be ported/recreated from canonical `main` after convergence.

## Local verification — required before Ready

Normal engineer path:

```bash
bash scripts/bitmomo-check.sh test
```

Use `quick` while iterating. Do **not** use GitHub-hosted Actions as the development loop.

Evidence / failures fixed:

```text
...
```

## Runtime acceptance

- [ ] Class A: local/source validation is sufficient for this change
- [ ] Class B: exact deployed staging candidate required before promotion
- [ ] Class B/C: browser/a11y required where user-visible
- [ ] Class C: full exact-SHA gate + staging product/data acceptance required
- [ ] Production smoke/canary required after deployment where applicable

A green source check never substitutes for staging/runtime acceptance, and staging never substitutes for source validation.

## Release identity — only when this is a real candidate

- Exact commit:
- Tree:
- RC ref:
- Artifact ID/hash:
- Managed runtime file count:
- Rollback point:

Release engineer only:

```bash
bash scripts/bitmomo-check.sh full <exact-40-char-sha>
```

An accepted RC is immutable. A blocker creates a focused fix and a **new** RC; never patch the accepted RC in place. Production promotes the accepted artifact unchanged rather than rebuilding it.

## Reviewer focus

1.
2.

## Completion

- [ ] No unrelated changes
- [ ] Canonical owner respected; no duplicated business/data logic
- [ ] No normal direct mutation of `main`, accepted `release/*`, or `rc-*`
- [ ] Superseded/absorbed PRs identified
- [ ] Dependent work will retarget/rebase to `main` after parent merge
- [ ] Working branch can be deleted after merge
