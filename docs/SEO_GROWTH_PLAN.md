# Bitmomo SEO & Growth Plan

_Read-only audit. Prepared locally, not yet committed. No code, WordPress settings, PR #7, or PR #15 were touched to produce this document._

## 1. Executive diagnosis

- Technical SEO basics are in decent shape (Rank Math active, correct title/description/canonical, clean robots.txt, valid sitemap index, single H1, clean heading hierarchy).
- The one concrete gap in the current metadata is missing `og:image`/`twitter:image` — already flagged in the earlier production-readiness audit, unresolved.
- Schema (Organization/Article/Breadcrumb) status is UNKNOWN — not verifiable from theme code (theme injects none, so it's entirely Rank Math's responsibility) and live `<script>` output couldn't be inspected this session.
- The biggest structural SEO problem is that BTC Daily Intelligence — Bitmomo's actual differentiator and the top of the funnel this business depends on — has no indexable URL at all. It only exists as a homepage JSON fragment. Nothing to rank, nothing to link to from X, nothing with a permalink history.
- Category/tag/pagination is implemented correctly (`paginate_links()` present, no theme-level noindex), so archive indexability is not the constraint — content volume and structure are.
- The "big-stories" tag archive (`/tag/big-stories/`) duplicates the homepage's BIG STORIES teaser list. Low-risk (teasers only, canonical is each article), not worth fixing now.
- Zero E-E-A-T trust signals exist today beyond what's already queued in the pending frontend PR (Tentang Kami / Kebijakan Privasi / Disclaimer footer links) — no author identity, no methodology page, no corrections policy.
- Current nav/categories (Tren AI, Berita, Riset, Analisis Koin) are reasonable but don't yet map cleanly to the "AI-powered crypto intelligence" positioning or to the high-intent keyword clusters below.
- Recommended architecture is intentionally minimal: one evergreen BTC hub, not a daily-page factory — matches solo-founder maintainability and avoids index bloat/cannibalization.
- Nothing here requires PHP/CSS/JS changes to start — the highest-leverage P0 items are wp-admin config (Rank Math) and content (new pages), which don't touch Codex's frontend work at all.

## 2. Technical SEO status

| Area | Status | Evidence | Priority |
|---|---|---|---|
| Indexability | GOOD | `meta robots: follow, index` confirmed live; robots.txt only disallows `/wp-admin/` | - |
| robots.txt | GOOD | Verified live: disallows `/wp-admin/`, allows `admin-ajax.php`, has `Sitemap:` line | - |
| Sitemap | GOOD | `sitemap_index.xml` valid, Rank Math-generated, lists post/page/mailpoet_page/category sitemaps | - |
| Canonical | GOOD | Self-referencing canonical confirmed on homepage | - |
| Title tags | GOOD | Homepage title verified live, descriptive, unique | - |
| Meta descriptions | GOOD | Present and descriptive on homepage (Rank Math-generated) | - |
| Open Graph | ISSUE | `og:title/description/type/url/locale` present; `og:image` confirmed missing | P0 |
| Twitter/X metadata | ISSUE | `twitter:card/title/description` present; `twitter:image` confirmed missing | P0 |
| Rank Math involvement | GOOD | Confirmed active — sitemap footer credits it, all meta/canonical/sitemap behavior matches its defaults | - |
| Organization schema | UNKNOWN | Not present in theme code (theme injects no JSON-LD); live `<script>` output not verifiable with available tools this session | P1 (verify) |
| Article schema | UNKNOWN | Same limitation as above | P1 (verify) |
| Breadcrumb schema | UNKNOWN | No breadcrumb markup found in theme templates (category.php, tag.php, single.php); Rank Math may still inject schema-only breadcrumbs even without visible UI | P2 |
| Duplicate/conflicting schema | UNKNOWN | Cannot audit without live head access | P2 (verify once tooling allows) |
| Heading hierarchy | GOOD | Homepage: single H1 -> H2 (BTC Daily Intelligence) -> H2 (BIG STORIES) -> H3 per card, verified live. Archive templates (category/tag) also single H1 | - |
| Article date/author semantics | ISSUE | single.php date now uses time-datetime in the pending frontend PR (not yet merged); no author byline anywhere in the theme | P1 |
| Image alt behavior | GOOD (code) | content-card.php sets real alt from post title when a featured image exists; decorative SVG fallback correctly uses empty alt (title is already visible as real text). Live per-post alt values depend on whether posts have featured images set -- content issue, not a template bug | - |
| Category/tag/archive indexability | GOOD | No theme-level noindex found; nothing blocks these in robots.txt | - |
| Pagination | GOOD | paginate_links() implemented on category.php, tag.php (both branches), home.php | - |
| Duplicate archive risk | ISSUE (minor) | /tag/big-stories/ archive duplicates the homepage's BIG STORIES teaser list. Low real-world risk (excerpts only, each article's own URL is canonical) | P2 |
| Internal linking structure | ISSUE | Only nav + in-grid links exist; footer had zero links until the pending frontend PR; no contextual cross-links between articles/categories | P1 |
| Orphan-page risk | UNKNOWN | Can't inventory all published posts without wp-admin/DB access; structurally, any post not tagged into a nav-visible category/tag is only reachable via the paginated archive or homepage grid, which is thin | P2 |
| BTC/AI content crawlability | ISSUE | BTC Daily Intelligence has no URL of its own -- nothing there for Google to crawl or rank | P0 |

