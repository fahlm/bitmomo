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
        emit('btc_intelligence_return_visit', { return_window: returnWindow(delta) });
        window.localStorage.setItem(VISIT_KEY, String(now));
      }
    } catch (error) {
      // Storage can be unavailable in privacy modes. Analytics never owns UX.
    }
  }

  document.addEventListener('click', function (event) {
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

  document.addEventListener('toggle', function (event) {
    var details = event.target;
    if (!details || !details.matches || !details.matches('.bm-bi__details') || !details.open) return;
    emit('btc_methodology_expand');
  }, true);

  trackVisit();
}());
