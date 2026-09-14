# Next Chat Bootstrap Prompt

Copy/paste the following as the first message in a fresh ChatGPT conversation:

---

Lanjutkan proyek **Bitmomo Hyperliquid referral-volume research** sebagai lead researcher/engineer. Jangan mulai riset dari nol.

Canonical branch: `research/hyperliquid-referral-volume-v0` di `fahlm/bitmomo`.
Local project: `~/bitmomo-hl-volume-bot`.

**First action:** baca `docs/research/hyperliquid-volume/CURRENT_HANDOFF.md` pada canonical branch dan jadikan itu source-of-truth utama sebelum memberi rekomendasi atau mengubah kode. Baca juga `SESSION3_VERDICT.md` untuk frozen validation result terakhir.

Objective: dengan modal sekitar $100 USDC, cari cara menghasilkan genuine Hyperliquid trading volume menuju referral eligibility $10K dengan expected cost serendah mungkin, idealnya profitable, tanpa wash trading, self-trading, spoofing, atau manipulasi volume.

Rules:

- failed hypotheses tetap documented;
- jangan tune rule menggunakan validation session yang sama;
- unknown metrics tetap UNKNOWN;
- no qualified market => IDLE;
- jangan live trade/mainnet sebelum fresh unseen validation dan risk/reconciliation gates lolos;
- no LLM as execution decision-maker;
- one execution authority only;
- raw JSONL/local datasets jangan diasumsikan ada di GitHub.

Current high-level state:

- standalone HH/HL/BOS/retest alpha rejected OOS;
- fade variant rejected on second holdout;
- candle order-flow proxy rejected;
- VVV frozen 8h unseen holdout failed: RT 94, maker 76.1%, T10K 4.3h, P10K -$6.42, DD $12.07;
- VVV state-aware exit diagnostic on SEEN data improved only slightly and still failed economics;
- dynamic market selector/supervisor exists and is fail-closed;
- resilient websocket reconnect with generation fencing works;
- dual-window selector is active: market state 15m, execution evidence 4h/latest 100, freshness 30m;
- **Session 3 is FROZEN** as `NO QUALIFIED MARKET / IDLE`;
- final Session 3 checkpoint had PONS and VVV `DEGRADED`, ETHFI/PUMP/BTC `REJECT`, healthy feed, supervisor IDLE;
- PONS: maker 65%, markout -9.38 bp, P10K -$10.09;
- VVV: maker 70%, markout -9.54 bp, P10K -$8.19;
- Session 3 validated the dual-window architecture but exposed a strong passive-entry adverse-selection problem;
- post-restart T10K can be temporarily biased because historical execution volume is rehydrated into an observer with a new `started_ms`; fix this before relying on rehydrated throughput metrics.

Do **not** continue tuning Session 3. It is now SEEN data.

Next work:

1. fix restart-safe volume/hour + T10K accounting with tests;
2. investigate adverse selection at passive entry using SEEN diagnostic/replay evidence;
3. pre-declare a small entry-policy candidate set without weakening selector thresholds;
4. select at most one candidate on SEEN data;
5. freeze it before a fresh unseen Session 4;
6. remain IDLE if economics still fail.

---