## 3. Recommended site architecture

Kept close to the current nav (Tren AI, Berita, Riset, Analisis Koin) rather than replaced -- changing category slugs now would break existing indexed URLs for no benefit.

```
Home
├── Bitcoin Intelligence   (NEW -- hub page, see §4)
├── Analisis Koin          (existing category -- BTC/crypto analysis articles)
├── Tren AI                (existing category -- AI x crypto content lives here)
├── Riset                  (existing category -- deeper research pieces)
├── Berita                 (existing category -- general news, lowest monetization intent)
└── Learn (evergreen)      (NEW -- only if/when educational content is written; do not create empty)
```

- Primary nav: add "Bitcoin Intelligence" as a new nav item pointing at the evergreen hub (§4). Don't add "Learn" until there's at least 3-4 articles to put in it -- an empty nav item is worse than no nav item.
- big-stories tag: keep as an internal curation tool, but consider adding noindex to that one tag archive via Rank Math's per-term settings once verified (it duplicates the homepage; not urgent, P2).
- Breadcrumbs: add breadcrumb display (Rank Math has this built in, template just needs to call rank_math_the_breadcrumbs()) on category/tag/single templates. Helps both users and Google understand hierarchy. Requires a small template change -- flag for Codex or a future PR, not done here.
- Internal-linking rule going forward: every new BTC Intelligence entry should link to the evergreen hub; every AI/crypto article should link to at least one relevant category page. No automated cross-linking plugin needed at this scale.

## 4. BTC Daily Intelligence SEO architecture

**Recommended model: C -- evergreen hub + selected dated analyses.**

