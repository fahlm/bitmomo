# TradingView setup — Bitmomo Market Engine v1

## Chart

1. Open a standard-candle BTC perpetual chart, preferably the Binance BTCUSDT perpetual contract.
2. Set the chart timeframe to 1 hour.
3. Open Pine Editor, paste `bitmomo-market-engine-v1.pine`, save, and add it to the chart.
4. Do not use Heikin Ashi, Renko, or another synthetic chart type for the alert source.

## Derivatives data

The script does not guess service-symbol names.

1. Add TradingView's **Funding rate** indicator and note the service symbol shown by TradingView.
2. Select the same service symbol in **Funding-rate service symbol** and enable funding.
3. Add TradingView's **Open Interest** indicator and note its service symbol.
4. Select it in **Open-interest service symbol** and enable open interest.
5. Verify the funding scale. The webhook expects a decimal rate: `0.0001` means `0.01%`.

When a derivatives series is not configured, its axis is sent as neutral with `data_status: unavailable`.

## Alert

1. Create an alert for **Bitmomo Market Engine v1**.
2. Select **Any alert() function call** as the condition.
3. Set the webhook URL to the private staging endpoint. Never paste the URL into source code or GitHub.
4. Set the alert to trigger once per bar close.
5. Leave the message field unchanged; the script builds the JSON message.

The script fires on the confirmed 1H bar ending at 19:00 WIB. The WordPress workflow creates a draft only, ready for editorial processing around 19:10 WIB.

## Safety checks

- Confirm the alert points to staging before enabling it.
- Confirm the latest generated Bitcoin Signal remains a draft.
- Compare DMI, ATR, funding, basis, and OI values against the chart before trusting scheduled output.
- Rotate the webhook token if the URL is exposed.

