(() => {
  'use strict';

  const config = window.BitmomoMarketContext || {};
  const root = document.querySelector('.bm-bi');
  const snapshot = root ? root.querySelector('.bm-bi__snapshot') : null;
  if (!root || !snapshot || !config.endpoint) return;

  const SERIES_ORDER = ['btc', 'gold', 'eth', 'sol'];
  const RANGE_ORDER = ['7d', '30d', '90d', 'ytd', '1y'];
  const MAX_ACTIVE_SERIES = 3;
  const state = {
    range: config.defaultRange || '30d',
    active: new Set(['btc']),
    payload: null,
    controller: null,
    requestSeq: 0
  };

  root.classList.add('bm-bi--market-context');
  promoteCurrentDecision();
  const explorer = buildExplorer();
  snapshot.insertAdjacentElement('afterend', explorer.section);
  enhanceSectionFlow();
  loadRange(state.range);

  function promoteCurrentDecision() {
    const title = snapshot.querySelector('#bm-bi-current-title');
    if (!title || snapshot.querySelector('.bm-bi__market-state')) return;
    const badge = document.createElement('span');
    badge.className = 'bm-bi__market-state';
    badge.hidden = true;
    badge.innerHTML = '<span>KONDISI PASAR</span><strong>—</strong>';
    title.insertAdjacentElement('afterend', badge);
  }

  function enhanceSectionFlow() {
    const rail = root.querySelector('.bm-bi__rail');
    if (rail && !rail.querySelector('a[href="#market-context"]')) {
      const link = document.createElement('a');
      link.href = '#market-context';
      link.textContent = 'Konteks';
      const firstLink = rail.querySelector('a');
      if (firstLink) firstLink.insertAdjacentElement('afterend', link);
      else rail.appendChild(link);
    }

    const methodology = root.querySelector('.bm-bi__methodology');
    const proNextStep = root.querySelector('.bm-bi__pro-cta');
    if (methodology && proNextStep) methodology.before(proNextStep);
  }

  function buildExplorer() {
    const section = document.createElement('section');
    section.id = 'market-context';
    section.className = 'bm-bi__section bm-mc';
    section.setAttribute('aria-labelledby', 'bm-mc-title');

    const heading = document.createElement('div');
    heading.className = 'bm-mc__heading';
    heading.innerHTML = [
      '<div>',
      '<p class="bm-bi__eyebrow">KONTEKS PASAR</p>',
      '<h2 id="bm-mc-title">Pahami BTC dalam konteks pasar yang lebih luas.</h2>',
      '<p>Bandingkan BTC dengan aset lain untuk melihat perubahan relatif dan kapan tesis pasar berubah.</p>',
      '</div>',
      '<div class="bm-mc__mode"><span>KINERJA RELATIF</span><strong>Awal = 100</strong></div>'
    ].join('');

    const controls = document.createElement('div');
    controls.className = 'bm-mc__controls';

    const rangeGroup = document.createElement('div');
    rangeGroup.className = 'bm-mc__range';
    rangeGroup.setAttribute('role', 'group');
    rangeGroup.setAttribute('aria-label', 'Rentang waktu');
    RANGE_ORDER.forEach((range) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.dataset.range = range;
      button.textContent = (config.ranges && config.ranges[range]) || range.toUpperCase();
      button.setAttribute('aria-pressed', range === state.range ? 'true' : 'false');
      button.addEventListener('click', () => {
        if (range === state.range) return;
        state.range = range;
        rangeGroup.querySelectorAll('button').forEach((item) => item.setAttribute('aria-pressed', item === button ? 'true' : 'false'));
        loadRange(range);
      });
      rangeGroup.appendChild(button);
    });

    const compareWrap = document.createElement('div');
    compareWrap.className = 'bm-mc__compare';
    const compareLabel = document.createElement('span');
    compareLabel.className = 'bm-mc__control-label';
    compareLabel.textContent = 'BANDINGKAN';
    const compareGroup = document.createElement('div');
    compareGroup.className = 'bm-mc__series-controls';
    compareGroup.setAttribute('role', 'group');
    compareGroup.setAttribute('aria-label', 'Aset pembanding');
    compareWrap.append(compareLabel, compareGroup);

    const chart = document.createElement('div');
    chart.className = 'bm-mc__chart';
    chart.setAttribute('role', 'region');
    chart.setAttribute('aria-label', 'Grafik perbandingan kinerja pasar');
    chart.innerHTML = '<div class="bm-mc__loading" role="status">Memuat konteks pasar…</div>';

    const legend = document.createElement('div');
    legend.className = 'bm-mc__legend';

    const status = document.createElement('p');
    status.className = 'bm-mc__status';
    status.setAttribute('aria-live', 'polite');

    const overlays = document.createElement('div');
    overlays.className = 'bm-mc__pro-overlays';
    overlays.innerHTML = [
      '<div class="bm-mc__pro-copy"><span>KONTEKS PRO</span><p>Pro menambahkan Expected Range, Scenario Map, dan Invalidation pada grafik yang sama.</p></div>',
      '<div class="bm-mc__pro-items" aria-label="Overlay Bitmomo Pro">',
      '<span>Expected Range <b aria-hidden="true">↗</b></span>',
      '<span>Scenario Map <b aria-hidden="true">↗</b></span>',
      '<span>Invalidation <b aria-hidden="true">↗</b></span>',
      '</div>'
    ].join('');

    controls.append(rangeGroup, compareWrap);
    section.append(heading, controls, chart, legend, status, overlays);
    return { section, compareGroup, chart, legend, status };
  }

  async function loadRange(range) {
    if (state.controller) state.controller.abort();

    const requestId = ++state.requestSeq;
    const controller = new AbortController();
    state.controller = controller;

    // A selected range owns its own payload. Never let an older successful
    // range remain interactive while a new range is loading or has failed.
    state.payload = null;
    state.active = new Set(['btc']);
    explorer.compareGroup.replaceChildren();
    explorer.legend.replaceChildren();
    explorer.chart.innerHTML = '<div class="bm-mc__loading" role="status">Memuat konteks pasar…</div>';
    explorer.status.textContent = 'Memuat data perbandingan pasar.';
    setLoading(true);

    try {
      const url = new URL(config.endpoint, window.location.origin);
      url.searchParams.set('range', range);
      const response = await fetch(url.toString(), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        signal: controller.signal
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const payload = await response.json();
      if (requestId !== state.requestSeq || range !== state.range) return;
      if (!payload || payload.range !== range || !Array.isArray(payload.series)) throw new Error('Invalid market-context payload');
      state.payload = payload;
      syncCurrentDecision(payload.current || {});
      renderSeriesControls();
      renderChart();
    } catch (error) {
      if (error && error.name === 'AbortError') return;
      if (requestId !== state.requestSeq || range !== state.range) return;

      state.payload = null;
      state.active = new Set(['btc']);
      explorer.compareGroup.replaceChildren();
      explorer.chart.innerHTML = '<div class="bm-mc__unavailable"><strong>Konteks pasar belum tersedia.</strong><span>Decision View di atas tetap menggunakan data utama Bitmomo.</span></div>';
      explorer.legend.replaceChildren();
      explorer.status.textContent = 'Konteks Pasar gagal dimuat; pembacaan BTC utama tidak terpengaruh.';
    } finally {
      if (requestId !== state.requestSeq) return;
      if (state.controller === controller) state.controller = null;
      setLoading(false);
    }
  }

  function setLoading(loading) {
    explorer.section.classList.toggle('is-loading', loading);
    if (loading && !state.payload) explorer.status.textContent = 'Memuat data perbandingan pasar.';
  }

  function syncCurrentDecision(current) {
    const badge = snapshot.querySelector('.bm-bi__market-state');
    if (!badge || current.status !== 'available' || !current.market_state) return;
    const labels = {
      accumulation: 'Akumulasi',
      expansion: 'Ekspansi',
      distribution: 'Distribusi',
      capitulation: 'Kapitulasi',
      transition: 'Transisi'
    };
    badge.hidden = false;
    badge.className = `bm-bi__market-state is-${escapeToken(current.market_state)}`;
    const value = badge.querySelector('strong');
    if (value) value.textContent = labels[current.market_state] || humanize(current.market_state);
  }

  function renderSeriesControls() {
    const seriesById = new Map(state.payload.series.map((item) => [item.id, item]));
    explorer.compareGroup.replaceChildren();

    SERIES_ORDER.forEach((id) => {
      const series = seriesById.get(id);
      if (!series) return;
      const available = series.status === 'available' && Array.isArray(series.points) && series.points.length > 1;
      if (!available && state.active.has(id) && id !== 'btc') state.active.delete(id);

      const button = document.createElement('button');
      button.type = 'button';
      button.className = `bm-mc__series-button is-${escapeToken(id)}`;
      button.dataset.series = id;
      button.setAttribute('aria-pressed', state.active.has(id) ? 'true' : 'false');
      button.disabled = id === 'btc' || !available;

      const label = document.createElement('span');
      label.textContent = series.label;
      const meta = document.createElement('small');
      meta.textContent = available ? series.cadence : unavailableLabel(series.reason);
      button.append(label, meta);

      if (id !== 'btc' && available) {
        button.disabled = false;
        button.addEventListener('click', () => toggleSeries(id, button));
      }
      explorer.compareGroup.appendChild(button);
    });
  }

  function toggleSeries(id, button) {
    if (state.active.has(id)) {
      state.active.delete(id);
      button.setAttribute('aria-pressed', 'false');
      renderChart();
      return;
    }
    if (state.active.size >= MAX_ACTIVE_SERIES) {
      explorer.status.textContent = `Maksimal ${MAX_ACTIVE_SERIES} aset sekaligus agar perbandingan tetap terbaca.`;
      return;
    }
    state.active.add(id);
    button.setAttribute('aria-pressed', 'true');
    renderChart();
  }

  function renderChart() {
    if (!state.payload) return;
    const available = state.payload.series.filter((item) => state.active.has(item.id) && item.status === 'available' && Array.isArray(item.points) && item.points.length > 1);
    if (!available.length) {
      explorer.chart.innerHTML = '<div class="bm-mc__unavailable"><strong>Data pembanding belum tersedia.</strong></div>';
      return;
    }

    const normalized = normalizeTogether(available);
    if (!normalized.length) {
      explorer.chart.innerHTML = '<div class="bm-mc__unavailable"><strong>Data pada rentang ini belum cukup untuk dibandingkan.</strong></div>';
      return;
    }

    const bounds = chartBounds(normalized);
    const dims = { width: 1000, height: 360, left: 58, right: 24, top: 24, bottom: 42 };
    const plotWidth = dims.width - dims.left - dims.right;
    const plotHeight = dims.height - dims.top - dims.bottom;
    const x = (t) => dims.left + ((t - bounds.minT) / Math.max(1, bounds.maxT - bounds.minT)) * plotWidth;
    const y = (value) => dims.top + (1 - ((value - bounds.minY) / Math.max(0.0001, bounds.maxY - bounds.minY))) * plotHeight;

    const svg = svgEl('svg', {
      viewBox: `0 0 ${dims.width} ${dims.height}`,
      class: 'bm-mc__svg',
      role: 'img',
      'aria-label': `Kinerja relatif ${normalized.map((item) => item.label).join(', ')}; awal rentang dinormalisasi ke 100.`
    });

    for (let i = 0; i < 5; i += 1) {
      const value = bounds.minY + ((bounds.maxY - bounds.minY) * i) / 4;
      const lineY = y(value);
      svg.appendChild(svgEl('line', { x1: dims.left, x2: dims.width - dims.right, y1: lineY, y2: lineY, class: value === 100 ? 'bm-mc__baseline' : 'bm-mc__gridline' }));
      const label = svgEl('text', { x: dims.left - 10, y: lineY + 4, class: 'bm-mc__axis-label', 'text-anchor': 'end' });
      label.textContent = formatIndex(value);
      svg.appendChild(label);
    }

    const xTicks = [bounds.minT, bounds.minT + ((bounds.maxT - bounds.minT) / 2), bounds.maxT];
    xTicks.forEach((tick, index) => {
      const label = svgEl('text', { x: x(tick), y: dims.height - 13, class: 'bm-mc__axis-label', 'text-anchor': index === 0 ? 'start' : index === 2 ? 'end' : 'middle' });
      label.textContent = formatDate(tick, state.range);
      svg.appendChild(label);
    });

    normalized.forEach((series) => {
      const path = svgEl('path', {
        d: series.points.map((point, index) => `${index ? 'L' : 'M'} ${x(point.t).toFixed(2)} ${y(point.index).toFixed(2)}`).join(' '),
        class: `bm-mc__line is-${escapeToken(series.id)}`,
        fill: 'none'
      });
      svg.appendChild(path);
    });

    const btc = normalized.find((series) => series.id === 'btc');
    if (btc) renderMarkers(svg, btc, bounds, x, y);

    const crosshair = svgEl('line', { class: 'bm-mc__crosshair', y1: dims.top, y2: dims.height - dims.bottom, x1: dims.left, x2: dims.left });
    crosshair.hidden = true;
    svg.appendChild(crosshair);

    const hit = svgEl('rect', { x: dims.left, y: dims.top, width: plotWidth, height: plotHeight, class: 'bm-mc__hit-area' });
    svg.appendChild(hit);

    const tooltip = document.createElement('div');
    tooltip.className = 'bm-mc__tooltip';
    tooltip.hidden = true;

    explorer.chart.replaceChildren(svg, tooltip);
    bindChartPointer(svg, hit, crosshair, tooltip, normalized, bounds, dims);
    renderLegend(normalized);
    explorer.status.textContent = `${normalized.map((item) => item.label).join(', ')} dibandingkan dari titik awal ${formatDate(normalized[0].baselineT, '1y')}. Semua garis dimulai dari indeks 100.`;
  }

  function normalizeTogether(seriesList) {
    const firstTimes = seriesList.map((series) => series.points[0] && Number(series.points[0].t)).filter(Number.isFinite);
    if (!firstTimes.length) return [];
    const baselineT = Math.max(...firstTimes);
    return seriesList.map((series) => {
      const points = series.points.filter((point) => Number(point.t) >= baselineT && Number(point.value) > 0);
      if (points.length < 2) return null;
      const base = Number(points[0].value);
      return {
        ...series,
        baselineT: Number(points[0].t),
        base,
        points: points.map((point) => ({ ...point, t: Number(point.t), value: Number(point.value), index: (Number(point.value) / base) * 100 }))
      };
    }).filter(Boolean);
  }

  function chartBounds(seriesList) {
    const allPoints = seriesList.flatMap((series) => series.points);
    const values = allPoints.map((point) => point.index).filter(Number.isFinite);
    const times = allPoints.map((point) => point.t).filter(Number.isFinite);
    const rawMin = Math.min(100, ...values);
    const rawMax = Math.max(100, ...values);
    const padding = Math.max(1.5, (rawMax - rawMin) * 0.12);
    return {
      minT: Math.min(...times),
      maxT: Math.max(...times),
      minY: rawMin - padding,
      maxY: rawMax + padding
    };
  }

  function renderMarkers(svg, btc, bounds, x, y) {
    const markers = Array.isArray(state.payload.markers) ? state.payload.markers : [];
    markers.forEach((marker) => {
      const t = Number(marker.t);
      if (!Number.isFinite(t) || t < bounds.minT || t > bounds.maxT) return;
      const point = nearestPoint(btc.points, t);
      if (!point) return;
      const group = svgEl('g', { class: `bm-mc__marker is-${escapeToken(marker.type || 'state')}`, 'data-label': marker.label || '' });
      const cx = x(t);
      const cy = y(point.index);
      if (marker.type === 'decision') {
        group.appendChild(svgEl('circle', { cx, cy, r: 5 }));
      } else {
        group.appendChild(svgEl('path', { d: `M ${cx} ${cy - 6} L ${cx + 6} ${cy} L ${cx} ${cy + 6} L ${cx - 6} ${cy} Z` }));
      }
      const title = svgEl('title');
      title.textContent = markerTitle(marker);
      group.appendChild(title);
      svg.appendChild(group);
    });
  }

  function bindChartPointer(svg, hit, crosshair, tooltip, normalized, bounds, dims) {
    const plotWidth = dims.width - dims.left - dims.right;
    const update = (clientX) => {
      const rect = svg.getBoundingClientRect();
      const ratio = Math.min(1, Math.max(0, (clientX - rect.left) / Math.max(1, rect.width)));
      const svgX = ratio * dims.width;
      const plotRatio = Math.min(1, Math.max(0, (svgX - dims.left) / plotWidth));
      const targetT = bounds.minT + plotRatio * (bounds.maxT - bounds.minT);
      const anchor = nearestPoint(normalized[0].points, targetT);
      if (!anchor) return;
      const lineX = dims.left + ((anchor.t - bounds.minT) / Math.max(1, bounds.maxT - bounds.minT)) * plotWidth;
      crosshair.hidden = false;
      crosshair.setAttribute('x1', lineX);
      crosshair.setAttribute('x2', lineX);
      tooltip.hidden = false;
      tooltip.innerHTML = tooltipHtml(anchor.t, normalized);
      const leftPct = (lineX / dims.width) * 100;
      tooltip.style.left = `${Math.min(78, Math.max(8, leftPct))}%`;
    };

    hit.addEventListener('pointermove', (event) => update(event.clientX));
    hit.addEventListener('pointerleave', () => {
      crosshair.hidden = true;
      tooltip.hidden = true;
    });
  }

  function tooltipHtml(targetT, normalized) {
    const rows = normalized.map((series) => {
      const point = nearestPoint(series.points, targetT);
      if (!point) return '';
      const change = point.index - 100;
      return `<div class="bm-mc__tooltip-row"><span><i class="is-${escapeToken(series.id)}"></i>${escapeHtml(series.label)}</span><strong>${formatSigned(change)}%</strong><small>${formatActual(point.value, series.unit)}</small></div>`;
    }).join('');
    return `<time>${escapeHtml(formatDate(targetT, '1y'))}</time>${rows}`;
  }

  function renderLegend(normalized) {
    explorer.legend.replaceChildren();
    normalized.forEach((series) => {
      const last = series.points[series.points.length - 1];
      const item = document.createElement('div');
      item.className = `bm-mc__legend-item is-${escapeToken(series.id)}`;
      item.innerHTML = `<span><i></i>${escapeHtml(series.label)}</span><strong>${formatSigned(last.index - 100)}%</strong><small>${escapeHtml(series.cadence)} · ${escapeHtml(series.provider)}</small>`;
      explorer.legend.appendChild(item);
    });
  }

  function nearestPoint(points, targetT) {
    if (!points || !points.length) return null;
    let best = points[0];
    let distance = Math.abs(points[0].t - targetT);
    for (let i = 1; i < points.length; i += 1) {
      const next = Math.abs(points[i].t - targetT);
      if (next < distance) {
        best = points[i];
        distance = next;
      }
    }
    return best;
  }

  function markerTitle(marker) {
    const parts = [marker.label || 'Penanda Bitmomo'];
    if (marker.market_state) parts.push(humanize(marker.market_state));
    if (marker.bias) parts.push(humanize(marker.bias));
    if (Number.isFinite(Number(marker.confidence))) parts.push(`Keyakinan ${Number(marker.confidence)}/100`);
    return parts.join(' · ');
  }

  function unavailableLabel(reason) {
    if (reason === 'provider_not_configured') return 'belum dikonfigurasi';
    if (reason === 'insufficient_history' || reason === 'insufficient_closed_history') return 'riwayat belum cukup';
    return 'sementara tidak tersedia';
  }

  function formatActual(value, unit) {
    if (!Number.isFinite(Number(value))) return '—';
    const number = Number(value);
    const formatted = new Intl.NumberFormat('en-US', { maximumFractionDigits: number >= 100 ? 0 : 2 }).format(number);
    if (unit === 'USD') return `$${formatted}`;
    if (unit === 'USD/oz') return `$${formatted}/oz`;
    return `${formatted} ${unit || ''}`.trim();
  }

  function formatIndex(value) {
    return Number(value).toFixed(Math.abs(value - 100) < 10 ? 1 : 0);
  }

  function formatSigned(value) {
    const rounded = Math.round(Number(value) * 10) / 10;
    return `${rounded > 0 ? '+' : ''}${rounded.toFixed(1)}`;
  }

  function formatDate(timestamp, range) {
    const date = new Date(Number(timestamp) * 1000);
    const options = range === '1y' || range === 'ytd'
      ? { day: '2-digit', month: 'short', year: '2-digit' }
      : { day: '2-digit', month: 'short' };
    return new Intl.DateTimeFormat('id-ID', options).format(date);
  }

  function humanize(value) {
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
  }

  function escapeToken(value) {
    return String(value || '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value == null ? '' : value);
    return div.innerHTML;
  }

  function svgEl(name, attrs = {}) {
    const element = document.createElementNS('http://www.w3.org/2000/svg', name);
    Object.entries(attrs).forEach(([key, value]) => element.setAttribute(key, String(value)));
    return element;
  }
})();