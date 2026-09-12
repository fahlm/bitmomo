(function () {
  'use strict';

  var pagePath = (window.location.pathname || '/').replace(/\/+$/, '') || '/';
  if (pagePath !== '/btc-intelligence') return;

  var VISIT_KEY = 'bitmomo_btc_intelligence_last_meaningful_view';
  var MIN_RETURN_MS = 10 * 60 * 1000;

  function queryValue(name) {
    try {
      var value = new URLSearchParams(window.location.search || '').get(name) || '';
      return String(value).slice(0, 120);
    } catch (error) {
      return '';
    }
  }

  function referrerHost() {
    if (!document.referrer) return '';
    try {
      return new URL(document.referrer).hostname.slice(0, 120);
    } catch (error) {
      return '';
    }
  }

  function context() {
    return {
      event_version: 1,
      page_path: pagePath,
      utm_source: queryValue('utm_source'),
      utm_medium: queryValue('utm_medium'),
      utm_campaign: queryValue('utm_campaign'),
      referrer_host: referrerHost()
    };
  }

  function emit(eventName, extra) {
    var payload = context();
    payload.event = eventName;

    if (extra && typeof extra === 'object') {
      Object.keys(extra).forEach(function (key) {
        payload[key] = extra[key];
      });
    }

    if (typeof window.CustomEvent === 'function') {
      window.dispatchEvent(new CustomEvent('bitmomo:analytics', { detail: payload }));
    }

    // Feed an analytics/tag-manager dataLayer only when one already exists.
    // Bitmomo does not create or bind itself to a specific provider here.
    if (Array.isArray(window.dataLayer)) {
      window.dataLayer.push(Object.assign({}, payload));
    }
  }

  function returnWindow(deltaMs) {
    var hours = deltaMs / (60 * 60 * 1000);
    if (hours < 6) return '10m_6h';
    if (hours < 24) return '6h_24h';
    if (hours < 168) return '1d_7d';
    return 'over_7d';
  }

  function trackVisit() {
    var now = Date.now();
    emit('btc_intelligence_view');

    try {
      var previous = parseInt(window.localStorage.getItem(VISIT_KEY) || '', 10);
      if (!previous || !isFinite(previous) || previous > now) {
        window.localStorage.setItem(VISIT_KEY, String(now));
        return;
      }

      var delta = now - previous;
      if (delta >= MIN_RETURN_MS) {
        emit('btc_intelligence_return_visit', {
          return_window: returnWindow(delta)
        });
        window.localStorage.setItem(VISIT_KEY, String(now));
      }
    } catch (error) {
      // Storage can be blocked by browser/privacy settings. Analytics must
      // never break the product surface when that happens.
    }
  }

  /**
   * Progressive enhancement for repeat visitors: put the information that
   * answers "what changed since I last looked?" before the detailed spectrum.
   * No data is recomputed or duplicated; existing server-rendered blocks are
   * only reordered in the DOM. If markup changes, this fails open and leaves
   * the canonical server order untouched.
   */
  function prioritizeMarketPulse() {
    var snapshot = document.querySelector('.bm-bi__snapshot');
    if (!snapshot || snapshot.getAttribute('data-market-pulse-enhanced') === '1') return;

    var pulse = snapshot.querySelector('.bm-bi__session-context');
    var spectrum = snapshot.querySelector('.bm-bi__spectrum');
    var freshness = snapshot.querySelector('.bm-bi__freshness');
    var top = snapshot.querySelector('.bm-bi__snapshot-top');

    if (!pulse || !spectrum) return;

    // What Changed is the most valuable repeat-visit answer, followed by the
    // current setup/what happened, then the next context to watch.
    if (pulse.children.length >= 2) {
      pulse.insertBefore(pulse.children[1], pulse.children[0]);
    }

    pulse.classList.add('bm-bi__session-context--pulse');
    pulse.setAttribute('role', 'region');
    pulse.setAttribute('aria-label', 'Market Pulse: perubahan dan konteks BTC saat ini');

    if (freshness && top) {
      top.insertAdjacentElement('afterend', freshness);
      freshness.insertAdjacentElement('afterend', pulse);
    } else {
      spectrum.parentNode.insertBefore(pulse, spectrum);
    }

    snapshot.setAttribute('data-market-pulse-enhanced', '1');
  }

  document.addEventListener('click', function (event) {
    var historyControl = event.target && event.target.closest
      ? event.target.closest('.bmreg-trend-bar, .bm-state-chart .bm-direction-bar')
      : null;

    if (historyControl) {
      emit('btc_history_interaction', {
        history_surface: historyControl.classList.contains('bmreg-trend-bar') ? 'regime_history' : 'market_context'
      });
    }

    var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
    if (!link) return;

    try {
      var destination = new URL(link.getAttribute('href') || '', window.location.origin);
      if (destination.origin === window.location.origin && destination.pathname.replace(/\/+$/, '') === '/pro') {
        emit('btc_pro_interest', {
          cta_placement: link.closest('.bm-bi__pro-cta') ? 'btc_intelligence_pro_cta' : 'btc_intelligence_link'
        });
      }
    } catch (error) {
      // Ignore malformed/non-navigation hrefs.
    }
  }, true);

  prioritizeMarketPulse();
  trackVisit();
}());
