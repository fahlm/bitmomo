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

  /*
   * The modal markup can render after this script tag (this file is not
   * deferred), so the lookup above can miss it on first parse. Re-resolve
   * once the DOM is ready and honour a direct #subscribe/#newsletter link
   * at that point too, mirroring resolveMenu()'s lazy re-query pattern.
   */
  document.addEventListener('DOMContentLoaded', function () {
    if (!modal) modal = document.getElementById('bm-subscribe-modal');
    if (modal && /#(subscribe|newsletter)$/i.test(window.location.hash || '')) openModal();
  });

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

(function () {
  // Progressive-enhancement only: reads the DOM the [bitmomo_market_regime_history]
  // shortcode already rendered (bitmomo-regime plugin) and draws a trend chart
  // above it. Never touches the plugin/shortcode itself, never fetches or
  // fabricates any data of its own -- purely a presentational layer over
  // markup that is already public on the page.
  var list = document.querySelector('.bmreg-history-list');
  var container = document.querySelector('.bmreg-history');
  if (!container) return;
  var items = list ? [].slice.call(list.querySelectorAll('.bmreg-history-item')) : [];

  var REGIME_LABELS = {
    akumulasi: 'Akumulasi',
    ekspansi: 'Ekspansi',
    distribusi: 'Distribusi',
    kapitulasi: 'Kapitulasi',
    transisi: 'Transisi'
  };

  function dayFromListItem(li) {
    var regime = li.getAttribute('data-regime') || '';
    var dateEl = li.querySelector('.bmreg-history-date');
    var regimeEl = li.querySelector('.bmreg-history-regime');
    var biasEl = li.querySelector('.bmreg-history-bias');
    var confEl = li.querySelector('.bmreg-history-confidence');
    var confidence = confEl ? parseInt(confEl.textContent, 10) : 0;
    if (isNaN(confidence)) confidence = 0;
    return {
      regime: regime,
      regimeLabel: regimeEl ? regimeEl.textContent.trim() : (REGIME_LABELS[regime] || regime),
      date: dateEl ? dateEl.textContent.trim() : '',
      biasLabel: biasEl ? biasEl.textContent.trim() : '',
      biasClass: biasEl ? biasEl.className : '',
      confidence: confidence
    };
  }

  var days = [];
  var dataNode = container.querySelector('.bmreg-history-data');
  if (dataNode) {
    try {
      var fullHistory = JSON.parse(dataNode.textContent || '[]');
      if (Array.isArray(fullHistory)) {
        days = fullHistory.map(function (day) {
          var regime = day.regime || '';
          var bias = day.directional_bias || '';
          var confidence = parseInt(day.regime_confidence, 10);
          if (isNaN(confidence)) confidence = 0;
          return {
            regime: regime,
            regimeLabel: day.regime_label_id || REGIME_LABELS[regime] || regime,
            date: day.date || '',
            biasLabel: bias ? bias.charAt(0).toUpperCase() + bias.slice(1) : '',
            biasClass: 'bmreg-history-bias bmreg-bias-' + bias,
            confidence: confidence
          };
        });
      }
    } catch (err) {
      days = [];
    }
  }
  if (!days.length) days = items.map(dayFromListItem);
  if (!days.length) return;

  var priceByDate = {};
  function dateKey(dateStr) {
    return (dateStr || '').slice(0, 10);
  }

  var currentIndex = days.length - 1;

  var wrap = document.createElement('div');
  wrap.className = 'bmreg-trend';

  var plot = document.createElement('div');
  plot.className = 'bmreg-trend-plot';

  var bars = document.createElement('div');
  bars.className = 'bmreg-trend-bars';

  days.forEach(function (day, i) {
    var bar = document.createElement('button');
    bar.type = 'button';
    bar.className = 'bmreg-trend-bar is-' + day.regime;
    bar.style.setProperty('--bmreg-h', Math.max(4, day.confidence) + '%');
    bar.setAttribute('data-index', i);
    bar.setAttribute('aria-label', day.date + ' · ' + day.regimeLabel + ' · ' + day.biasLabel + ' · ' + day.confidence + '%');
    if (i === days.length - 1) bar.classList.add('is-current');
    bars.appendChild(bar);
  });

  var priceWrap = document.createElement('div');
  priceWrap.className = 'bmreg-price';
  var svgNS = 'http://www.w3.org/2000/svg';
  var priceSvg = document.createElementNS(svgNS, 'svg');
  priceSvg.setAttribute('class', 'bmreg-price-svg');
  priceSvg.setAttribute('preserveAspectRatio', 'none');
  var pricePolyline = document.createElementNS(svgNS, 'polyline');
  pricePolyline.setAttribute('class', 'bmreg-price-line');
  priceSvg.appendChild(pricePolyline);
  priceWrap.appendChild(priceSvg);
  var priceCaption = document.createElement('p');
  priceCaption.className = 'bmreg-price-caption';
  priceCaption.textContent = 'Harga BTC (USD), selaras dengan tanggal di atas.';
  priceWrap.style.display = 'none';

  function renderPriceLine() {
    var prices = days.map(function (day) {
      var p = priceByDate[dateKey(day.date)];
      return typeof p === 'number' ? p : null;
    });
    var known = prices.filter(function (p) { return p !== null; });
    if (!known.length) { priceWrap.style.display = 'none'; return; }
    priceWrap.style.display = '';
    var min = Math.min.apply(null, known);
    var max = Math.max.apply(null, known);
    var range = max - min || 1;
    var barsRect = bars.getBoundingClientRect();
    var width = barsRect.width || bars.offsetWidth || 1;
    var height = barsRect.height || bars.offsetHeight || 160;
    priceSvg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
    priceSvg.style.width = width + 'px';
    [].slice.call(priceSvg.querySelectorAll('.bmreg-price-dot')).forEach(function (d) { d.remove(); });
    var barEls = [].slice.call(bars.children);
    var points = [];
    barEls.forEach(function (barEl, i) {
      var p = prices[i];
      if (p === null) return;
      var barRect = barEl.getBoundingClientRect();
      var x = (barRect.left + barRect.width / 2) - barsRect.left;
      var lineHeight = height * 0.42;
      var y = height - 10 - ((p - min) / range) * lineHeight;
      points.push(x.toFixed(1) + ',' + y.toFixed(1));
      var dot = document.createElementNS(svgNS, 'circle');
      dot.setAttribute('class', 'bmreg-price-dot' + (i === currentIndex ? ' is-current' : ''));
      dot.setAttribute('cx', x.toFixed(1));
      dot.setAttribute('cy', y.toFixed(1));
      dot.setAttribute('r', i === currentIndex ? 4 : 2.5);
      priceSvg.appendChild(dot);
    });
    pricePolyline.setAttribute('points', points.join(' '));
  }

  var detail = document.createElement('div');
  detail.className = 'bmreg-trend-detail';

  function renderDetail(day) {
    var priceStr = '';
    var price = priceByDate[dateKey(day.date)];
    if (typeof price === 'number') {
      priceStr = '<span class=' + JSON.stringify('bmreg-trend-detail-price') + '>$' + Math.round(price).toLocaleString('en-US') + '</span>';
    }
    detail.innerHTML =
      '<span class="bmreg-trend-detail-date">' + day.date + '</span>' +
      '<span class="bmreg-trend-detail-state">' + day.regimeLabel + '</span>' +
      '<span class="bmreg-trend-detail-bias ' + day.biasClass + '">' + day.biasLabel + '</span>' +
      '<span class="bmreg-trend-detail-conf">' + day.confidence + '%</span>' +
      priceStr;
  }

  renderDetail(days[days.length - 1]);

  bars.addEventListener('click', function (e) {
    var bar = e.target;
    while (bar && bar !== bars && !bar.classList.contains('bmreg-trend-bar')) {
      bar = bar.parentNode;
    }
    if (!bar || bar === bars) return;
    [].slice.call(bars.children).forEach(function (b) { b.classList.remove('is-current'); });
    bar.classList.add('is-current');
    var idx = parseInt(bar.getAttribute('data-index'), 10);
    currentIndex = idx;
    renderDetail(days[idx]);
    renderPriceLine();
  });

  var legend = document.createElement('div');
  legend.className = 'bmreg-trend-legend';
  legend.innerHTML = ['akumulasi', 'ekspansi', 'distribusi', 'kapitulasi', 'transisi'].map(function (key) {
    return '<span><i class="is-' + key + '"></i>' + REGIME_LABELS[key] + '</span>';
  }).join('');

  var caption = document.createElement('p');
  caption.className = 'bmreg-trend-caption';
  caption.textContent = 'Tinggi bar = confidence yang tercatat hari itu, diwarnai berdasarkan Market State setiap hari.';

  plot.appendChild(bars);
  plot.appendChild(priceWrap);
  wrap.appendChild(plot);
  wrap.appendChild(priceCaption);
  wrap.appendChild(detail);
  wrap.appendChild(legend);
  wrap.appendChild(caption);

  container.insertBefore(wrap, container.firstChild);

  var firstDate = days.length ? dateKey(days[0].date) : '';
  var lastDate = days.length ? dateKey(days[days.length - 1].date) : '';
  var spanDays = 30;
  if (firstDate && lastDate) {
    var spanMs = new Date(lastDate + 'T00:00:00Z').getTime() - new Date(firstDate + 'T00:00:00Z').getTime();
    spanDays = Math.max(1, Math.round(spanMs / 86400000)) + 3;
  }

  fetch('https://api.coingecko.com/api/v3/coins/bitcoin/market_chart?vs_currency=usd&days=' + spanDays + '&interval=daily')
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data || !data.prices) return;
      data.prices.forEach(function (pt) {
        var key = new Date(pt[0]).toISOString().slice(0, 10);
        priceByDate[key] = pt[1];
      });
      renderPriceLine();
      renderDetail(days[currentIndex]);
    })
    .catch(function () {});

  window.addEventListener('resize', renderPriceLine);
}());
