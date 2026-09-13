# Summary

<!-- In 2–5 sentences: what changes, why it matters, and the user/system outcome. -->

## Canonical context

- **Issue / objective:** <!-- #123 or N/A -->
- **Base branch:** <!-- normally main -->
- **Depends on:** <!-- #PR or None -->
- **Supersedes:** <!-- #PR or None -->
- **Superseded by:** None
- **Release line:** <!-- None, or the single active release branch -->
- **Production impact:** <!-- None / indirect / direct -->

> Default rule: branch from current `main` and target `main`. A non-`main` base requires a real code dependency and must be explained here. PR numbers are not versions.

## Scope

### In scope
- 

### Explicitly out of scope
- 

## Change type

- [ ] Product / feature
- [ ] Bug fix
- [ ] Refactor
- [ ] Data / intelligence logic
- [ ] UI / UX
- [ ] CI / release engineering
- [ ] Documentation / research only
- [ ] Hotfix

## Safety boundaries

- **Customer-visible behavior changed:** Yes / No
- **Data contract changed:** Yes / No
- **Database/state changed:** Yes / No
- **Entitlement/payment changed:** Yes / No
- **External API/provider changed:** Yes / No
- **Security/privacy boundary changed:** Yes / No
- **Rollback available:** Yes / No / N/A

If any answer is **Yes**, explain the impact and compatibility/rollback strategy.

## Verification

### Deterministic/source checks
- [ ] Relevant lint/static checks pass
- [ ] Relevant deterministic tests pass
- [ ] No unrelated files changed
- [ ] New behavior has regression coverage where practical

### Runtime/browser checks
- [ ] Not required for this change
- [ ] Required on staging before merge/promotion
- [ ] Required after production deployment

Evidence / commands / run IDs:

```text
...
```

## Release state

Mark exactly the state that is true **now**. Do not claim later states early.

- [ ] SOURCE — implementation/review only
- [ ] ARTIFACT — deterministic candidate artifact exists
- [ ] STAGING — exact artifact deployed to canonical staging
- [ ] RUNTIME — filesystem/cache/data parity verified
- [ ] BROWSER — responsive/accessibility/browser acceptance passed
- [ ] PRODUCT READY — product-specific launch/readiness checks passed
- [ ] PRODUCTION AUTHORIZED — explicit owner/release authorization granted
- [ ] PRODUCTION VERIFIED — deployed production runtime verified

**Current release status:**

## Deployment / rollback

- **Deploy target:** None / staging / production
- **Exact artifact / commit:**
- **Cache action:**
- **Migration/manual step:**
- **Rollback procedure:**

## Reviewer focus

<!-- Name the 1–3 things most likely to be wrong or most important to verify. -->

1. 
2. 
3. 

## Completion rule

After merge or supersession:
- close absorbed/superseded PRs;
- retarget/rebase any dependent child PR to current `main` as soon as possible;
- do not leave a completed PR open as a historical bookmark;
- delete the merged working branch when repository settings/process allow it;
- move durable rationale into canonical docs instead of relying on PR history alone.
