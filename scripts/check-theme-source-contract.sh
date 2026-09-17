#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "${repo_root}"

theme_dir="website/wp-content/themes/bitmomo-child-v3"
asset_trait="${theme_dir}/inc/trait-bitmomo-assets.php"
frontend_trait="${theme_dir}/inc/trait-bitmomo-frontend.php"
account="website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-account.php"

expected=(
  "functions.php" "custom.css" "front-page.php" "page.php" "single.php" "home.php"
  "archive.php" "category.php" "tag.php" "search.php" "404.php" "index.php"
  "header.php" "footer.php" "style.css" "assets/css/design-system.css"
  "assets/css/home-opportunity.css" "assets/css/public-surfaces.css"
  "assets/css/navigation-footer.css" "assets/css/public-readability.css"
  "assets/css/article-reading.css" "template-parts/archive-index.php"
)

for file in "${expected[@]}"; do
  test -f "${theme_dir}/${file}" || {
    echo "::error file=${theme_dir}/${file}::Required public-surface file is missing"
    exit 1
  }
done

test -f "docs/PUBLIC_SURFACE_CONTRACT.md" || {
  echo "::error::Public surface contract is missing"
  exit 1
}

! grep -q "Informasi AI &" "${theme_dir}/home.php"
! grep -q "BITMOMO RESEARCH" "${theme_dir}/home.php"
! grep -q "big-stories" "${theme_dir}/tag.php"

for file in home.php archive.php category.php tag.php search.php index.php; do
  grep -q "template-parts/archive" "${theme_dir}/${file}" || {
    echo "::error file=${theme_dir}/${file}::Archive route must consume the shared archive surface"
    exit 1
  }
done

grep -q "template-parts/whitelist" "${theme_dir}/front-page.php"
grep -q 'id="primary"' "${theme_dir}/front-page.php"
! grep -q "template-parts/pro.*teaser" "${theme_dir}/front-page.php"
! grep -q "bm-howworks-future" "${theme_dir}/template-parts/how-it-works.php"
! grep -q "public-surfaces.css" "${theme_dir}/header.php"
grep -q "design-system.css" "${asset_trait}"
grep -q "public-surfaces.css" "${asset_trait}"
grep -q "navigation-footer.css" "${asset_trait}"
grep -q "home-conversion.css" "${asset_trait}"

test ! -f "${theme_dir}/template-parts/pro-teaser.php"
test ! -f "${theme_dir}/template-parts/why-bitmomo.php"
test ! -f "${theme_dir}/assets/images/bitmomo-logo.png"

grep -q "<h1 class=\"bm-pro-account__title\"" "${account}" || {
  echo "::error file=${account}::Pro account product surface must render its own H1"
  exit 1
}

grep -q "bitmomo_post_research_classification" "${theme_dir}/single.php"
! grep -q "Riset Terkait" "${theme_dir}/single.php"

grep -q "bitmomo_hide_anonymous_rest_user_routes" "${frontend_trait}" || {
  echo "::error file=${frontend_trait}::Anonymous REST user-enumeration boundary is missing"
  exit 1
}
grep -q "is_user_logged_in()" "${frontend_trait}"
grep -q "'/wp/v2/users'" "${frontend_trait}"
grep -q "add_filter('rest_endpoints'" "${frontend_trait}"

echo "PASS canonical theme source contract"
