# Bitmomo Research Authoring Guide V1

**Audience:** Bitmomo analysts, researchers, editors, and reviewers  
**Status:** Canonical editorial operating guide for new Research publications  
**Applies to:** Market Research and Intelligence Systems Research  

## 1. Purpose

Bitmomo Research is an authority surface, not a generic blog. Every article classified as institutional research must help a reader understand a claim, the evidence behind it, the limits of that claim, and what would cause the conclusion to be reconsidered.

The core standard is:

> **Evidence before narrative.**

A Research publication is not accepted because it sounds sophisticated. It is accepted because the reasoning can be inspected.

For the Indonesian audience, write in clear professional Bahasa Indonesia. English technical terms may be retained when they are standard in crypto, finance, AI, statistics, or software engineering, but explain the term on first use when a non-specialist Indonesian reader may not understand it.

## 2. Decide first: is this actually Bitmomo Research?

Before writing, classify the intended publication.

### Market Research

Use **Market Research** when the primary object being analyzed is Bitcoin or the crypto market and the article makes a market-related analytical claim.

Typical examples:

- Bitcoin market structure;
- ETF and capital flows;
- derivatives, funding, basis, open interest, positioning;
- liquidity;
- macro conditions relevant to BTC;
- volatility and regime behavior;
- fundamental or market-mechanics research.

### Intelligence Systems Research

Use **Intelligence Systems Research** when the primary object being analyzed is the system used to produce, evaluate, or validate intelligence.

Typical examples:

- whether AI can predict Bitcoin;
- forward validation and backtesting methodology;
- agent architecture;
- model evaluation;
- data provenance;
- failure modes and reliability;
- decentralized AI;
- AI infrastructure;
- model architecture and research;
- AI industry or societal implications when the work is analytical rather than news reporting.

### Not institutional research

Do **not** assign a Research Desk when the article is primarily:

- news reporting;
- announcement;
- opinion without a testable analytical basis;
- generic educational content;
- promotional copy;
- product marketing;
- rewritten third-party reporting;
- speculative narrative without adequate evidence.

If uncertain, keep the post outside institutional Research until editorial review. The system is intentionally fail-closed.

## 3. Research classification rules

Every qualified Research article must have **exactly one Research Desk**.

Allowed desks:

- `Market Research`
- `Intelligence Systems Research`

Never assign both. Never use WordPress category order or arbitrary tags as a substitute for Research Desk classification.

Research Topics are secondary discovery metadata. Use only topics materially covered by the article.

Canonical topics currently include:

- Bitcoin
- Macro
- Market Structure
- Derivatives
- ETF & Flows
- Liquidity
- Fundamentals
- AI Agents
- Evaluation
- Data Provenance
- Decentralized AI
- AI Infrastructure
- Models
- AI Industry & Society

Do not add a new topic merely because a phrase appears once in the article. A topic should represent a meaningful analytical dimension of the work.

## 4. Mandatory article package

Before publication, every Research article must have all of the following.

### A. Title

The title must state the analytical question, mechanism, or finding clearly.

Prefer:

- `Can AI Actually Predict Bitcoin? Designing a Forward-Validated Crypto Intelligence System`
- `Struktur Permintaan ETF dan Implikasinya pada Volatilitas BTC`
- `Membaca Funding Rate Tanpa Terjebak Noise Harian`

Avoid:

- `Bitcoin Akan Meledak!`
- `AI Ini Gila Banget`
- `Rahasia Whale Terbongkar`
- vague titles such as `Analisis Bitcoin Hari Ini` unless the document is genuinely a recurring report with a defined methodology.

Do not use clickbait, certainty theater, or price-target sensationalism.

### B. Manual excerpt / research deck

Always write a manual WordPress excerpt for qualified Research.

The excerpt is used as:

1. the article deck below the title;
2. the preferred search/meta-description source;
3. the Research Hub summary/key finding.

