# Shadow Session 2 Status

Session 2 was stopped manually after the resilient selector accumulated substantial dry-run feedback and demonstrated automatic websocket recovery. The session is now SEEN diagnostic data and must not be used as an independent validation set for selector rules changed afterward.

Key observed behavior:

- reconnect/recovery path worked repeatedly;
- supervisor remained fail-closed/IDLE when no market qualified;
- cumulative shadow feedback greatly exceeded the per-market `ExecN` shown in the 15-minute window;
- this exposed a design flaw: market-state and execution-evidence metrics were sharing the same 15-minute rolling horizon.

The next selector version separates a 15-minute market-state window from a 4-hour / latest-100-attempt execution-evidence window with a 30-minute execution freshness gate.
