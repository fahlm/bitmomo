(function () {
  'use strict';

  var hamburger = null;
  var nav = null;
  var MOBILE_MAX = 768;

  function resolveMenu() {
    hamburger = document.getElementById('bm-hamburger');
    nav = document.getElementById('bm-nav') || document.querySelector('.bm-nav');
    return Boolean(hamburger && nav);
  }

  function isMobileMenu() {
    return window.innerWidth <= MOBILE_MAX;
  }

  function menuFocusable() {
    if (!resolveMenu()) return [];
    return [hamburger].concat(Array.prototype.slice.call(nav.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])')))
      .filter(function (item) { return !item.hasAttribute('disabled') && item.getAttribute('aria-hidden') !== 'true'; });
  }

  function syncMenuAccessibility(isOpen) {
    if (!resolveMenu()) return;

    if (isMobileMenu()) {
      if (isOpen) {
        nav.removeAttribute('inert');
        nav.setAttribute('aria-hidden', 'false');
      } else {
        nav.setAttribute('inert', '');
        nav.setAttribute('aria-hidden', 'true');
      }
    } else {
      nav.removeAttribute('inert');
      nav.removeAttribute('aria-hidden');
    }

    hamburger.setAttribute('aria-label', isOpen ? 'Tutup menu' : 'Buka menu');
  }

  function setMenuOpen(isOpen, returnFocus, moveIntoMenu) {
    if (!resolveMenu()) return;
    var shouldOpen = Boolean(isOpen && isMobileMenu());
    nav.classList.toggle('open', shouldOpen);
    hamburger.classList.toggle('active', shouldOpen);
    document.body.classList.toggle('menu-open', shouldOpen);
    hamburger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    syncMenuAccessibility(shouldOpen);

    if (shouldOpen && moveIntoMenu) {
      window.requestAnimationFrame(function () {
        var firstLink = nav.querySelector('a[href], button:not([disabled])');
        if (firstLink) firstLink.focus();
      });
    }
    if (!shouldOpen && returnFocus) hamburger.focus();
  }

  function queryValue(name) {
    try {
      return String(new URLSearchParams(window.location.search || '').get(name) || '').slice(0, 120);
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

  function emitAnalytics(eventName, extra) {
    if (!eventName) return;
    var payload = {
      event: String(eventName).slice(0, 80),
      event_version: 1,
      page_path: (window.location.pathname || '/').slice(0, 160),
      utm_source: queryValue('utm_source'),
      utm_medium: queryValue('utm_medium'),
      utm_campaign: queryValue('utm_campaign'),
      referrer_host: referrerHost()
    };

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

  function isHomepageWhitelist() {
    var form = document.getElementById('bm-wl-form');
    if (!form) return false;
    var source = form.querySelector('input[name="source"]');
    return Boolean(source && source.value === 'homepage');
  }

  function simplifyHomepageWhitelist() {
    if (!isHomepageWhitelist()) return;
    var firstName = document.querySelector('#bm-wl-form input[name="first_name"]');
    if (firstName && firstName.closest('label')) {
      firstName.closest('label').remove();
    }
  }

  function appendWhitelistContinuation() {
    if (!isHomepageWhitelist()) return;
    var successPanel = document.getElementById('bm-wl-success-panel');
    if (!successPanel || successPanel.querySelector('.bm-wl__continuation')) return;

    var continuation = document.createElement('div');
    continuation.className = 'bm-wl__continuation';
    continuation.innerHTML =
      '<strong>Sambil menunggu, lihat bagaimana Bitmomo bekerja.</strong>' +
      '<p>Gunakan produk gratisnya sekarang, lalu lihat bagaimana pembacaan sebelumnya dievaluasi.</p>' +
      '<div class="bm-wl__continuation-actions">' +
        '<a href="/btc-intelligence/" data-bm-event="homepage_post_signup_btc_click" data-bm-placement="whitelist_success">Buka BTC Intelligence</a>' +
        '<a href="/btc-intelligence/#decision-ledger" data-bm-event="homepage_post_signup_ledger_click" data-bm-placement="whitelist_success">Lihat Decision Ledger</a>' +
      '</div>';
    successPanel.appendChild(continuation);
  }

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('#bm-hamburger');
    if (trigger) {
      hamburger = trigger;
      nav = document.getElementById('bm-nav') || document.querySelector('.bm-nav');
      if (!nav) return;
      event.preventDefault();
      var opening = hamburger.getAttribute('aria-expanded') !== 'true';
      setMenuOpen(opening, false, opening);
      return;
    }

    var navLink = event.target.closest('#bm-nav a, .bm-nav a');
    if (navLink) {
      setMenuOpen(false, false, false);
      return;
    }

    if (
      resolveMenu() &&
      hamburger.getAttribute('aria-expanded') === 'true' &&
      !event.target.closest('#bm-nav')
    ) {
      setMenuOpen(false, false, false);
    }
  });

  window.addEventListener('resize', function () {
    if (!resolveMenu()) return;
    if (!isMobileMenu()) {
      setMenuOpen(false, false, false);
    } else {
      syncMenuAccessibility(hamburger.getAttribute('aria-expanded') === 'true');
    }
  });

  document.addEventListener('keydown', function (event) {
    if (!resolveMenu()) return;
    var isOpen = hamburger.getAttribute('aria-expanded') === 'true' && isMobileMenu();

    if (event.key === 'Escape' && isOpen) {
      event.preventDefault();
      setMenuOpen(false, true, false);
      return;
    }

    if (event.key !== 'Tab' || !isOpen) return;
    var focusable = menuFocusable();
    if (!focusable.length) return;
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    var active = document.activeElement;

    if (event.shiftKey && (active === first || focusable.indexOf(active) === -1)) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && active === last) {
      event.preventDefault();
      first.focus();
    }
  });

  if (resolveMenu()) {
    hamburger.setAttribute('aria-expanded', 'false');
    syncMenuAccessibility(false);
  }

  document.addEventListener('click', function (event) {
    var productLink = event.target && event.target.closest ? event.target.closest('[data-bm-event]') : null;
    if (productLink) {
      emitAnalytics(productLink.getAttribute('data-bm-event'), {
        cta_placement: String(productLink.getAttribute('data-bm-placement') || 'unknown').slice(0, 80),
        cta_target: String(productLink.getAttribute('href') || '').split('?')[0].slice(0, 160)
      });
    }

    // Legacy affiliate/outbound tracker remains isolated from product analytics.
    var affiliateLink = event.target && event.target.closest ? event.target.closest('[data-bm-cta]') : null;
    if (!affiliateLink) return;

    var config = window.bitmomoConfig;
    if (!config || !config.ajaxUrl || !config.ctaNonce) return;

    var payload = new URLSearchParams({
      action: 'bitmomo_cta_click',
      cta: affiliateLink.getAttribute('data-bm-cta') || '',
      nonce: config.ctaNonce
    });

    if (navigator.sendBeacon) {
      navigator.sendBeacon(config.ajaxUrl, payload);
    } else {
      fetch(config.ajaxUrl, { method: 'POST', body: payload, keepalive: true });
    }
  }, true);

  window.addEventListener('bitmomo:analytics', function (event) {
    var detail = event && event.detail && typeof event.detail === 'object' ? event.detail : {};
    if (!isHomepageWhitelist()) return;
    if (detail.source !== 'homepage') return;
    if (detail.event === 'whitelist_created' || detail.event === 'whitelist_duplicate') {
      appendWhitelistContinuation();
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', simplifyHomepageWhitelist, { once: true });
  } else {
    simplifyHomepageWhitelist();
  }
}());
