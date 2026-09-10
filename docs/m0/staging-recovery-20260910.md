# M0 staging recovery — 2026-09-10

Status: BLOCKED before deployment tooling and server writes.

Canonical M0 gates are the user's external Engineering roadmap staging → production and explicit task gate list; no M1+ work.

Baseline: 13528d351e082d5de26481e8f2c076d9acff4307.
Branch: chore/m0-staging-recovery-20260910.

## Verified access

Existing signed-in Hostinger hPanel session opened the domain-scoped File Manager for seagreen-snail-158456.hostingersite.com, account u689746960. The selected site's document root is public_html in this domain-scoped file browser; absolute OS path remains unverified. SSH panel reports 46.202.138.170 port 65002, user u689746960, status INACTIVE. SSH was not enabled. Local ~/.ssh/config is absent; querying the existing SSH agent was denied by the sandbox, so its identities remain unknown.

## Read-only recovery

Downloaded the four live Bitmomo plugin directories and bitmomo-child-v3 via the domain-scoped File Manager on 2026-09-10 around 15:51 Asia/Jakarta. ZIP entries are relative to each selected component; comparison prefixes each entry with its exact component path. Inventoried 105 files: 76 match baseline Git blob hashes, 21 modified source files, 5 new source files, 3 extra .DS_Store metadata files. The 26 new/modified source files are recovered byte-for-byte, verified by Git blob SHA against the downloaded bytes. No code was edited. A heuristic secret scan of those 26 files found no candidates; no wp-config, database, uploads or credentials were downloaded or committed.

Snapshot source includes staging product behavior; this recovery does not approve or introduce new product semantics. Existing repository tests remain in git. No file entry under tests/ appears in any of the four live plugin ZIP snapshots; absence of empty directories and public URL reachability have not been independently checked.

Three .DS_Store files were classified as macOS metadata and retained in local raw archives, not canonical source. No server files were deleted. The full original eight-artifact list has not been reconciled. Top-level wp-content/plugins-old and themes-old were observed and left untouched. No full recursive inventory outside the five selected components has been completed.

## Open gates

- Two clean-checkout deployments, second no drift: NOT RUN.
- Deliberate server hand edit aborts deployment: NOT RUN.
- Eight stray staging artifacts removed: NOT DONE / not fully classified.
- tests/ absent from all four live plugins: no test files in downloaded snapshots; full gate verification pending.
- Clean dry run extras=0: NOT RUN. Three .DS_Store files currently observed in the five components.

## Required access to continue

An authenticated automation-capable SSH/SFTP route is needed to implement and prove the reproducible deployment gates. Preferred: SSH config alias bitmomo-staging in ~/.ssh/config with HostName 46.202.138.170, Port 65002, User u689746960 and an approved key via SSH agent or a private key file outside git. SSH is currently INACTIVE in hPanel; enabling account-level SSH or adding keys requires user action/approval. Confirm the absolute staging root after connection; never infer or target production. Existing browser file access remains usable for read-only inventory but does not provide a shell/deployment transport to this task.

## Recovery / rollback

This commit preserves all baseline files and overlays only recovered source. No baseline tests or other files are deleted. Raw component ZIPs are retained locally in task work/. Source recovery can be checked by comparing every inventory Git blob SHA to the recovery tree, excluding the three .DS_Store artifacts. No deployment, database mutation, cache purge or server rollback was performed. Resume with a fresh read-only inventory because staging can change after snapshot capture.

PRODUCTION CHANGED: NO