- **Hub URL**: /analisis-bitcoin-hari-ini/ (or similar) -- a single evergreen page, continuously updated to reflect the current/latest BTC record (today's direction, confidence, range, key drivers -- the same data already in data/btc-daily-sample.json). This is the URL that accumulates backlinks, historical rankings, and trust over time. It's also the URL every X/social post should link to.
- **Dated analyses**: only publish a standalone dated post (e.g. /analisis-bitcoin-15-agustus-2026/) when that day's call is genuinely notable (high-confidence signal, major invalidation event, something worth citing later) -- not every single day. This avoids the index-bloat and near-duplicate-content risk of a new near-identical page every 24 hours.
- **Why not A (pure evergreen only)**: loses the ability to ever point to "what we said on a specific day," which matters for building a public track record -- a real trust asset for a financial-content site.
- **Why not B (one new URL every day)**: guaranteed keyword cannibalization (many pages all targeting "bitcoin hari ini" variants competing with each other), index bloat, and unsustainable content-ops for a solo founder maintaining this by hand.
- **Old daily analyses**: once a dated post ages out (Bitmomo has no track-record page yet), don't delete it -- link it from a simple "Riwayat Analisis" (analysis history) section on the hub page, newest first. This turns old calls into supporting evidence for the hub's authority instead of orphaned pages. Never 404 or noindex old correct analyses; only correct/retract via a visible update note if a call needs correction (ties to Corrections Policy in §6).
- **Maintainability**: this model needs exactly one recurring action (update the hub) plus occasional judgment calls (worth a dated post or not) -- matches "solo founder, low engineering complexity" constraint better than any daily-generation model.

## 5. Content clusters

Only clusters that plausibly justify a standalone page right now -- most of the brief's example list stays unbuilt.

| Cluster | Search intent | Recommended page | Internal-link destination | Monetization relevance |
|---|---|---|---|---|
| Bitcoin daily outlook (analisis/prediksi/outlook bitcoin hari ini) | High-intent, near-transactional | The evergreen hub (§4) | Homepage BTC card <-> hub (bidirectional) | High -- hub is the natural home for the RedotPay CTA |
| Support/resistance & signal terms (support resistance bitcoin, sinyal bitcoin) | High-intent, same searcher as above | Fold into the hub as a section, not a separate page | Hub | High -- same page as above |
| AI x Crypto overview (AI crypto, crypto AI, proyek AI crypto) | Medium-intent, research/awareness | One evergreen "AI x Crypto" explainer page under Tren AI | Tren AI category, links out to relevant articles as they're published | Medium -- softer CTA (see §7) |
| Bittensor/TAO, AI agents crypto | Narrow, currently low search volume for an Indonesian audience specifically | Do NOT build standalone pages yet -- cover as regular articles under Tren AI/Riset until there's evidence of real search demand | Tren AI | Low for now |
| Educational/evergreen (what is BTC, how does crypto trading work) | High volume, low immediate intent | Defer -- only start once Bitcoin Intelligence hub + AI x Crypto page are live and validated | Would feed the hub eventually | Low direct, but builds topical authority |

Explicitly avoided: separate pages per keyword variant ("harga bitcoin hari ini" vs "analisis bitcoin hari ini" vs "prediksi bitcoin hari ini") -- these are the same search intent and would cannibalize each other. One hub page should target all of them.

## 6. Trust / E-E-A-T gaps

Content guidance only -- the footer links themselves (slugs tentang-kami, kebijakan-privasi, disclaimer) are already coded in the pending frontend PR; these pages don't exist in wp-admin yet.

- **Tentang Kami (About)**: who runs Bitmomo, why it exists, what "AI-powered crypto intelligence" means in practice, and -- critically for trust -- an explicit statement that BTC Daily Intelligence is currently manually/example data, not a track-recorded automated signal (matches the disclosure already written into the BTC card).
- **Kebijakan Privasi (Privacy Policy)**: standard WordPress/analytics data-collection disclosure (Site Kit/Google Analytics is in use). Can be a fairly standard template -- low SEO risk either way, but required for trust and often for ad/affiliate network compliance.
- **Disclaimer**: expand the existing BTC-card disclaimer copy into a full page -- not financial advice, no historical accuracy track record yet, crypto risk warning, affiliate relationship disclosure (RedotPay).
- **Missing entirely, not yet planned anywhere**:
  - Author identity/bio -- even a single shared "Bitmomo Team" byline with a short bio beats no attribution at all.
  - Methodology page for BTC Intelligence -- explains how the direction/confidence/range figures are derived (currently manual; say so plainly). This single page does a lot of E-E-A-T work for a finance-adjacent site.
  - Corrections policy -- one paragraph is enough: how/when a wrong call gets flagged and updated.
  - Contact method -- an email address is sufficient; doesn't need a full contact form.
  - Source citations -- when BTC Intelligence cites specific data (ETF flows, funding rates), link the source where practical.
  - Timestamp/update policy -- already partially solved (BTC card shows "Diperbarui X hari lalu"); extend the same convention to the evergreen hub once built.

## 7. Conversion SEO rules

| Content type | Search intent | Ideal CTA | CTA location | Affiliate CTA appropriate? |
|---|---|---|---|---|
| BTC Daily Intelligence hub/dated posts | High-intent, near-transactional | RedotPay card CTA (existing) | End of the analysis, after the actual insight -- same placement pattern as the current homepage card | Yes -- highest-fit page for it |
| AI x Crypto / Tren AI articles | Research/awareness | Soft, contextual -- link to the BTC hub or a relevant tool/wallet mention only if genuinely relevant to that article's topic | Inline, only if contextually earned, not appended by default | Situational -- not by default |
| Riset (deeper research pieces) | Research/awareness, higher trust bar | Soft contextual CTA at most; prioritize credibility over conversion here | End of article, low-pressure | Rarely |
| Berita (general news) | Low purchase intent, informational | No affiliate CTA | N/A | No |
| Learn/educational (once built) | Top-of-funnel, low intent | Internal link to the BTC hub or AI x Crypto page, not an affiliate link | Inline | No |

Rule of thumb carried through: affiliate CTA density should track search intent, not page count -- one well-placed CTA on the hub outperforms (and is more trustworthy than) CTAs scattered across every article.

## 8. Measurement plan

Minimum stack -- nothing paid, using only what's already confirmed present (WordPress, Rank Math, Site Kit, native theme CTA click tracking).

- **Google Search Console** (via Site Kit, already indicated as installed): impressions, clicks, and query data per landing page -- this is the only available source for the "Google impression -> click" half of the funnel; nothing else in the current stack measures this.
- **GA4** (via Site Kit): landing page -> engagement, and event tracking for outbound clicks if Site Kit's enhanced measurement is enabled (verify, don't assume).
- **Native bitmomo_cta_clicks option** (already built, per-day counts by CTA key): the "landing page -> affiliate CTA click" half. No new tooling needed -- just needs the small admin-readable view that's already a known open item from the earlier engineering audit.
- **RedotPay's own referral dashboard** (if they provide one): the only way to close the loop to "eventual referral conversion" -- Bitmomo's own stack can't see past the click without it. Worth confirming with RedotPay what reporting they expose.