Target: **1–2 concise sentences**. State the analytical result or question, not marketing language.

Good example:

> Model AI dapat menemukan pola yang berguna pada data pasar, tetapi predictive value hanya layak dipercaya setelah diuji secara forward, dibandingkan dengan baseline sederhana, dan dievaluasi pada regime yang berbeda.

Avoid:

> Baca riset terbaru Bitmomo tentang masa depan AI dan Bitcoin.

### C. Research Desk

Assign exactly one canonical Research Desk in the **Bitmomo Research Classification** panel.

### D. Research Topics

Select the smallest useful set of relevant topics. Usually 1–3 topics is enough.

### E. Featured image / research figure

A featured image should support the research, not function as a magazine thumbnail wall.

Preferred:

- one clear chart or analytical visual;
- a restrained conceptual illustration;
- Bitmomo visual identity;
- very little embedded text;
- no fake Bloomberg/Glassnode UI;
- no fabricated data points;
- no visual claim stronger than the article evidence.

If the image contains a chart, the chart must correspond to actual data or be clearly labeled as conceptual/illustrative.

### F. Sources

Every material factual claim that depends on external data must be traceable to a source.

Prefer primary or high-authority sources:

- exchange/API data;
- ETF issuer or regulator data;
- FRED / BLS / central-bank data for macro;
- protocol documentation;
- official model/system papers;
- peer-reviewed research;
- original company technical documentation;
- Bitmomo canonical datasets or validated internal outputs.

Use secondary news sources mainly for context, not as the sole foundation of a quantitative thesis when a primary source exists.

## 5. Canonical research structure

The exact headings may vary, but the logic should normally follow this sequence.

### 1. Executive finding / research question

Open with the problem and the conclusion or hypothesis being tested.

A reader should understand within the first few paragraphs:

- what is being investigated;
- why it matters;
- the central conclusion or unresolved question.

Do not waste the opening on generic history that the target reader already knows.

### 2. Evidence and data

Explain what evidence is used.

Include when relevant:

- source;
- observation period;
- frequency/timeframe;
- units;
- sample size;
- exclusions;
- data quality limitations;
- transformations or derived metrics.

Never describe a dataset as `real-time`, `institutional`, `on-chain`, or `AI-powered` unless that description is literally true and supportable.

### 3. Method / analytical framework

Explain how the evidence is interpreted.

For quantitative research, describe the method enough for a sophisticated reader to understand what was done without exposing secrets that are genuinely proprietary.

For AI/system research, distinguish clearly between:

- training;
- inference;
- backtest;
- validation;
- forward validation;
- live observation;
- human review.

Never call an in-sample result `validated`.

### 4. Findings

Separate observations from interpretation.

Useful language:

- `Data menunjukkan…`
- `Dalam sampel ini…`
- `Hubungan yang terlihat adalah…`
- `Salah satu interpretasi yang konsisten dengan data adalah…`

Avoid converting correlation into causation without evidence.

### 5. Thesis / interpretation

Explain what the findings mean.

A market-research thesis should normally state:

- current mechanism or setup;
- relevant market condition;
- why the evidence matters;
- what the thesis does **not** imply.

An Intelligence Systems paper should state the tested proposition and the performance/reliability boundary.

### 6. Invalidation / failure conditions

Every analytical conclusion needs a boundary.

Ask:

- What new evidence would make this interpretation weaker?
- Which market condition would invalidate the mechanism?
- Which data-quality problem could invalidate the result?
- What alternative explanation remains plausible?

This section is mandatory when the article makes a directional, predictive, causal, or system-performance claim.

### 7. Limitations

State material limitations explicitly.

Examples:

- short sample window;
- survivorship bias;
- API history limits;
- changing market regime;
- limited venue coverage;
- model drift;
- unavailable historical fields;
- small number of macro events;
- lack of transaction-cost modeling;
- selection bias.

Do not bury a limitation that could materially change the conclusion.

### 8. What to monitor next

