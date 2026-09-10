# Server-only themes — forensic archive, 10 September 2026

Two WordPress themes that existed **only** on the Bitmomo staging server
(`seagreen-snail-158456.hostingersite.com`) and in **no commit of this
repository**. Checked against all 386 commits reachable from every ref on
10 Sep 2026: neither directory appears in any of them.

They are archived here before deletion from the server, because code that
exists in exactly one place is how this project came to need a rescue in
the first place. Nothing in this branch is used by the live site.

## What is here

| Path | Files | Bytes | What it is |
|---|---|---|---|
| `themes/bitmomo-speed/` | 7 | 36,140 | A standalone theme — `Theme Name: Bitmomo Speed`, "Ultra-fast custom theme for Bitmomo - zero bloat, maximum performance", Version 2.0, Author: Bitmomo Team. Not a child theme; a parallel architecture to the `hello-elementor` + `bitmomo-child-v3` line that shipped. |
| `themes/bitmomo-child/` | 4 | 11,147 | The earliest child theme of `hello-elementor`. Its own comments describe the design as "inspired by ChainOfThought.xyz". Placeholder hero image from Pexels, `action="#"` newsletter form. |

## Provenance and fidelity

Each file was read from the server through the Hostinger file API and
rewritten here. **Every file was verified byte-for-byte against the size the
server reported**; all 11 match exactly. That check is what caught two
files whose trailing newline differed, which were then corrected.

Byte-length equality is not proof of identical content — see
`docs/FOUNDATION_BRIEF_V2.md` §3.3. If anything here is ever to be
*reused* rather than merely kept, diff it against the server copy first,
while the server copy still exists.

## Notes worth keeping

- `bitmomo-speed` uses `#0c1c2a` as its ground colour and `#f4ad32` as its
  accent — both are in the approved palette today. Its teal is `#26d0c6`,
  which later became `#2dd4bf`. This is where two thirds of the current
  palette appears to originate.
- `bitmomo-speed/style.css` carries the same broken font stack that was
  diagnosed on the live site on 9 Sep: `ui-sans-serif, system-ui,
  -apple-system, Inter, ...` — `Inter` sits after `system-ui`, so it never
  applies. The defect is older than the theme that shipped.
- `bitmomo-speed/footer.php` contains a complete newsletter modal with
  focus management, Escape handling and a `#subscribe` hash trigger, plus
  a MailPoet shortcode integration with an inline fallback form.
- `bitmomo-speed/functions.php` defines `BM_Nav_Walker`, `bm_fallback_menu`,
  `bm_reading_time`, `bm_get_excerpt` and `bm_get_thumbnail`, and a
  `WP_DEBUG`-gated render-time/peak-memory comment in the footer.

## Deletion

Once this branch is pushed, these two directories can be removed from the
server. They are not referenced by the active theme and removing them
changes nothing that renders.
