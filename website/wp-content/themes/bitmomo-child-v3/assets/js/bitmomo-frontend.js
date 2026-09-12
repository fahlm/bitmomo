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
