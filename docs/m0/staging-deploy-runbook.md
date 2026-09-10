# M0 staging deploy runbook

This runbook is limited to the staging site `seagreen-snail-158456.hostingersite.com`.
Production is out of scope.

## Managed scope

Only these paths are managed:

- `wp-content/plugins/bitmomo-ai`
- `wp-content/plugins/bitmomo-btc-intelligence`
- `wp-content/plugins/bitmomo-pro`
- `wp-content/plugins/bitmomo-regime`
- `wp-content/themes/bitmomo-child-v3`

The deployable source is `website/` in git. Plugin `tests/` directories and
`.DS_Store` files are intentionally excluded from the deploy manifest.

## Required local access

Create a local SSH config alias outside the repo:

```sshconfig
Host bitmomo-staging
  HostName 46.202.138.170
  Port 65002
  User u689746960
  IdentityFile ~/.ssh/<approved-staging-key>
  IdentitiesOnly yes
```

Set the verified absolute staging root before running deploy commands:

```bash
export BITMOMO_STAGING_HOST=bitmomo-staging
export BITMOMO_STAGING_SITE=seagreen-snail-158456.hostingersite.com
export BITMOMO_STAGING_ROOT=/absolute/path/to/public_html
```

## Commands

Generate the local manifest:

```bash
python3 scripts/m0-staging-deploy.py manifest
```

Read-only remote comparison:

```bash
python3 scripts/m0-staging-deploy.py dry-run
```

First staging deploy after recovery:

```bash
python3 scripts/m0-staging-deploy.py deploy --bootstrap
```

Normal staging deploy:

```bash
python3 scripts/m0-staging-deploy.py deploy
```

## Safety behavior

The script aborts before deployment if a managed remote file differs from the
last deployed manifest. This is the M0 hand-edit protection: a manual edit on
the server changes the remote hash, so the next deploy fails instead of
silently overwriting it.

Extras are not deleted unless they are listed in
`config/m0-classified-extras.json` with `decision: delete`. Any unexplained
extra causes a non-zero dry run and deploy abort.

## M0 proof sequence

1. Verify SSH target and absolute root.
2. Run `dry-run` and classify every extra.
3. Run `deploy --bootstrap` to remove only classified extras and write the
   first remote manifest.
4. Run `deploy`; it should report no missing, changed, drift, or extras.
5. Create one deliberate hand edit on staging inside a managed canonical file.
6. Run `deploy`; it must abort with drift.
7. Restore the file from git using the same script or a classified manual
   recovery, then run two consecutive deploys from a clean checkout. The second
   must report no drift.
