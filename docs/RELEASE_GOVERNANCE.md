# Bitmomo Release Governance

This document is the canonical release state machine for public Bitmomo runtime changes.

The purpose is to prevent a green source CI result from being mistaken for a production-ready product. A release is accepted only when the exact candidate is proven across source, artifact, staging runtime, guest-facing assets, browser behavior, and product readiness.

## Release state machine

A candidate moves in one direction only:

`SOURCE CANDIDATE -> REVIEWED ARTIFACT -> STAGING DEPLOYED -> RUNTIME VERIFIED -> BROWSER ACCEPTED -> PRODUCT READY -> PRODUCTION AUTHORIZED`

A failure at any stage stops promotion. Production must not be used as a validation environment.

## 1. Source candidate

A release candidate is one exact Git commit SHA and git tree.

For `release/**` candidates the following are authoritative and must run on the exact candidate, not as an undocumented local substitute:

- Full Release Safety;
- Theme Safety;
- Authority Surface Safety;
- Homepage Research Boundary when its owned files change.

Full Release Safety deliberately repeats the launch-critical source contracts so a release candidate cannot lose authority merely because a narrower workflow trigger does not fire.

## 2. Reviewed artifact

The runtime is built once from the exact candidate.

`bitmomo-runtime-manifest.json` records:

- `source_commit`;
- `source_tree`;
- deterministic runtime TAR SHA-256;
- packaging-definition SHA-256;
- every managed runtime file and its SHA-256.

The artifact manifest must identify the same commit/tree as the candidate under review.

Never promote an artifact whose provenance is inferred from `BM_VERSION`. `BM_VERSION` is a semantic theme version, not a release identity.

## 3. Staging deployment

Only the reviewed runtime artifact is deployed to canonical staging:

`https://seagreen-snail-158456.hostingersite.com`

Before deployment, preserve a rollback point.

After deployment run the existing staging side-effect safety check and exact artifact parity check. For parity, bind the manifest to the intended candidate explicitly, for example:

```bash
python3 scripts/check-staging-artifact.py \
  dist/bitmomo-runtime-manifest.json \
  --expected-commit "$CANDIDATE_SHA" \
  --expected-tree "$CANDIDATE_TREE" \
  --expected-artifact-sha256 "$RUNTIME_TAR_SHA256"
```

A filesystem match proves deployed bytes, not browser cache correctness or WordPress content/config readiness.

## 4. Guest-facing asset coherence

After cache purge, verify what an unauthenticated visitor actually receives:

```bash
python3 scripts/check-staging-asset-coherence.py \
  --base-url https://seagreen-snail-158456.hostingersite.com
```

This gate checks both the canonical cached homepage and a cache-busted request. Canonical first-party CSS/JS must:

- render with the expected content-hash version query;
- return bytes whose SHA-256 matches the exact checked-out candidate.

This is the authority for stale Hostinger/LiteSpeed/CDN asset incidents. Do not add emergency CSS overrides until this gate proves the browser is receiving current assets.

## 5. Browser acceptance

Run UI Browser Safety against canonical staging after artifact parity and asset coherence pass.

Minimum viewport matrix:

- 360x800;
- 390x568;
- 390x844;
- 768x1024;
- 1024x900;
- 1440x1000.

Blocking browser requirements include:

- no horizontal overflow;
- one intentional visible H1;
- no sticky-header obstruction;
- mobile navigation accessible open/closed states;
- closed navigation cannot receive real keyboard Tab focus;
- Escape closes the menu and returns focus;
- short-height mobile menu remains usable;
- console/page errors = 0;
- Axe serious/critical = 0;
- qualified Research -> article journey exists;
- screenshots/report are retained as evidence.

Social destinations are fail-closed. An absent unconfigured channel is valid. Exact social destinations become mandatory only when the release environment explicitly supplies an expected social map.

## 6. Product readiness

Source/runtime correctness and product readiness are separate gates.

Run:

```bash
BITMOMO_RELEASE_PROFILE=whitelist \
BITMOMO_STAGING_ROOT=/home/u689746960/domains/seagreen-snail-158456.hostingersite.com/public_html \
bash scripts/check-staging-readiness.sh
```

### Whitelist profile

Blocking:

- Bitmomo AI active;
- BTC Intelligence active;
- Bitmomo Pro active;
- Privacy published;
- Disclaimer published;
- at least one qualified Market Research article;
- canonical BTC public snapshot operational (`fresh` or visibly `delayed`, never `unavailable`);
- whitelist AJAX/shortcode operational;
- paid checkout remains disabled.

MailPoet is reported but is not a whitelist blocker because Founding whitelist storage is independent from MailPoet. If a public newsletter surface claims to be operational, its own browser acceptance must still prove that claim.

Terms are reported but become a hard blocker in the paid profile.

### Paid profile

In addition to the common product checks:

- Terms must be published;
- checkout must be configured;
- payment/entitlement/lifecycle acceptance must be completed before authorization.

## 7. Production authorization

Production promotion is authorized only when all applicable gates above are PASS on the same candidate/artifact lineage.

Record at minimum:

- candidate commit SHA;
- candidate git tree;
- artifact ID/name;
- runtime TAR SHA-256;
- staging rollback point;
- staging parity result;
- asset coherence result;
- browser acceptance result;
- product readiness result;
- final production authorization decision.

Promotion should reuse the exact staging-accepted runtime artifact. If the release process creates a different commit/tree/artifact after acceptance, that new artifact is a new candidate and must repeat staging acceptance.

## 8. Failure classification

Use these categories so fixes go to the correct owner:

- **SOURCE FAILURE** — syntax, deterministic tests, architecture/contracts;
- **ARTIFACT FAILURE** — nondeterminism, manifest/provenance mismatch;
- **DEPLOYMENT FAILURE** — staging filesystem differs from artifact;
- **CACHE/ASSET FAILURE** — guest HTML or CDN bytes differ from candidate;
- **BROWSER FAILURE** — responsive, keyboard, Axe, console, visual/runtime behavior;
- **PRODUCT READINESS FAILURE** — content, plugin activation, BTC state, legal/config/service dependency;
- **BUSINESS READINESS FAILURE** — launch profile or commercial lifecycle is incomplete.

Do not fix one category by weakening a gate in another category.
