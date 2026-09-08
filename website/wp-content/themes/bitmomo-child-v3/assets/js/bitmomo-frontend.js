(function () {
  'use strict';

  var hamburger = null;
  var nav = null;

  function resolveMenu() {
    hamburger = document.getElementById('bm-hamburger');
    nav = document.getElementById('bm-nav') || document.querySelector('.bm-nav');
    return Boolean(hamburger && nav);
  }

  function setMenuOpen(isOpen, returnFocus) {
    if (!resolveMenu()) return;
    nav.classList.toggle('open', isOpen);
    hamburger.classList.toggle('active', isOpen);
    document.body.classList.toggle('menu-open', isOpen);
    hamburger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (!isOpen && returnFocus) hamburger.focus();
  }

  /*
   * Delegate menu interaction from document so it remains functional when an
   * optimizer defers this file or the cached header markup is replaced.
   */
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
    if (navLink) setMenuOpen(false, false);
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth > 768) setMenuOpen(false, false);
  });

  if (resolveMenu() && !hamburger.hasAttribute('aria-expanded')) {
    hamburger.setAttribute('aria-expanded', 'false');
  }

  var modal = document.getElementById('bm-subscribe-modal');
  var lastFocused = null;

  function focusableElements() {
    if (!modal) return [];
    return Array.from(modal.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'))
      .filter(function (element) { return element.offsetParent !== null; });
  }

  function openModal(event) {
    if (!modal) return;
    if (event) event.preventDefault();
    lastFocused = document.activeElement;
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    window.setTimeout(function () {
      var target = modal.querySelector('input[type="email"]') || modal.querySelector('.bm-subscribe-dialog');
      if (target) target.focus();
    }, 50);
  }

  function closeModal(event) {
    if (!modal || modal.getAttribute('aria-hidden') === 'true') return;
    if (event) event.preventDefault();
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (/#(subscribe|newsletter)$/i.test(window.location.hash || '')) {
      window.history.replaceState(null, '', window.location.pathname + window.location.search);
    }
    if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
  }

  function isSubscribeLink(href) {
    if (!href) return false;
    try {
      var url = new URL(href, window.location.origin);
      var path = (url.pathname || '').replace(/\/+$/, '').toLowerCase();
      var hash = (url.hash || '').toLowerCase();
      return path === '/subscribe' || hash === '#subscribe' || hash === '#newsletter';
    } catch (error) {
      return false;
    }
  }

  document.addEventListener('click', function (event) {
    var closeControl = event.target.closest('[data-close="1"]');
    if (closeControl) {
      closeModal(event);
      return;
    }
    var link = event.target.closest('a[href]');
    if (link && (link.classList.contains('js-open-subscribe') || isSubscribeLink(link.getAttribute('href')))) {
      openModal(event);
    }
  }, true);

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      setMenuOpen(false, Boolean(hamburger && hamburger.getAttribute('aria-expanded') === 'true'));
      closeModal(event);
      return;
    }
    if (event.key !== 'Tab' || !modal || modal.getAttribute('aria-hidden') === 'true') return;
    var elements = focusableElements();
    if (!elements.length) return;
    var first = elements[0];
    var last = elements[elements.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  if (/#(subscribe|newsletter)$/i.test(window.location.hash || '')) openModal();

  /* ---------- CTA outbound click tracking ---------- */
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

  /* ---------- Homepage 30D direction chart: tap/click detail state ---------- */
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

    var detail = document.getElementById('bm-direction-detail');
    if (!detail) return;

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
