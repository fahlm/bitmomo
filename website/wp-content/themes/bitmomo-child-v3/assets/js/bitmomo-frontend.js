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

  function setMenuOpen(isOpen, returnFocus) {
    if (!resolveMenu()) return;
    var shouldOpen = Boolean(isOpen && isMobileMenu());
    nav.classList.toggle('open', shouldOpen);
    hamburger.classList.toggle('active', shouldOpen);
    document.body.classList.toggle('menu-open', shouldOpen);
    hamburger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    syncMenuAccessibility(shouldOpen);
    if (!shouldOpen && returnFocus) hamburger.focus();
  }

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('#bm-hamburger');
    if (trigger) {
      hamburger = trigger;
      nav = document.getElementById('bm-nav') || document.querySelector('.bm-nav');
      if (!nav) return;
      event.preventDefault();
      setMenuOpen(hamburger.getAttribute('aria-expanded') !== 'true', false);
      return;
    }

    var navLink = event.target.closest('#bm-nav a, .bm-nav a');
    if (navLink) {
      setMenuOpen(false, false);
      return;
    }

    if (
      resolveMenu() &&
      hamburger.getAttribute('aria-expanded') === 'true' &&
      !event.target.closest('#bm-nav')
    ) {
      setMenuOpen(false, false);
    }
  });

  window.addEventListener('resize', function () {
    if (!resolveMenu()) return;
    if (!isMobileMenu()) {
      setMenuOpen(false, false);
    } else {
      syncMenuAccessibility(hamburger.getAttribute('aria-expanded') === 'true');
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape' || !resolveMenu()) return;
    var wasOpen = hamburger.getAttribute('aria-expanded') === 'true';
    if (wasOpen) setMenuOpen(false, true);
  });

  if (resolveMenu()) {
    hamburger.setAttribute('aria-expanded', 'false');
    syncMenuAccessibility(false);
  }

  document.addEventListener('click', function (event) {
    var link = event.target && event.target.closest ? event.target.closest('[data-bm-cta]') : null;
    if (!link) return;

    var config = window.bitmomoConfig;
    if (!config || !config.ajaxUrl || !config.ctaNonce) return;

    var payload = new URLSearchParams({
      action: 'bitmomo_cta_click',
      cta: link.getAttribute('data-bm-cta') || '',
      nonce: config.ctaNonce
    });

    if (navigator.sendBeacon) {
      navigator.sendBeacon(config.ajaxUrl, payload);
    } else {
      fetch(config.ajaxUrl, { method: 'POST', body: payload, keepalive: true });
    }
  }, true);
}());