**Minimum useful KPIs**: (1) GSC impressions/clicks for the BTC hub URL specifically, once it exists -- proxy for "is anyone finding this"; (2) CTA click-through rate = bitmomo_cta_clicks / homepage (and later, hub) pageviews from GA4 -- proxy for "is the funnel converting"; (3) RedotPay-reported signups attributed to the referral link, if available -- the only true revenue signal.

## 9. 30-day SEO execution backlog

**P0** (crawling/indexing, trust, traffic quality, conversion)
- Set Rank Math default og:image/twitter:image (wp-admin config, no code).
- Create the three pending trust pages (Tentang Kami, Kebijakan Privasi, Disclaimer) using §6 content guidance, once the footer PR is merged/deployed.
- Publish the BTC Daily Intelligence evergreen hub page (§4) -- the single highest-leverage item, since it's currently the entire missing link between "X/Google traffic" and "useful BTC intelligence" in the funnel.

**P1**
- Verify Organization/Article schema is actually present in Rank Math's live output (Google Rich Results Test) -- currently UNKNOWN, not ISSUE.
- Add author byline (even a shared "Bitmomo Team" byline) to single.php.
- Add a couple of footer/nav internal links toward the new BTC hub once it exists.

**P2**
- Consider noindex on the /tag/big-stories/ archive to avoid the homepage duplicate (low priority, low risk either way).
- Add breadcrumb display (rank_math_the_breadcrumbs()) to category/tag/single templates -- small template change, hand off to Codex/frontend track rather than doing it here.
- Build the "AI x Crypto" evergreen explainer page once 2-3 supporting articles exist under Tren AI.

## 10. EXACT NEXT 3 ACTIONS

1. **Set Rank Math's default social share image.** Expected impact: fixes broken/blank link previews for every X post that drives traffic to bitmomo.id -- directly serves the "X -> bitmomo.id" first funnel step. Effort: SMALL. Requires code: No (wp-admin setting only). Conflict with Codex: None -- pure Rank Math config, doesn't touch theme files or PR #15.

2. **Publish the BTC Daily Intelligence evergreen hub page** (/analisis-bitcoin-hari-ini/ or similar) using existing BTC record data as the initial content. Expected impact: the single missing piece connecting search/social traffic to indexable, rankable BTC content -- everything else in this plan depends on this page existing. Effort: MEDIUM (content creation as a normal WP page/post, no template changes required to start -- can launch as a standard post before any custom template work). Requires code: No, to start. Conflict with Codex: None -- new content, not a theme file.

3. **Draft and publish Tentang Kami / Kebijakan Privasi / Disclaimer page content** (§6) as soon as the footer-links PR is merged and those slugs go live. Expected impact: closes the biggest trust gap on a finance-adjacent site, directly supports the "visitor trust -> affiliate CTA" funnel step. Effort: SMALL. Requires code: No (content only; the links themselves are already coded). Conflict with Codex: Low -- purely additive wp-admin content once their footer PR lands; no reason to wait beyond that merge.
