#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "${repo_root}"

theme="website/wp-content/themes/bitmomo-child-v3"
helpers="${theme}/inc/template-functions.php"
about="${theme}/template-parts/about-authority.php"
hub="${theme}/template-parts/research-hub.php"
home_research="${theme}/template-parts/research.php"
single="${theme}/single.php"
header="${theme}/header.php"
home="${theme}/home.php"
assets="${theme}/inc/trait-bitmomo-assets.php"
content="${theme}/inc/trait-bitmomo-content.php"

# Institutional classification boundary.
grep -q "template-parts/about" "${theme}/page.php"
grep -q "template-parts/research" "${theme}/category.php"
grep -Eqi "platform .*market intelligence.*(research|riset)" "${about}"
! grep -Eqi "media independen|media independent" "${about}"

grep -q "bitmomo_market_research_taxonomy_slugs" "${helpers}"
grep -q "bitmomo_post_is_market_research" "${helpers}"
grep -q "bitmomo_post_is_ai_systems_research" "${helpers}"
grep -q "bitmomo_post_research_classification" "${helpers}"
grep -q "bitmomo_post_publication_label" "${helpers}"
grep -q "bitmomo_research_focus_filters" "${helpers}"
grep -q "bitmomo_post_matches_research_focus" "${helpers}"
grep -q "bitmomo_post_research_topic_label" "${helpers}"
grep -q "bitmomo_post_reading_minutes" "${helpers}"
grep -q "bitmomo_post_manual_deck" "${helpers}"
grep -q "bitmomo_post_has_meaningful_update" "${helpers}"

grep -q "tag__not_in" "${hub}"
grep -q "bitmomo_post_is_market_research" "${home_research}"
grep -q "bitmomo_post_research_classification" "${single}"
grep -q "bitmomo_post_publication_label" "${single}"
grep -q "Riset Pasar Terkait" "${single}"
grep -q "Riset Sistem Intelligence Terkait" "${single}"
! grep -q "Riset Terkait" "${single}"

grep -q "bitmomo_post_research_classification" "${header}"
! grep -q "is_single() && has_category" "${header}"
grep -q "Arsip Publikasi" "${home}"
! grep -q "BITMOMO RESEARCH" "${home}"

grep -q "design-system.css" "${assets}"
grep -q "home.css" "${assets}"
grep -q "article-reading.css" "${assets}"
grep -q "research.css" "${assets}"
grep -q "about.css" "${assets}"
! grep -q "bm-disclaimer" "${content}"
grep -q "must remain the authored publication itself" "${content}"

python3 - <<'PY'
import json
manifest = json.load(open('config/production-runtime.json', encoding='utf-8'))
components = {c['name']: c for c in manifest.get('components', [])}
total = sum(int(c.get('expected_file_count', 0)) for c in components.values())
expected_total = int(manifest.get('expected_file_count', -1))
assert expected_total > 0
assert expected_total == total
assert 'bitmomo-child-v3' in components
assert 'bitmomo-btc-intelligence' in components
assert 'bitmomo-pro' in components
assert 'assets/css/home.css' in components['bitmomo-child-v3']['required']
assert 'includes/class-bitmomo-btc-intelligence-accountability.php' in components['bitmomo-btc-intelligence']['required']
assert 'includes/class-bitmomo-pro-public-copy.php' in components['bitmomo-pro']['required']
PY

grep -q '"assets/css/design-system.css"' config/production-runtime.json
grep -q '"assets/css/home.css"' config/production-runtime.json
grep -q '"assets/css/article-reading.css"' config/production-runtime.json

# Research Hub UX contract.
css="${theme}/assets/css/research.css"
footer="${theme}/footer.php"
research_contract="docs/RESEARCH_HUB_CONTRACT.md"

grep -q "The Research Hub is a research workspace" "${research_contract}"
grep -q "RESEARCH LIBRARY" "${hub}"
grep -q "RESEARCH PROGRAMS" "${hub}"
grep -q "INTELLIGENCE SYSTEMS" "${hub}"
grep -q "research_q" "${hub}"
grep -Eq "TEMUAN UTAMA|RINGKASAN RISET" "${hub}"
grep -q "RESEARCH FIGURE" "${hub}"
grep -q "bitmomo_post_reading_minutes" "${hub}"
grep -q "bitmomo_post_matches_research_focus" "${hub}"
! grep -q "Gabung Founding Whitelist" "${hub}"
grep -q "Intelligence Systems" "${footer}"

library_line="$(grep -n "RESEARCH LIBRARY" "${hub}" | head -1 | cut -d: -f1)"
method_line="$(grep -n "RESEARCH STANDARD" "${hub}" | tail -1 | cut -d: -f1)"
test "${library_line}" -lt "${method_line}"

