<?php
/**
 * pages/_wa_share_modal_user.php
 * User-panel WhatsApp PDF share modal. Mirrors admin/views/_wa_share_modal.php
 * behavior (generate PDF -> wa.me link) using user-panel CSS classes.
 * Include from a page with $p (product array) and $id (product id) in scope.
 */
?>
<div id="waPdfShareModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9500;align-items:center;justify-content:center;padding:16px;">
  <div style="background:var(--white);border-radius:var(--radius-xl);width:100%;max-width:420px;box-shadow:var(--shadow-xl);overflow:hidden;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border);background:var(--gray-50);">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:10px;background:#e8faf0;color:#25D366;display:flex;align-items:center;justify-content:center;">
          <?= icon('whatsapp', 18) ?>
        </div>
        <p style="font-size:14px;font-weight:700;color:var(--text);">Share Product PDF</p>
      </div>
      <button onclick="closeWaPdfShare()" type="button" style="color:var(--text3);cursor:pointer;padding:4px;background:none;border:none;">
        <?= icon('close', 18) ?>
      </button>
    </div>
    <div style="padding:20px;">

      <div id="waPdfStep1">
        <label class="input-label">Recipient Mobile Number</label>
        <div style="display:flex;border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:6px;" id="waPdfInputWrap">
          <select id="waPdfCountryCode" style="border:none;outline:none;background:var(--gray-50);padding:0 8px;font-size:13px;font-weight:600;border-right:1px solid var(--border);font-family:inherit;flex-shrink:0;">
            <option value="91">🇮🇳 +91</option>
            <option value="1">🇺🇸 +1</option>
            <option value="44">🇬🇧 +44</option>
            <option value="971">🇦🇪 +971</option>
            <option value="61">🇦🇺 +61</option>
          </select>
          <input type="tel" id="waPdfMobileInput" placeholder="Mobile number" class="input-field" style="border:none;flex:1;min-height:44px;"/>
        </div>
        <p id="waPdfMobileError" style="display:none;font-size:11px;color:var(--danger);margin-bottom:12px;">Please enter a valid mobile number.</p>
        <button type="button" onclick="doWaPdfShare()" class="btn btn-block" style="background:#25D366;color:#fff;">
          <?= icon('whatsapp',15) ?>&nbsp; Generate &amp; Share
        </button>
      </div>

      <div id="waPdfStep2" style="display:none;text-align:center;padding:20px 0;">
        <div class="loader-spinner" style="margin:0 auto 14px;"></div>
        <p style="font-size:13px;color:var(--text3);">Generating PDF…</p>
      </div>

      <div id="waPdfStep3" style="display:none;text-align:center;padding:6px 0;">
        <div style="width:52px;height:52px;border-radius:50%;background:#e8faf0;color:#25D366;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
          <?= icon('check',22) ?>
        </div>
        <p style="font-weight:700;color:var(--text);margin-bottom:4px;">WhatsApp Opened!</p>
        <p style="font-size:12px;color:var(--text3);margin-bottom:16px;">Send the message to complete sharing.</p>
        <a id="waPdfDlLink" href="#" target="_blank" download class="btn btn-secondary btn-sm">
          <?= icon('download',13) ?>&nbsp; Download PDF
        </a>
      </div>

      <div id="waPdfStep4" style="display:none;text-align:center;padding:6px 0;">
        <p style="font-weight:700;color:var(--danger);margin-bottom:6px;">Generation Failed</p>
        <p id="waPdfErrMsg" style="font-size:12px;color:var(--text3);margin-bottom:14px;"></p>
        <button type="button" onclick="waPdfGoStep(1)" class="btn btn-secondary btn-sm">Try Again</button>
      </div>

    </div>
  </div>
</div>

<script>
(function () {
  var _pid  = <?= (int)$id ?>;
  var _name = <?= json_encode($p['name'] ?? '') ?>;

  window.openWaPdfShare = function () {
    document.getElementById('waPdfMobileInput').value = '';
    document.getElementById('waPdfMobileError').style.display = 'none';
    waPdfGoStep(1);
    document.getElementById('waPdfShareModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
  };
  window.closeWaPdfShare = function () {
    document.getElementById('waPdfShareModal').style.display = 'none';
    document.body.style.overflow = '';
  };
  window.waPdfGoStep = function (n) {
    [1,2,3,4].forEach(function (i) {
      var el = document.getElementById('waPdfStep' + i);
      if (el) el.style.display = (i === n) ? '' : 'none';
    });
  };

  function validateMobile(num, code) {
    var clean = num.replace(/[\s\-\(\)\.]+/g, '');
    if (!clean) return false;
    if (code === '91') return /^[6-9]\d{9}$/.test(clean);
    return /^\d{7,15}$/.test(clean);
  }

  window.doWaPdfShare = function () {
    var code   = document.getElementById('waPdfCountryCode').value;
    var number = document.getElementById('waPdfMobileInput').value.trim();
    var errEl  = document.getElementById('waPdfMobileError');
    if (!validateMobile(number, code)) {
      errEl.style.display = 'block';
      return;
    }
    errEl.style.display = 'none';
    var fullNumber = code + number.replace(/[\s\-\(\)\.]+/g, '');

    waPdfGoStep(2);
    fetch('index.php?wa_pdf=1&product_id=' + _pid)
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (data) {
        if (!data.success) throw new Error(data.error || 'PDF generation failed.');
        var msg = '*' + _name + ' — Product Details PDF*\n\n' + data.url +
          '\n\n_Tap the link above to view or download the full product PDF._' +
          '\n\nRegards,\n' + <?= json_encode(APP_NAME) ?>;
        window.open('https://wa.me/' + fullNumber + '?text=' + encodeURIComponent(msg), '_blank', 'noopener,noreferrer');
        document.getElementById('waPdfDlLink').href = data.url;
        document.getElementById('waPdfDlLink').download = data.filename || 'product.pdf';
        waPdfGoStep(3);
      })
      .catch(function (err) {
        document.getElementById('waPdfErrMsg').textContent = err.message || 'Unknown error.';
        waPdfGoStep(4);
      });
  };

  document.getElementById('waPdfShareModal').addEventListener('click', function (e) {
    if (e.target === this) closeWaPdfShare();
  });
})();
</script>