# Next Chat Bootstrap Prompt

Copy/paste the following as the first message in a fresh ChatGPT conversation:

---

Lanjutkan proyek **Bitmomo Hyperliquid referral-volume research** sebagai lead researcher/engineer. Jangan mulai riset dari nol.

Canonical branch: `research/hyperliquid-referral-volume-v0` di `fahlm/bitmomo`.
Local project: `~/bitmomo-hl-volume-bot`.

**First action:** baca `docs/research/hyperliquid-volume/CURRENT_HANDOFF.md` pada canonical branch dan jadikan itu source-of-truth utama sebelum memberi rekomendasi atau mengubah kode.

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
- Session 1/2 are SEEN diagnostic;
- dual-window fix is active: market state 15m, execution evidence 4h/latest 100, freshness 30m;
- Session 3 is the fresh validation session and its thresholds must not be tuned before verdict freeze;
- latest checkpoint still had no qualified market.

Continue from Session 3. Inspect the current evidence, decide whether Session 3 should continue or be frozen, document the verdict, then only afterward propose the next research iteration.

---
