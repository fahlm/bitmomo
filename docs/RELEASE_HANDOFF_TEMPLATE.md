# Bitmomo Release Handoff Template

Use this format for every staging or production handoff. Keep it short, exact, and evidence-based.

```text
RELEASE OBJECTIVE:
CANONICAL ISSUE:
CANONICAL BRANCH:
SOURCE SHA:
SOURCE TREE:

CHANGESET:
- ...

EXPLICITLY NOT CHANGED:
- ...

SOURCE / CI:
- authoritative suite: PASS / FAIL / BLOCKED / NOT RUN
- runner actually executed: YES / NO
- relevant run IDs:

ARTIFACT:
- status: GENERATED / NOT GENERATED
- artifact name/id:
- SHA-256:
- deterministic rebuild parity: PASS / FAIL / NOT RUN

STAGING:
- status: DEPLOYED / NOT DEPLOYED
- environment:
- exact deployed artifact/SHA:
- cache purge/action:

RUNTIME:
- filesystem/source parity: PASS / FAIL / NOT RUN
- cache/CDN asset coherence: PASS / FAIL / NOT RUN
- data/provider readiness: PASS / FAIL / DEGRADED / NOT RUN

BROWSER:
- responsive matrix: PASS / FAIL / NOT RUN
- keyboard/focus: PASS / FAIL / NOT RUN
- 200% zoom: PASS / FAIL / NOT RUN
- console: PASS / FAIL / NOT RUN
- Axe serious/critical: PASS / FAIL / NOT RUN

PRODUCT READY:
- profile: whitelist / paid / other
- status: PASS / FAIL / NOT RUN
- blockers:

PRODUCTION:
- authorization: GRANTED / NOT GRANTED
- deployed: YES / NO
- exact production identity:
- post-deploy verification: PASS / FAIL / NOT RUN

ROLLBACK:
- used: YES / NO
- rollback point/procedure:

FINAL STATE:
- SOURCE: PASS / FAIL
- ARTIFACT: PASS / FAIL / NOT RUN
- STAGING: PASS / FAIL / NOT RUN
- RUNTIME: PASS / FAIL / NOT RUN
- BROWSER: PASS / FAIL / NOT RUN
- PRODUCT READY: PASS / FAIL / NOT RUN
- PRODUCTION AUTHORIZED: YES / NO
- PRODUCTION VERIFIED: PASS / FAIL / NOT RUN

NEXT ACTION:
OWNER:
```

## Rules

- Never write `latest`, `final`, or `current` without also naming the branch/SHA/artifact being referenced.
- `CI BLOCKED` is not `CI FAIL` and is not `CI PASS`.
- `SOURCE PASS` is not permission to deploy production.
- A staging deploy is not staging acceptance until runtime/browser/product gates pass.
- Never reuse a superseded artifact because a newer candidate cannot build.
- If rollback occurs, record the exact restored version and why.
