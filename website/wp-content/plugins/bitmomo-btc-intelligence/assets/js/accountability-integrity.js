(() => {
  'use strict';

  const config = window.BitmomoAccountabilityIntegrity || {};
  const root = document.querySelector('.bm-bi');
  if (!root) return;

  const rows = Array.isArray(config.ledgerRows) ? config.ledgerRows : [];
  const tableRows = [...root.querySelectorAll('#decision-ledger .bm-bi__ledger-table tbody tr')];

  tableRows.forEach((tr, index) => {
    const row = rows[index];
    const firstCell = tr.querySelector('td:first-child');
    if (!row || !firstCell || firstCell.querySelector('.bm-bi__ledger-method')) return;

    const session = firstCell.querySelector('small');
    if (session) session.textContent = publicSessionLabel(session.textContent || '');

    const method = document.createElement('small');
    method.className = 'bm-bi__ledger-method';
    method.textContent = friendlyMethod(row.outcomeMethodology || '');
    firstCell.appendChild(method);
  });

  const ledger = root.querySelector('#decision-ledger');
  const ledgerIntro = ledger ? ledger.querySelector('.bm-bi__section-intro') : null;
  if (ledgerIntro && !ledger.querySelector('.bm-bi__population-note')) {
    const note = document.createElement('p');
    note.className = 'bm-bi__population-note';
    const total = Number(config.ledgerTotal || rows.length || 0);
    const evaluated = Number(config.ledgerEvaluated || 0);
    const missed = Number(config.ledgerUnscored || 0);
    note.textContent = `Populasi Ledger: ${total} catatan matang terbaru lintas versi (${evaluated} sudah dievaluasi${missed ? `; ${missed} periode evaluasi terlewat` : ''}). Tidak ada filter berdasarkan hasil.`;
    ledgerIntro.insertAdjacentElement('afterend', note);

    const coverage = document.createElement('p');
    coverage.className = 'bm-bi__population-note bm-bi__coverage-note';
    coverage.textContent = 'Ledger hanya menampilkan brief recorded-live yang benar-benar tercatat. Jadwal yang tidak menghasilkan record tidak diisi ulang secara retrospektif; kekosongan historis tetap diperlakukan sebagai gap data, bukan keputusan pasar.';
    note.insertAdjacentElement('afterend', coverage);
  }

  const track = root.querySelector('.bm-bi__track-record');
  const trackIntro = track ? track.querySelector('.bm-bi__section-intro') : null;
  if (trackIntro && !track.querySelector('.bm-bi__population-note')) {
    const note = document.createElement('p');
    note.className = 'bm-bi__population-note is-scorecard';
    const n = Number(config.scorecardN || 0);
    const method = friendlyMethod(config.scorecardMethodology || '');
    note.textContent = `Populasi Scorecard: ${n} catatan berstatus evaluated dari satu versi metodologi yang kompatibel (${method}). Karena Ledger dan Scorecard memiliki aturan populasi berbeda, jumlah baris keduanya tidak harus sama.`;
    trackIntro.insertAdjacentElement('afterend', note);
  }

  const methodology = root.querySelector('.bm-bi__methodology .bm-bi__details-body');
  if (methodology && !methodology.querySelector('.bm-bi__neutral-rule')) {
    const rule = document.createElement('p');
    rule.className = 'bm-bi__neutral-rule';
    rule.textContent = 'Aturan hasil +24 jam: Bullish dinilai sesuai pada ≥ +0,5% dan Bearish pada ≤ −0,5%; gerak di antara −0,5% dan +0,5% tidak konklusif untuk keduanya. Netral dinilai sesuai hanya bila perubahan tetap di dalam rentang −0,5% sampai +0,5%; di luar rentang itu dinilai tidak sesuai.';
    methodology.appendChild(rule);
  }

  function friendlyMethod(method) {
    const value = String(method || '').toLowerCase();
    if (value === 'observed-close-24h-v2') return 'Metode hasil +24j v2';
    if (value === 'legacy-window-v1' || !value) return 'Metode hasil +24j legacy';
    return `Metode ${String(method).replace(/[-_]+/g, ' ')}`;
  }

  function publicSessionLabel(label) {
    const value = String(label || '').trim().toLowerCase();
    if (value === 'us session') return 'US POST-CLOSE · legacy';
    if (value === 'us post-close' || value === 'us post close') return 'US POST-CLOSE';
    if (value === 'us pre-open' || value === 'us pre open') return 'US PRE-OPEN';
    if (value === 'morning') return 'US PRE-OPEN · legacy';
    return label;
  }
})();
