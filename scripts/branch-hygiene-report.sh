#!/usr/bin/env bash
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

REMOTE="${BITMOMO_REMOTE:-origin}"
MAIN_BRANCH="${BITMOMO_MAIN_BRANCH:-main}"
STALE_DAYS="${BITMOMO_BRANCH_STALE_DAYS:-14}"

command -v git >/dev/null 2>&1 || { echo "ERROR git is required" >&2; exit 2; }
command -v python3 >/dev/null 2>&1 || { echo "ERROR python3 is required" >&2; exit 2; }
[[ "$STALE_DAYS" =~ ^[0-9]+$ ]] || { echo "ERROR BITMOMO_BRANCH_STALE_DAYS must be an integer" >&2; exit 2; }

git remote get-url "$REMOTE" >/dev/null 2>&1 || { echo "ERROR unknown remote: $REMOTE" >&2; exit 2; }
git fetch --prune "$REMOTE" >/dev/null

git rev-parse --verify "$REMOTE/$MAIN_BRANCH" >/dev/null 2>&1 || {
  echo "ERROR missing $REMOTE/$MAIN_BRANCH after fetch" >&2
  exit 2
}

now="$(python3 - <<'PY'
import time
print(int(time.time()))
PY
)"

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT

printf 'branch\ttip_sha\tage_days\tmerged_into_main\tclass\tcleanup_candidate\n' > "$tmp"

while read -r ref sha epoch; do
  branch="${ref#refs/remotes/${REMOTE}/}"
  [ "$branch" = "HEAD" ] && continue

  age_days=$(( (now - epoch) / 86400 ))
  [ "$age_days" -lt 0 ] && age_days=0

  merged="no"
  if git merge-base --is-ancestor "$sha" "$REMOTE/$MAIN_BRANCH" 2>/dev/null; then
    merged="yes"
  fi

  class="working"
  case "$branch" in
    "$MAIN_BRANCH") class="trunk" ;;
    release/*) class="release" ;;
    rc-*) class="rc" ;;
    archive/*) class="archive" ;;
    chatgpt/*|claude/*|codex/*) class="legacy-tool" ;;
  esac

  cleanup="no"
  case "$class" in
    trunk|release|rc) ;;
    *)
      if [ "$merged" = "yes" ] && [ "$age_days" -ge "$STALE_DAYS" ]; then
        cleanup="review"
      fi
      ;;
  esac

  printf '%s\t%s\t%s\t%s\t%s\t%s\n' "$branch" "$sha" "$age_days" "$merged" "$class" "$cleanup" >> "$tmp"
done < <(
  git for-each-ref \
    --format='%(refname) %(objectname) %(committerdate:unix)' \
    "refs/remotes/${REMOTE}/" | sort
)

python3 - "$tmp" <<'PY'
import csv, sys
path=sys.argv[1]
rows=list(csv.DictReader(open(path, encoding='utf-8'), delimiter='\t'))
rows.sort(key=lambda r:(r['cleanup_candidate']!='review', -int(r['age_days']), r['branch']))
headers=['branch','age_days','merged_into_main','class','cleanup_candidate','tip_sha']
width={h:max(len(h), *(len(r[h]) for r in rows)) if rows else len(h) for h in headers}
print('  '.join(h.ljust(width[h]) for h in headers))
print('  '.join('-'*width[h] for h in headers))
for r in rows:
    print('  '.join(r[h].ljust(width[h]) for h in headers))
print()
print(f"remote_branches={len(rows)}")
print(f"cleanup_review_candidates={sum(r['cleanup_candidate']=='review' for r in rows)}")
print('NOTE cleanup_candidate=review is non-destructive guidance only.')
print('Before deleting any ref, confirm it is not an open PR head, active dependency, release/RC authority, or intentionally preserved research branch.')
PY