grep -q "bm-research-filter" "${css}"
grep -q "bm-research-library__list" "${css}"
grep -q "bm-research-programs__list" "${css}"
grep -q "bm-research-methodology" "${css}"

# Google-to-article reading contract.
article_css="${theme}/assets/css/article-reading.css"
design="${theme}/assets/css/design-system.css"
functions="${theme}/functions.php"
article_contract="docs/ARTICLE_READING_CONTRACT.md"
browser="scripts/check-public-ui.mjs"
cleanliness="scripts/check-article-cleanliness.mjs"

grep -q "Google/search result → article orientation" "${article_contract}"
grep -q "noindex,follow" "${article_contract}"
grep -q "Body cleanliness / legacy database boundary" "${article_contract}"
grep -q "Bitmomo Research" "${single}"
grep -q "Dipublikasikan" "${single}"
grep -q "menit baca" "${single}"
grep -q "RESEARCH STANDARD" "${single}"
grep -q "bm-related--editorial" "${single}"
! grep -q "bm-post-nav" "${single}"

grep -q -- "--bm-reading-width: 720px" "${design}"
grep -q "var(--bm-reading-width)" "${article_css}"
grep -q "font-size: clamp(17px" "${article_css}"
grep -q "bm-article-body > table" "${article_css}"
grep -q "display: block" "${article_css}"
grep -q "overflow-x: auto" "${article_css}"
grep -q "@media (max-width: 720px)" "${article_css}"

grep -q "Bitmomo — Bitcoin Market Intelligence & Research" "${functions}"
grep -q "Bitmomo Research — Bitcoin Markets & Intelligence Systems" "${functions}"
grep -q "bitmomo_should_noindex_public_view" "${functions}"
grep -q "bitmomo_is_unclassified_legacy_research_post" "${functions}"
grep -q "rank_math/frontend/robots" "${functions}"
grep -q "get_post_field('post_excerpt'" "${functions}"
grep -q "define('BM_VERSION', '4.7')" "${functions}"
grep -q "Version: 4.7" "${theme}/style.css"

grep -q "bm-research-lead__title a" "${browser}"
grep -q "no qualified Research article discoverable" "${browser}"
grep -q "reading column too wide" "${browser}"
grep -q "article body font too small" "${browser}"
grep -q "article line-height too tight" "${browser}"
grep -q "generic previous/next post navigation returned" "${browser}"

grep -q "inline typography overrides" "${cleanliness}"
grep -q "Subscribe Newsletter Bitmomo" "${cleanliness}"
grep -q "agi-superintelligence-dan-blockchain" "${cleanliness}"
grep -q "Legacy generic-Riset canary is still indexable" "${cleanliness}"
grep -q "Qualified article title does not carry the Bitmomo Research search promise" "${cleanliness}"

# Single SEO owner and utility indexability.
pro_main="website/wp-content/plugins/bitmomo-pro/bitmomo-pro.php"
btc_main="website/wp-content/plugins/bitmomo-btc-intelligence/bitmomo-btc-intelligence.php"

grep -q "Tentang Bitmomo — Market Research & Intelligence Systems" "${functions}"
grep -q "Bitmomo Research — Bitcoin Markets & Intelligence Systems" "${functions}"
grep -q "Bitmomo — Bitcoin Market Intelligence & Research" "${functions}"
grep -q "bitmomo_should_noindex_public_view" "${functions}"
grep -q "is_search()" "${functions}"
grep -q "is_archive() && !is_category('riset')" "${functions}"
grep -q "rank_math/frontend/title" "${functions}"
grep -q "rank_math/frontend/description" "${functions}"
grep -q "rank_math/frontend/robots" "${functions}"
! grep -q "rank_math/frontend/title" "${helpers}"
! grep -q "rank_math/frontend/description" "${helpers}"

grep -q "remove_filter( 'rank_math/frontend/description'" "${pro_main}"
grep -q "remove_action( 'wp_head'" "${pro_main}"
grep -q "define( 'BITMOMO_PRO_VERSION'" "${pro_main}"
grep -q "remove_filter( 'rank_math/frontend/description'" "${btc_main}"
grep -q "remove_action( 'wp_head'" "${btc_main}"
grep -q "define( 'BITMOMO_BTC_INTELLIGENCE_VERSION'" "${btc_main}"
grep -q "class-bitmomo-btc-intelligence-accountability.php" "${btc_main}"

# Publisher-first source-of-truth regression guard.
grep -q "canonical brand/source-of-truth" docs/content/tentang-kami.md
grep -q "research & intelligence platform" docs/content/tentang-kami.md
grep -q 'Copy publik Bitmomo tidak boleh menggunakan positioning seperti "media independen"' docs/content/tentang-kami.md

echo "PASS canonical authority source contract"