When useful, close with measurable variables or future evidence that would update the thesis.

This is not a buy/sell instruction. It is a research follow-up framework.

## 6. Market Research-specific rules

For BTC/crypto market articles:

- specify timeframe whenever discussing direction or trend;
- distinguish spot, futures, perpetuals, options, ETF flows, and on-chain data;
- distinguish price movement from flow, positioning, and causality;
- do not call support/resistance an `expected range` unless the method actually estimates a range;
- do not present funding alone as proof of market direction;
- explain whether metrics are absolute, normalized, percentile-based, or z-scored;
- state whether a claim is descriptive, explanatory, or predictive;
- avoid `bullish` / `bearish` labels without explaining the evidence and horizon;
- include transaction costs/slippage when making strategy-performance claims where relevant.

If a forecast or model outcome is discussed, disclose how it was evaluated. Backtest performance must not be presented as live track record.

## 7. Intelligence Systems Research-specific rules

For AI/system articles:

- define the task being evaluated;
- identify the baseline;
- separate predictive accuracy from usefulness to a trader or analyst;
- describe validation design;
- state leakage controls;
- distinguish training/test/forward periods;
- report failure modes as well as successes;
- never imply that using an LLM automatically produces predictive edge;
- do not call a model `AI intelligence` when it is simply deterministic rules;
- label synthetic or simulated results clearly;
- distinguish prototype, shadow-mode, staging, and production behavior.

A Bitmomo internal system may be discussed only to the extent that the claims can be supported by actual implementation or test evidence.

## 8. Writing style for the Indonesian market

Bitmomo should sound like a rigorous Indonesian intelligence platform, not translated English marketing copy.

Prefer natural Bahasa Indonesia:

- `apa yang berubah`
- `batas tesis`
- `hasil pengujian`
- `sumber data`
- `kondisi pasar`
- `bukti`
- `evaluasi`

Avoid awkward literal translations and unnecessary jargon such as:

- `market melakukan pricing in` when `pasar mulai memperhitungkan` is clearer;
- `conviction tinggi` when `keyakinan model lebih tinggi` or a specific metric is clearer;
- `intelligence layer` when the phrase is not technically necessary.

Technical English terms are acceptable when they are the normal vocabulary of the field: `funding rate`, `open interest`, `basis`, `forward validation`, `overfitting`, `data leakage`, `inference`, `benchmark`, and similar terms.

Do not use exaggerated institutional language to compensate for weak evidence. The work itself must create authority.

## 9. Numbers, charts, and visual evidence

Every chart must answer a question.

A chart should have:

- clear title;
- labeled axis where needed;
- time period;
- units;
- source;
- readable legend when multiple series exist;
- explanation in the article body of why the chart matters.

Do not include a chart simply because the page looks empty.

Never:

- truncate an axis to exaggerate a move without clear disclosure;
- mix incompatible units without explanation;
- use percentages without stating the denominator;
- present model output as observed market data;
- remove losing observations from a performance chart;
- cherry-pick only the period that supports the thesis without discussing the selection.

## 10. Citations and source notes

When referencing external research or data, identify the source close to the claim.

For a quantitative figure, use a caption or nearby source note such as:

`Sumber: Farside Investors, Bitmomo Research. Data sampai 15 September 2026.`

For internal derived metrics:

`Sumber: Binance market data; kalkulasi Bitmomo Research.`

For methodology articles, link primary technical references where possible.

Do not copy long passages from external sources. Paraphrase the evidence and retain attribution.

## 11. SEO and search-result promise

SEO must describe the document that actually exists.

For qualified Research:

- title must match the analytical subject;
- manual excerpt should be suitable as a meta description;
- do not stuff keywords;
- do not add `2026`, `hari ini`, or `terbaru` unless the article is genuinely time-bound;
- do not promise a signal, prediction, target, or proprietary dataset that the article does not provide;
- use descriptive H2/H3 headings that improve both scanning and search understanding.

