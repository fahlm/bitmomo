# M1 production rescue snapshot — 2026-09-10

This directory preserves production-only managed Bitmomo files observed on `bitmomo.id` during read-only M1 inventory. These files are evidence, not a merge decision.

Production root verified: `/home/u689746960/domains/bitmomo.id/public_html`.

The files under `website/` are the 18 managed production files whose hashes differ from the M0 staging baseline commit `e195619b8030fbe43a8ded48410040a07c2899af`. Production also lacks `wp-content/themes/bitmomo-child-v3/assets/css/bitmomo-typography.css`, which exists in the M0/staging baseline.

M1 stop condition: the differences are semantic product/configuration conflicts, so no production deploy path may overwrite or merge them silently.

PRODUCTION CHANGED: NO
