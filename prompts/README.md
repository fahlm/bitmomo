# Bitmomo Prompt System

This directory will contain reusable AI prompts, output schemas, evaluation criteria, and agent instructions used across Bitmomo.

## Planned areas

- `research/` — research and fact-checking workflows.
- `content/` — scripts, articles, Shorts, and editorial workflows.
- `twitter/` — X post, thread, and engagement workflows.
- `btc-analyst/` — Bitcoin market-analysis agents, schemas, scoring, and consensus logic.

## Prompt design principles

Every production prompt should define:

1. Role and objective.
2. Required inputs and data freshness.
3. Explicit constraints.
4. Structured output schema.
5. Evaluation criteria.
6. Failure and uncertainty handling.
7. Version and change history.

Prompts should be treated as version-controlled production assets, not disposable chat instructions.
