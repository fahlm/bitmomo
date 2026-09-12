(function () {
  'use strict';

  var hamburger = null;
  var nav = null;
  var mobileQuery = window.matchMedia('(max-width: 1023px)');

  function resolveMenu() {
    hamburger = document.getElementById('bm-hamburger');
    nav = document.getElementById('bm-nav');
    return Boolean(hamburger && nav);
  }

  function isCollapsedNavigation() {
    return mobileQuery.matches;
  }

  function syncMenuAccessibility(isOpen) {
    if (!resolveMenu()) return;
    if (isCollapsedNavigation()) {
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
    var shouldOpen = Boolean(isOpen && isCollapsedNavigation());
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
      event.preventDefault();
      setMenuOpen(trigger.getAttribute('aria-expanded') !== 'true', false);
      return;
    }

    if (event.target.closest('#bm-nav a')) {
      setMenuOpen(false, false);
      return;
    }

    if (resolveMenu() && hamburger.getAttribute('aria-expanded') === 'true' && !event.target.closest('#bm-nav')) {
      setMenuOpen(false, false);
    }
  });

  function handleNavigationModeChange() {
    if (!resolveMenu()) return;
    setMenuOpen(false, false);
    syncMenuAccessibility(false);
  }

  if (typeof mobileQuery.addEventListener === 'function') mobileQuery.addEventListener('change', handleNavigationModeChange);
  else if (typeof mobileQuery.addListener === 'function') mobileQuery.addListener(handleNavigationModeChange);

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape' || !resolveMenu()) return;
    if (hamburger.getAttribute('aria-expanded') === 'true') setMenuOpen(false, true);
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
    if (navigator.sendBeacon) navigator.sendBeacon(config.ajaxUrl, payload);
    else fetch(config.ajaxUrl, { method: 'POST', body: payload, keepalive: true });
  }, true);

  document.addEventListener('click', function (event) {
    var bar = event.target && event.target.closest ? event.target.closest('.bm-state-chart .bm-direction-bar') : null;
    if (!bar) return;
    var chart = bar.closest('.bm-state-chart');
    if (!chart) return;
    var buttons = chart.querySelectorAll('.bm-direction-bar');
    for (var i = 0; i < buttons.length; i++) {
      buttons[i].classList.remove('is-selected');
      buttons[i].setAttribute('aria-pressed', 'false');
    }
    bar.classList.add('is-selected');
    bar.setAttribute('aria-pressed', 'true');

    var dateEl = document.getElementById('bm-direction-detail-date');
    var biasEl = document.getElementById('bm-direction-detail-bias');
    var certEl = document.getElementById('bm-direction-detail-certainty');
    var stateEl = document.getElementById('bm-direction-detail-state');
    if (dateEl) dateEl.textContent = bar.getAttribute('data-date') || '';
    if (biasEl) {
      biasEl.textContent = bar.getAttribute('data-bias-label') || '';
      biasEl.className = bar.getAttribute('data-bias-class') || '';
    }
    if (certEl) certEl.textContent = bar.getAttribute('data-certainty-label') || '';
    if (stateEl) stateEl.textContent = bar.getAttribute('data-state-label') || '';
  });
}());
