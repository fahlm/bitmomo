# M1 production rescue archive

The production rescue payload is split into six base64 text parts because this environment could not push directly with git credentials.

To reconstruct locally from the repository root:

```bash
cat docs/m1/production-rescue/archive/production-rescue-20260910.tar.gz.b64.part-* > /tmp/production-rescue-20260910.tar.gz.b64
base64 -d /tmp/production-rescue-20260910.tar.gz.b64 > /tmp/production-rescue-20260910.tar.gz
tar -tzf /tmp/production-rescue-20260910.tar.gz
```

The archive contains the 18 drifted production files under `website/`, plus the rescue README. It is evidence only; it is not a canonical merge decision.

PRODUCTION CHANGED: NO