A visitor coming from Google should immediately see on-page the same research identity implied by the search result.

## 12. WordPress publishing workflow

Use this sequence for every qualified Research article.

1. Create the article as **Draft**.
2. Write the title and manual excerpt.
3. Complete the article body and source verification.
4. Add the featured research figure if one materially improves the article.
5. In **Bitmomo Research Classification**, choose exactly one Research Desk.
6. Select only relevant Research Topics.
7. Verify that the article is intended to be institutional Research, not News/Editorial.
8. Review all factual claims, numbers, timestamps, links, and chart labels.
9. Check mobile readability in preview.
10. Check that only one H1 exists—the WordPress post title. Do not insert another H1 inside the article body.
11. Make sure the opening does not duplicate the manual excerpt word-for-word.
12. Send for editorial/research review.
13. Publish only after the reviewer confirms the checklist below.

Do not manually manipulate old tags such as `ai-lab`, `bitcoin`, `etf`, or `funding-rate` in order to force Research classification. Canonical classification belongs to the Bitmomo Research Classification controls.

## 13. Reviewer acceptance checklist

A reviewer should answer **YES** to all applicable items before publication:

- Is the article genuinely research rather than news or marketing?
- Is exactly one Research Desk selected?
- Are the selected topics materially covered?
- Does the title accurately describe the work?
- Is a manual excerpt present?
- Can every important factual/data claim be traced to a source?
- Are the dates/timeframes clear?
- Are observation and interpretation separated?
- Are predictive/causal claims supported by an adequate method?
- Is the invalidation/failure condition explicit when relevant?
- Are important limitations disclosed?
- Are backtest, forward-validation, shadow, and live results labeled correctly?
- Are charts honest, sourced, and readable?
- Does the article avoid clickbait and certainty theater?
- Does the article avoid investment-instruction language?
- Is the Bahasa Indonesia natural and professional?
- Is there exactly one page H1?
- Does the article render cleanly on mobile?
- Does the search-result promise match the article content?

If any material answer is **NO**, return the article to Draft.

## 14. Fast templates

### Market Research template

```text
Title

Manual excerpt / key finding

## Ringkasan temuan
What happened / what is being tested / core conclusion.

## Data dan konteks
Sources, period, timeframe, data quality.

## Apa yang ditunjukkan data
Observed evidence.

## Interpretasi
Mechanism and thesis.

## Apa yang dapat membatalkan tesis ini
Invalidation / alternative explanation.

## Keterbatasan
Method/data limitations.

## Yang perlu dipantau berikutnya
Measurable follow-up variables.

Sources / figure notes as needed.
```

### Intelligence Systems Research template

```text
Title

Manual excerpt / key finding

## Pertanyaan riset
What capability or proposition is being tested?

## Sistem / data yang diuji
Inputs, architecture, dataset, baseline.

## Metode evaluasi
Train/test/forward split, metric, leakage controls.

## Hasil
Observed performance and comparison to baseline.

## Failure modes
Where and why the system fails.

## Apa yang dapat disimpulkan
Bounded interpretation.

## Keterbatasan
Data/model/experimental limitations.

## Langkah validasi berikutnya
What evidence is still required before stronger claims are justified.
```

## 15. Escalation rules

Escalate to the Research/Engineering owner before publication when an article:

- discloses internal model performance;
- describes a production or staging system;
- claims predictive edge;
- compares Bitmomo against named competitors;
- uses unpublished internal datasets;
- contains security-sensitive implementation details;
- presents a new metric that may later be exposed in BTC Intelligence or Pro;
- materially changes the documented Bitmomo methodology.

Editorial review alone is not sufficient for those cases; the technical claim must match the actual system.

## 16. Final principle

Bitmomo should never need to tell readers that it is sophisticated.

The article should demonstrate it through:

**clear questions → traceable evidence → disciplined analysis → explicit limitations → testable conclusions.**
