# Outcome

<!-- 2–5 sentences: what changed, why, and the user/system outcome. -->

## Scope

- **Base:** `main` unless there is a real dependency
- **Depends on:** None / #PR
- **Canonical owner/files:**
- **Production impact:** None / indirect / direct
- **Out of scope:**

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

- [ ] Source-only change; staging not required
- [ ] Staging required before promotion
- [ ] Browser/a11y required on deployed candidate
- [ ] Production smoke required after deployment

## Release identity — only when this is a candidate

- Exact commit:
- Tree:
- Artifact/hash:
- Rollback point:

Release engineer only:

```bash
bash scripts/bitmomo-check.sh full <exact-40-char-sha>
```

## Reviewer focus

1.
2.

## Completion

- [ ] No unrelated changes
- [ ] Superseded/absorbed PRs identified
- [ ] Dependent work will retarget to `main` after merge
- [ ] Working branch can be deleted after merge
