<?php

// COPY THIS FILE OUTSIDE public_html, for example:
// /home/u123456789/.bitmomo/opportunity-radar.php
// Never commit the populated file.
return [
    // Absolute path to the checked-out/deployed Bitmomo repository on Hostinger.
    'repo_root' => '/home/u123456789/bitmomo-ops',

    // Absolute path to the existing WordPress install. The worker bootstraps it
    // read-only so Research Feed V1 can use the canonical BTC Intelligence state.
    'wordpress_root' => '/home/u123456789/domains/bitmomo.id/public_html',

    // Keep state private and outside public_html.
    'state_path' => '/home/u123456789/.bitmomo/opportunity-radar-state.json',

    // X recent-search credential. Required for live X discovery.
    'x_bearer_token' => 'REPLACE_ME',

    // YouTube is optional in the first live phase. Leave blank to disable it.
    'youtube_api_key' => '',

    // Private Telegram destination for operator alerts.
    'telegram_bot_token' => 'REPLACE_ME',
    'telegram_chat_id' => 'REPLACE_ME',

    // Cost guard. Polling stops for the UTC day once cumulative estimated X
    // Post-read cost reaches this amount. The value is deliberately conservative.
    'daily_x_read_budget_usd' => 1.00,

    // Optional manually curated/original Research Feed source records.
    // The file must contain either an array of manual research source records or
    // {"items": [...]}. Leave blank to use canonical BTC Intelligence only.
    'manual_research_path' => '',

    // Set true for initial validation: discovery and drafting run, Telegram send
    // is replaced by stdout and no alert IDs are marked delivered.
    'dry_run' => true,
];
