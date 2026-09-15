(() => {
  'use strict';

  const config = window.BitmomoMarketContext || {};
  const root = document.querySelector('.bm-bi');
  const snapshot = root ? root.querySelector('.bm-bi__snapshot') : null;
  if (!root || !snapshot || !config.endpoint) return;

  const SERIES_ORDER = ['btc', 'eth', 'sol', 'gold'];
  const RANGE_ORDER = ['7d', '30d', '90d', 'ytd', '1y'];
  const DEFAULT_ACTIVE = ['btc', 'eth', 'sol'];
  const MAX_ACTIVE_SERIES = 3;
  const DAY_SECONDS = 86400;

  const state = {
    range: config.defaultRange || '30d',
    preferredActive: new Set(DEFAULT_ACTIVE),
    active: new Set(['btc']),
    payload: null,
    controller: null,
    requestSeq: 0,
    resizeBucket: null,
    resizeRaf: 0
  };

  root.classList.add('bm-bi--market-context');
  promoteCurrentDecision();
  const explorer = buildExplorer();
  snapshot.insertAdjacentElement('afterend', explorer.section);
  enhanceSectionFlow();
  setupResizeObserver();
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
      '<div class="bm-mc__mode"><span>METODE</span><strong>Indeks relatif · Awal = 100</strong><small>Harga aktual tetap tersedia saat grafik ditelusuri.</small></div>'
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
    chart.tabIndex = 0;
    chart.setAttribute('role', 'region');
    chart.setAttribute('aria-label', 'Grafik perbandingan kinerja pasar. Arahkan pointer, ketuk grafik, atau gunakan tombol panah kiri dan kanan untuk menelusuri tanggal.');
    chart.innerHTML = '<div class="bm-mc__loading" role="status">Memuat konteks pasar…</div>';

    const legend = document.createElement('div');
    legend.className = 'bm-mc__legend';
    legend.setAttribute('aria-label', 'Ringkasan aset aktif');

    const markerKey = document.createElement('div');
    markerKey.className = 'bm-mc__marker-key';
    markerKey.hidden = true;

    const status = document.createElement('p');
    status.className = 'bm-mc__status';
    status.setAttribute('aria-live', 'polite');

    const overlays = document.createElement('div');
    overlays.className = 'bm-mc__pro-overlays';
    overlays.innerHTML = [
      '<div class="bm-mc__pro-copy"><span>KONTEKS PRO</span><p>Pro menambahkan Expected Range, Scenario Map, dan Invalidation pada grafik yang sama.</p></div>',
      '<div class="bm-mc__pro-items" aria-label="Overlay Bitmomo Pro">',
      '<span>Expected Range</span>',
      '<span>Scenario Map</span>',
      '<span>Invalidation</span>',
      '</div>'
    ].join('');

    controls.append(rangeGroup, compareWrap);
    section.append(heading, controls, chart, legend, markerKey, status, overlays);
    return { section, rangeGroup, compareGroup, chart, legend, markerKey, status };
  }

  function setupResizeObserver() {
    if (typeof ResizeObserver !== 'function') return;
    state.resizeBucket = widthBucket(explorer.chart.clientWidth);
    const observer = new ResizeObserver((entries) => {
      const entry = entries[0];
      const width = entry && entry.contentRect ? entry.contentRect.width : explorer.chart.clientWidth;
      const nextBucket = widthBucket(width);
      if (nextBucket === state.resizeBucket) return;
      state.resizeBucket = nextBucket;
      if (!state.payload) return;
      if (state.resizeRaf) cancelAnimationFrame(state.resizeRaf);
      state.resizeRaf = requestAnimationFrame(() => {
        state.resizeRaf = 0;
        renderChart();
      });
    });
    observer.observe(explorer.chart);
  }

  function widthBucket(width) {
    if (width >= 900) return 'wide';
    if (width >= 620) return 'medium';
    return 'compact';
  }

  async function loadRange(range) {
    if (state.controller) state.controller.abort();

    const requestId = ++state.requestSeq;
    const controller = new AbortController();
    state.controller = controller;

    state.payload = null;
    state.active = new Set(['btc']);
    explorer.compareGroup.replaceChildren();
    explorer.legend.replaceChildren();
    explorer.markerKey.replaceChildren();
    explorer.markerKey.hidden = true;
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
      syncActiveSeries();
      renderSeriesControls();
      renderChart();
    } catch (error) {
      if (error && error.name === 'AbortError') return;
      if (requestId !== state.requestSeq || range !== state.range) return;

      state.payload = null;
      state.active = new Set(['btc']);
      explorer.compareGroup.replaceChildren();
      explorer.legend.replaceChildren();
      explorer.markerKey.replaceChildren();
      explorer.markerKey.hidden = true;
      explorer.chart.innerHTML = '<div class="bm-mc__unavailable"><strong>Konteks pasar belum tersedia.</strong><span>Decision View di atas tetap menggunakan data utama Bitmomo.</span></div>';
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

  function isSeriesAvailable(series) {
    return !!(series && series.status === 'available' && Array.isArray(series.points) && series.points.length > 1);
  }

  function syncActiveSeries() {
    if (!state.payload) return;
    const seriesById = new Map(state.payload.series.map((item) => [item.id, item]));
    const next = [];
    SERIES_ORDER.forEach((id) => {
      if (next.length >= MAX_ACTIVE_SERIES) return;
      if (!state.preferredActive.has(id)) return;
      if (isSeriesAvailable(seriesById.get(id))) next.push(id);
    });

    if (!next.includes('btc') && isSeriesAvailable(seriesById.get('btc'))) {
      next.unshift('btc');
    }

    state.active = new Set(next.slice(0, MAX_ACTIVE_SERIES));
  }

  function renderSeriesControls() {
    if (!state.payload) return;
    const seriesById = new Map(state.payload.series.map((item) => [item.id, item]));
    explorer.compareGroup.replaceChildren();

    SERIES_ORDER.forEach((id) => {
      const series = seriesById.get(id);
      if (!series) return;
      const available = isSeriesAvailable(series);
      const active = state.active.has(id);

      const button = document.createElement('button');
      button.type = 'button';
      button.className = `bm-mc__series-button is-${escapeToken(id)}`;
      button.dataset.series = id;
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
      button.disabled = id === 'btc' || !available;
      button.setAttribute('aria-label', `${series.label}: ${active ? 'aktif' : available ? 'tidak aktif' : 'data tidak tersedia'}`);

      const label = document.createElement('span');
      label.textContent = series.label;
      const meta = document.createElement('small');
      meta.dataset.role = 'meta';
      meta.textContent = available ? (active ? 'aktif' : 'tambahkan') : unavailableLabel(series.reason);
      button.append(label, meta);

      if (id !== 'btc' && available) {
        button.addEventListener('click', () => toggleSeries(id));
      }
      explorer.compareGroup.appendChild(button);
    });
  }

  function toggleSeries(id) {
    if (state.active.has(id)) {
      state.active.delete(id);
      state.preferredActive.delete(id);
      renderSeriesControls();
      renderChart();
      return;
    }

    if (state.active.size >= MAX_ACTIVE_SERIES) {
      explorer.status.textContent = `Maksimal ${MAX_ACTIVE_SERIES} aset sekaligus. Nonaktifkan satu pembanding untuk menambahkan ${String(id).toUpperCase()}.`;
      return;
    }

    state.active.add(id);
    state.preferredActive.add(id);
    renderSeriesControls();
    renderChart();
  }

  function renderChart() {
    if (!state.payload) return;
    const available = state.payload.series.filter((item) => state.active.has(item.id) && isSeriesAvailable(item));
    const btc = available.find((item) => item.id === 'btc');

    if (!btc) {
      explorer.chart.innerHTML = '<div class="bm-mc__unavailable"><strong>Data BTC untuk perbandingan belum tersedia.</strong><span>Decision View utama tetap dapat digunakan.</span></div>';
      explorer.legend.replaceChildren();
      explorer.markerKey.hidden = true;
      return;
    }

    const comparison = normalizeTogether(available);
    if (!comparison || !comparison.series.length) {
      explorer.chart.innerHTML = '<div class="bm-mc__unavailable"><strong>Belum ada tanggal dasar bersama untuk aset yang dipilih.</strong><span>Coba kurangi aset pembanding atau pilih rentang yang lebih panjang.</span></div>';
      explorer.legend.replaceChildren();
      explorer.markerKey.hidden = true;
      return;
    }

    const normalized = comparison.series;
    const bounds = chartBounds(normalized);
    const dims = { width: 1000, height: 390, left: 62, right: 24, top: 26, bottom: 46 };
    const plotWidth = dims.width - dims.left - dims.right;
    const plotHeight = dims.height - dims.top - dims.bottom;
    const x = (t) => dims.left + ((t - bounds.minT) / Math.max(1, bounds.maxT - bounds.minT)) * plotWidth;
    const y = (value) => dims.top + (1 - ((value - bounds.minY) / Math.max(0.0001, bounds.maxY - bounds.minY))) * plotHeight;

    const svg = svgEl('svg', {
      viewBox: `0 0 ${dims.width} ${dims.height}`,
      class: 'bm-mc__svg',
      role: 'img',
      'aria-label': `Kinerja relatif ${normalized.map((item) => item.label).join(', ')}; tanggal dasar bersama ${formatDate(comparison.baselineT, '1y')} dinormalisasi ke indeks 100.`
    });

    bounds.yTicks.forEach((value) => {
      const lineY = y(value);
      const isBaseline = Math.abs(value - 100) < Math.max(0.0001, bounds.yStep / 20);
      svg.appendChild(svgEl('line', {
        x1: dims.left,
        x2: dims.width - dims.right,
        y1: lineY,
        y2: lineY,
        class: isBaseline ? 'bm-mc__baseline' : 'bm-mc__gridline'
      }));
      const label = svgEl('text', {
        x: dims.left - 11,
        y: lineY + 4,
        class: isBaseline ? 'bm-mc__axis-label is-baseline' : 'bm-mc__axis-label',
        'text-anchor': 'end'
      });
      label.textContent = formatIndex(value, bounds.yStep);
      svg.appendChild(label);
    });

    const xTicks = buildXTicks(bounds.minT, bounds.maxT, tickCountForWidth(explorer.chart.clientWidth));
    xTicks.forEach((tick, index) => {
      if (index > 0 && index < xTicks.length - 1) {
        svg.appendChild(svgEl('line', {
          x1: x(tick),
          x2: x(tick),
          y1: dims.top,
          y2: dims.height - dims.bottom,
          class: 'bm-mc__x-gridline'
        }));
      }
      const label = svgEl('text', {
        x: x(tick),
        y: dims.height - 15,
        class: 'bm-mc__axis-label',
        'text-anchor': index === 0 ? 'start' : index === xTicks.length - 1 ? 'end' : 'middle'
      });
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

    const normalizedBtc = normalized.find((series) => series.id === 'btc');
    const markerTypes = normalizedBtc ? renderMarkers(svg, normalizedBtc, bounds, x, y) : new Set();
    renderMarkerKey(markerTypes);

    const crosshair = svgEl('line', {
      class: 'bm-mc__crosshair',
      y1: dims.top,
      y2: dims.height - dims.bottom,
      x1: dims.left,
      x2: dims.left
    });
    crosshair.hidden = true;
    svg.appendChild(crosshair);

    const hit = svgEl('rect', {
      x: dims.left,
      y: dims.top,
      width: plotWidth,
      height: plotHeight,
      class: 'bm-mc__hit-area'
    });
    svg.appendChild(hit);

    const tooltip = document.createElement('div');
    tooltip.className = 'bm-mc__tooltip';
    tooltip.hidden = true;

    explorer.chart.replaceChildren(svg, tooltip);

    const statusText = `Tanggal dasar bersama ${formatDate(comparison.baselineT, '1y')} · ${normalized.length} aset · data harian tertutup. Arahkan, ketuk, atau gunakan ← → untuk detail.`;
    explorer.status.textContent = statusText;

    const reveal = bindChartPointer(svg, hit, crosshair, tooltip, normalized, bounds, dims, statusText);
    bindChartKeyboard(reveal, tooltip, crosshair, normalized, statusText);
    renderLegend(normalized);
    syncControlMetrics(normalized);
  }

  function normalizeTogether(seriesList) {
    const prepared = seriesList.map((series) => ({
      ...series,
      points: series.points
        .map((point) => ({ ...point, t: Number(point.t), value: Number(point.value) }))
        .filter((point) => Number.isFinite(point.t) && Number.isFinite(point.value) && point.value > 0)
        .sort((a, b) => a.t - b.t)
    })).filter((series) => series.points.length > 1);

    if (!prepared.length) return null;

    const baselineT = findCommonBaselineTimestamp(prepared);
    if (!Number.isFinite(baselineT)) return null;

    const normalized = prepared.map((series) => {
      const basePoint = series.points.find((point) => point.t === baselineT);
      if (!basePoint || basePoint.value <= 0) return null;
      const points = series.points
        .filter((point) => point.t >= baselineT)
        .map((point) => ({ ...point, index: (point.value / basePoint.value) * 100 }));
      if (points.length < 2) return null;
      return {
        ...series,
        baselineT,
        base: basePoint.value,
        points
      };
    }).filter(Boolean);

    if (normalized.length !== prepared.length) return null;
    return { baselineT, series: normalized };
  }

  function findCommonBaselineTimestamp(seriesList) {
    if (!seriesList.length) return null;
    if (seriesList.length === 1) return seriesList[0].points[0].t;

    const remaining = seriesList.slice(1).map((series) => new Set(series.points.map((point) => point.t)));
    for (const point of seriesList[0].points) {
      if (remaining.every((times) => times.has(point.t))) return point.t;
    }
    return null;
  }

  function chartBounds(seriesList) {
    const allPoints = seriesList.flatMap((series) => series.points);
    const values = allPoints.map((point) => point.index).filter(Number.isFinite);
    const times = allPoints.map((point) => point.t).filter(Number.isFinite);
    const rawMin = Math.min(100, ...values);
    const rawMax = Math.max(100, ...values);
    const rawSpan = Math.max(1, rawMax - rawMin);
    const paddedMin = rawMin - Math.max(0.8, rawSpan * 0.08);
    const paddedMax = rawMax + Math.max(0.8, rawSpan * 0.08);
    const yStep = niceStep((paddedMax - paddedMin) / 4);
    let minY = Math.floor(paddedMin / yStep) * yStep;
    let maxY = Math.ceil(paddedMax / yStep) * yStep;

    if (maxY - minY < yStep * 4) {
      const missing = yStep * 4 - (maxY - minY);
      minY -= Math.ceil((missing / 2) / yStep) * yStep;
      maxY += Math.floor((missing / 2) / yStep) * yStep;
    }

    minY = Math.min(minY, 100);
    maxY = Math.max(maxY, 100);

    const yTicks = [];
    for (let value = minY, guard = 0; value <= maxY + yStep / 10 && guard < 12; value += yStep, guard += 1) {
      yTicks.push(Number(value.toFixed(8)));
    }

    return {
      minT: Math.min(...times),
      maxT: Math.max(...times),
      minY,
      maxY,
      yStep,
      yTicks
    };
  }

  function niceStep(rawStep) {
    const step = Math.max(0.0001, Number(rawStep) || 1);
    const exponent = Math.floor(Math.log10(step));
    const power = 10 ** exponent;
    const fraction = step / power;
    let niceFraction = 1;
    if (fraction > 5) niceFraction = 10;
    else if (fraction > 2.5) niceFraction = 5;
    else if (fraction > 2) niceFraction = 2.5;
    else if (fraction > 1) niceFraction = 2;
    return niceFraction * power;
  }

  function buildXTicks(minT, maxT, count) {
    const tickCount = Math.max(2, count);
    return Array.from({ length: tickCount }, (_, index) => minT + ((maxT - minT) * index) / (tickCount - 1));
  }

  function tickCountForWidth(width) {
    if (width >= 900) return 5;
    if (width >= 620) return 4;
    return 3;
  }

  function renderMarkers(svg, btc, bounds, x, y) {
    const markers = Array.isArray(state.payload.markers) ? state.payload.markers : [];
    const groups = new Map();

    markers.forEach((marker) => {
      const t = Number(marker.t);
      if (!Number.isFinite(t) || t < bounds.minT - DAY_SECONDS || t > bounds.maxT + DAY_SECONDS) return;
      const point = nearestPoint(btc.points, t);
      if (!point || Math.abs(point.t - t) > DAY_SECONDS * 1.5) return;
      const type = marker.type === 'decision' ? 'decision' : 'state';
      const key = `${point.t}:${type}`;
      if (!groups.has(key)) groups.set(key, { type, point, markers: [] });
      groups.get(key).markers.push(marker);
    });

    const types = new Set();
    groups.forEach((entry) => {
      types.add(entry.type);
      const cx = x(entry.point.t);
      const anchorY = y(entry.point.index);
      const cy = anchorY + (entry.type === 'decision' ? 11 : -11);
      const group = svgEl('g', {
        class: `bm-mc__marker is-${entry.type}`,
        'data-count': entry.markers.length
      });

      group.appendChild(svgEl('line', {
        x1: cx,
        x2: cx,
        y1: anchorY,
        y2: cy,
        class: 'bm-mc__marker-stem'
      }));

      if (entry.type === 'decision') {
        group.appendChild(svgEl('circle', { cx, cy, r: 5 }));
      } else {
        group.appendChild(svgEl('path', { d: `M ${cx} ${cy - 6} L ${cx + 6} ${cy} L ${cx} ${cy + 6} L ${cx - 6} ${cy} Z` }));
      }

      const title = svgEl('title');
      title.textContent = entry.markers.length > 1
        ? `${markerTitle(entry.markers[0])} · ${entry.markers.length} peristiwa`
        : markerTitle(entry.markers[0]);
      group.appendChild(title);
      svg.appendChild(group);
    });

    return types;
  }

  function renderMarkerKey(types) {
    explorer.markerKey.replaceChildren();
    if (!types || !types.size) {
      explorer.markerKey.hidden = true;
      return;
    }

    const label = document.createElement('span');
    label.className = 'bm-mc__marker-key-label';
    label.textContent = 'PENANDA';

    const items = document.createElement('div');
    items.className = 'bm-mc__marker-key-items';

    if (types.has('decision')) {
      const item = document.createElement('span');
      item.innerHTML = '<i class="is-decision" aria-hidden="true"></i>Decision Ledger';
      items.appendChild(item);
    }
    if (types.has('state')) {
      const item = document.createElement('span');
      item.innerHTML = '<i class="is-state" aria-hidden="true"></i>Perubahan tesis';
      items.appendChild(item);
    }

    explorer.markerKey.append(label, items);
    explorer.markerKey.hidden = false;
  }

  function bindChartPointer(svg, hit, crosshair, tooltip, normalized, bounds, dims, statusText) {
    const plotWidth = dims.width - dims.left - dims.right;
    let pinned = false;

    const hide = () => {
      pinned = false;
      crosshair.hidden = true;
      tooltip.hidden = true;
      tooltip.classList.remove('is-left', 'is-right');
      explorer.status.textContent = statusText;
    };

    const revealAtTimestamp = (targetT, announce = false) => {
      const anchor = nearestPoint(normalized[0].points, targetT);
      if (!anchor) return;
      const lineX = dims.left + ((anchor.t - bounds.minT) / Math.max(1, bounds.maxT - bounds.minT)) * plotWidth;
      crosshair.hidden = false;
      crosshair.setAttribute('x1', lineX);
      crosshair.setAttribute('x2', lineX);
      tooltip.hidden = false;
      tooltip.innerHTML = tooltipHtml(anchor.t, normalized);
      const leftPct = (lineX / dims.width) * 100;
      tooltip.style.left = `${Math.min(96, Math.max(4, leftPct))}%`;
      tooltip.classList.toggle('is-left', leftPct < 18);
      tooltip.classList.toggle('is-right', leftPct > 82);
      if (announce) explorer.status.textContent = keyboardSummary(anchor.t, normalized);
    };

    const updateFromClientX = (clientX, announce = false) => {
      const rect = svg.getBoundingClientRect();
      const ratio = Math.min(1, Math.max(0, (clientX - rect.left) / Math.max(1, rect.width)));
      const svgX = ratio * dims.width;
      const plotRatio = Math.min(1, Math.max(0, (svgX - dims.left) / plotWidth));
      const targetT = bounds.minT + plotRatio * (bounds.maxT - bounds.minT);
      revealAtTimestamp(targetT, announce);
    };

    hit.addEventListener('pointermove', (event) => {
      if (event.pointerType === 'touch' || pinned) return;
      updateFromClientX(event.clientX, false);
    });

    hit.addEventListener('pointerdown', (event) => {
      pinned = true;
      updateFromClientX(event.clientX, true);
    });

    hit.addEventListener('pointerleave', (event) => {
      if (event.pointerType !== 'touch' && !pinned) hide();
    });

    return { revealAtTimestamp, hide };
  }

  function bindChartKeyboard(reveal, tooltip, crosshair, normalized, statusText) {
    if (explorer.chart._bmMarketContextKeyHandler) {
      explorer.chart.removeEventListener('keydown', explorer.chart._bmMarketContextKeyHandler);
    }

    const points = normalized[0].points;
    let index = Math.max(0, points.length - 1);
    const handler = (event) => {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End', 'Escape'].includes(event.key)) return;
      event.preventDefault();

      if (event.key === 'Escape') {
        reveal.hide();
        return;
      }
      if (event.key === 'Home') index = 0;
      else if (event.key === 'End') index = points.length - 1;
      else if (event.key === 'ArrowLeft') index = Math.max(0, index - 1);
      else if (event.key === 'ArrowRight') index = Math.min(points.length - 1, index + 1);

      reveal.revealAtTimestamp(points[index].t, true);
    };

    explorer.chart._bmMarketContextKeyHandler = handler;
    explorer.chart.addEventListener('keydown', handler);

    explorer.chart.onblur = () => {
      tooltip.hidden = true;
      crosshair.hidden = true;
      explorer.status.textContent = statusText;
    };
  }

  function tooltipHtml(targetT, normalized) {
    const rows = normalized.map((series) => {
      const point = nearestPoint(series.points, targetT);
      if (!point) return '';
      const change = point.index - 100;
      const dateNote = point.t === targetT ? '' : ` · data ${formatDate(point.t, '1y')}`;
      return `<div class="bm-mc__tooltip-row"><span><i class="is-${escapeToken(series.id)}"></i>${escapeHtml(series.label)}</span><strong>${formatSigned(change)}%</strong><small>${escapeHtml(formatActual(point.value, series.unit))} · ${escapeHtml(series.provider)}${escapeHtml(dateNote)}</small></div>`;
    }).join('');

    const events = tooltipEvents(targetT);
    return `<time>${escapeHtml(formatDate(targetT, '1y'))}</time>${rows}${events}`;
  }

  function tooltipEvents(targetT) {
    const markers = Array.isArray(state.payload && state.payload.markers) ? state.payload.markers : [];
    const targetDay = utcDay(targetT);
    const dayMarkers = markers.filter((marker) => utcDay(Number(marker.t)) === targetDay);
    if (!dayMarkers.length) return '';

    const rows = dayMarkers.slice(0, 4).map((marker) => {
      const type = marker.type === 'decision' ? 'decision' : 'state';
      const icon = type === 'decision' ? '●' : '◆';
      const parts = [];
      if (type === 'decision') {
        parts.push('Decision Ledger');
        if (marker.bias) parts.push(localizeBias(marker.bias));
        if (Number.isFinite(Number(marker.confidence))) parts.push(`${Number(marker.confidence)}/100`);
      } else {
        parts.push('Tesis berubah');
        if (marker.bias) parts.push(localizeBias(marker.bias));
        if (marker.market_state) parts.push(localizeMarketState(marker.market_state));
      }
      return `<div class="bm-mc__tooltip-event is-${type}"><span aria-hidden="true">${icon}</span>${escapeHtml(parts.join(' · '))}</div>`;
    }).join('');

    return `<div class="bm-mc__tooltip-events"><b>Peristiwa Bitmomo</b>${rows}</div>`;
  }

  function keyboardSummary(targetT, normalized) {
    const values = normalized.map((series) => {
      const point = nearestPoint(series.points, targetT);
      if (!point) return '';
      return `${series.label} ${formatSigned(point.index - 100)} persen`;
    }).filter(Boolean).join(', ');
    return `${formatDate(targetT, '1y')}: ${values}.`;
  }

  function renderLegend(normalized) {
    explorer.legend.replaceChildren();
    normalized.forEach((series) => {
      const last = series.points[series.points.length - 1];
      const item = document.createElement('div');
      item.className = `bm-mc__legend-item is-${escapeToken(series.id)}`;
      item.title = `${series.provider} · ${series.cadence}`;
      item.innerHTML = [
        `<span><i></i>${escapeHtml(series.label)}</span>`,
        `<strong>${formatSigned(last.index - 100)}%</strong>`,
        `<small>${escapeHtml(formatActual(last.value, series.unit))} · ${escapeHtml(formatDate(last.t, '1y'))}</small>`
      ].join('');
      explorer.legend.appendChild(item);
    });
  }

  function syncControlMetrics(normalized) {
    const byId = new Map(normalized.map((series) => [series.id, series]));
    explorer.compareGroup.querySelectorAll('.bm-mc__series-button').forEach((button) => {
      const id = button.dataset.series;
      const meta = button.querySelector('[data-role="meta"]');
      if (!meta) return;
      const series = byId.get(id);
      if (series && state.active.has(id)) {
        const last = series.points[series.points.length - 1];
        meta.textContent = `${formatSigned(last.index - 100)}%`;
      }
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
    if (marker.market_state) parts.push(localizeMarketState(marker.market_state));
    if (marker.bias) parts.push(localizeBias(marker.bias));
    if (Number.isFinite(Number(marker.confidence))) parts.push(`Keyakinan ${Number(marker.confidence)}/100`);
    return parts.join(' · ');
  }

  function unavailableLabel() {
    return 'tidak tersedia';
  }

  function formatActual(value, unit) {
    if (!Number.isFinite(Number(value))) return '—';
    const number = Number(value);
    const formatted = new Intl.NumberFormat('en-US', { maximumFractionDigits: number >= 100 ? 0 : 2 }).format(number);
    if (unit === 'USD') return `$${formatted}`;
    if (unit === 'USD/oz') return `$${formatted}/oz`;
    return `${formatted} ${unit || ''}`.trim();
  }

  function formatIndex(value, step) {
    const decimals = Math.abs(step) < 1 ? 1 : Number.isInteger(step) ? 0 : 1;
    return Number(value).toFixed(decimals);
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

  function utcDay(timestamp) {
    if (!Number.isFinite(Number(timestamp))) return '';
    return new Date(Number(timestamp) * 1000).toISOString().slice(0, 10);
  }

  function localizeBias(value) {
    const labels = { bullish: 'Bullish', neutral: 'Netral', bearish: 'Bearish' };
    return labels[String(value || '').toLowerCase()] || humanize(value);
  }

  function localizeMarketState(value) {
    const labels = {
      accumulation: 'Akumulasi',
      expansion: 'Ekspansi',
      distribution: 'Distribusi',
      capitulation: 'Kapitulasi',
      transition: 'Transisi'
    };
    return labels[String(value || '').toLowerCase()] || humanize(value);
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