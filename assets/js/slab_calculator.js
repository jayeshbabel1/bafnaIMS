/**
 * assets/js/slab_calculator.js — shared modal, instant client calc.
 * Formula mirrors includes/slab_calculator.php::calcSlabArea() exactly
 * (base unit = feet) so results never disagree. Nothing persisted server-
 * side, so no submit — client calc is safe/sufficient for this UX.
 */
(function () {
  'use strict';
  var UNIT_TO_FEET = { ft: 1, in: 1/12, mm: 1/304.8, cm: 1/30.48, m: 3.28084 };
  function toFeet(v, u) { return v * (UNIT_TO_FEET[u] || 1); }
  function round2(n) { return Math.round(n * 100) / 100; }

  function calc(vals) {
    var length = parseFloat(vals.length), width = parseFloat(vals.width);
    var qty = parseInt(vals.quantity, 10), wastage = parseFloat(vals.wastage);
    var unit = vals.unit || 'ft';
    if (!isFinite(length) || length <= 0) return { error: 'Enter a valid length.' };
    if (!isFinite(width)  || width  <= 0) return { error: 'Enter a valid width.' };
    if (!isFinite(qty) || qty < 1)        return { error: 'Enter a valid quantity.' };
    if (!isFinite(wastage) || wastage < 0 || wastage > 100) return { error: 'Wastage must be 0–100%.' };
    var lengthFt = toFeet(length, unit), widthFt = toFeet(width, unit);
    var areaPerSlab = lengthFt * widthFt;
    var totalArea = areaPerSlab * qty;
    var wastageArea = totalArea * (wastage / 100);
    return {
      areaPerSlab: round2(areaPerSlab), totalArea: round2(totalArea),
      wastageArea: round2(wastageArea), requiredArea: round2(totalArea + wastageArea),
    };
  }

  var modalEl = null;

  function buildModal() {
    if (modalEl) return modalEl;
    var wrap = document.createElement('div');
    wrap.id = 'slabCalcModal';
    wrap.className = 'modal-overlay';
    wrap.innerHTML =
      '<div class="modal-sheet" style="max-width:420px;">' +
        '<div class="modal-handle"></div>' +
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">' +
          '<p style="font-family:var(--font-display);font-size:17px;font-weight:700;">Slab Calculator</p>' +
          '<button type="button" id="slabCalcClose" style="color:var(--text3);padding:4px;cursor:pointer;background:none;border:none;">&times;</button>' +
        '</div>' +
        '<div class="input-group"><label class="input-label">Length</label>' +
          '<input type="number" id="scLength" class="input-field" min="0" step="0.01" placeholder="e.g. 10"/></div>' +
        '<div class="input-group"><label class="input-label">Width</label>' +
          '<input type="number" id="scWidth" class="input-field" min="0" step="0.01" placeholder="e.g. 5"/></div>' +
        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">' +
          '<div class="input-group"><label class="input-label">Unit</label>' +
            '<select id="scUnit" class="input-field">' +
              '<option value="ft">Feet</option><option value="in">Inches</option>' +
              '<option value="mm">Millimeters</option><option value="cm">Centimeters</option>' +
              '<option value="m">Meters</option></select></div>' +
          '<div class="input-group"><label class="input-label">Quantity</label>' +
            '<input type="number" id="scQty" class="input-field" min="1" step="1" value="1"/></div>' +
        '</div>' +
        '<div class="input-group"><label class="input-label">Wastage %</label>' +
          '<input type="number" id="scWastage" class="input-field" min="0" max="100" step="0.1" value="0"/></div>' +
        '<p id="scError" style="display:none;font-size:12px;color:var(--danger);margin:-6px 0 12px;"></p>' +
        '<div style="background:var(--gray-50);border-radius:var(--radius);padding:14px 16px;margin-top:6px;display:flex;flex-direction:column;gap:8px;">' +
          '<div style="display:flex;justify-content:space-between;font-size:13px;"><span style="color:var(--text3);">Area / Slab</span><strong id="scAreaPerSlab">—</strong></div>' +
          '<div style="display:flex;justify-content:space-between;font-size:13px;"><span style="color:var(--text3);">Total Area</span><strong id="scTotalArea">—</strong></div>' +
          '<div style="display:flex;justify-content:space-between;font-size:13px;"><span style="color:var(--text3);">Wastage</span><strong id="scWastageArea">—</strong></div>' +
          '<div style="display:flex;justify-content:space-between;font-size:14px;padding-top:8px;border-top:1px solid var(--border);"><span style="font-weight:700;">Required Area</span><strong id="scRequiredArea" style="color:var(--black);">—</strong></div>' +
        '</div>' +
        '<div style="display:flex;gap:10px;margin-top:18px;"><button type="button" id="scReset" class="btn btn-secondary" style="flex:1;">Reset</button></div>' +
      '</div>';
    document.body.appendChild(wrap);
    modalEl = wrap;

    wrap.querySelector('#slabCalcClose').addEventListener('click', closeSlabCalculator);
    wrap.addEventListener('click', function (e) { if (e.target === wrap) closeSlabCalculator(); });
    ['scLength','scWidth','scUnit','scQty','scWastage'].forEach(function (id) {
      wrap.querySelector('#' + id).addEventListener('input', runCalc);
      wrap.querySelector('#' + id).addEventListener('change', runCalc);
    });
    wrap.querySelector('#scReset').addEventListener('click', function () {
      wrap.querySelector('#scLength').value = '';
      wrap.querySelector('#scWidth').value = '';
      wrap.querySelector('#scQty').value = '1';
      wrap.querySelector('#scWastage').value = window.SLAB_CALC_DEFAULT_WASTAGE || '0';
      wrap.querySelector('#scUnit').value = 'ft';
      runCalc();
    });
    return wrap;
  }

  function runCalc() {
    var vals = {
      length: modalEl.querySelector('#scLength').value,
      width: modalEl.querySelector('#scWidth').value,
      unit: modalEl.querySelector('#scUnit').value,
      quantity: modalEl.querySelector('#scQty').value,
      wastage: modalEl.querySelector('#scWastage').value,
    };
    var errEl = modalEl.querySelector('#scError');
    var out = calc(vals);
    if (out.error) {
      errEl.textContent = out.error; errEl.style.display = 'block';
      ['scAreaPerSlab','scTotalArea','scWastageArea','scRequiredArea'].forEach(function (id) {
        modalEl.querySelector('#' + id).textContent = '—';
      });
      return;
    }
    errEl.style.display = 'none';
    modalEl.querySelector('#scAreaPerSlab').textContent = out.areaPerSlab.toFixed(2) + ' Sq.Ft.';
    modalEl.querySelector('#scTotalArea').textContent = out.totalArea.toFixed(2) + ' Sq.Ft.';
    modalEl.querySelector('#scWastageArea').textContent = out.wastageArea.toFixed(2) + ' Sq.Ft.';
    modalEl.querySelector('#scRequiredArea').textContent = out.requiredArea.toFixed(2) + ' Sq.Ft.';
  }

  window.openSlabCalculator = function (opts) {
    opts = opts || {};
    var wrap = buildModal();
    wrap.querySelector('#scLength').value = opts.length || '';
    wrap.querySelector('#scWidth').value  = opts.width  || '';
    wrap.querySelector('#scUnit').value   = opts.unit   || 'in';
    wrap.querySelector('#scQty').value    = opts.quantity || 1;
    wrap.querySelector('#scWastage').value = (opts.wastage != null ? opts.wastage : (window.SLAB_CALC_DEFAULT_WASTAGE || 0));
    wrap.classList.add('open');
    document.body.style.overflow = 'hidden';
    runCalc();
  };
  window.closeSlabCalculator = function () {
    if (!modalEl) return;
    modalEl.classList.remove('open');
    document.body.style.overflow = '';
  };
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modalEl && modalEl.classList.contains('open')) closeSlabCalculator();
  });
})();