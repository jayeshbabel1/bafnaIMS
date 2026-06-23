<?php
/**
 * admin/views/_wa_share_modal.php  (v2 — PDF share)
 * ─────────────────────────────────────────────────────────────────────────
 * Reusable WhatsApp PDF Share Modal.
 * Activated via: openWaShare(productId, productName, quarryNo, thumbSrc)
 *
 * Flow:
 *  1. Modal opens → admin enters mobile + country code.
 *  2. On "Generate & Share":
 *     a. AJAX POST generates PDF server-side (?wa_pdf=1&product_id=N).
 *     b. On success: opens WhatsApp with pdf_url in message.
 *     c. Shows success state with direct PDF download link.
 * ─────────────────────────────────────────────────────────────────────────
 */
?>

<!-- ══════════════════════════════════════════════════════════════════════
     WhatsApp PDF Share Modal
     ══════════════════════════════════════════════════════════════════════ -->
<div id="waShareModal"
     style="display:none;position:fixed;inset:0;
            background:rgba(0,0,0,.6);
            z-index:9500;align-items:center;justify-content:center;padding:16px;">

  <div style="background:var(--surface);border-radius:var(--card-radius);
              width:100%;max-width:500px;
              box-shadow:0 24px 64px rgba(0,0,0,.25);
              overflow:hidden;animation:waModalIn .22s ease;">

    <!-- ── Header ──────────────────────────────────────────────────────── -->
    <div style="display:flex;align-items:center;justify-content:space-between;
                padding:16px 20px;border-bottom:1px solid var(--border);
                background:var(--surface2);">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:38px;height:38px;border-radius:10px;background:#e8faf0;
                    color:#25D366;display:flex;align-items:center;justify-content:center;">
          <?= icon('whatsapp', 19) ?>
        </div>
        <div>
          <p style="font-size:14px;font-weight:700;color:var(--text);">Share Product PDF</p>
          <p style="font-size:11px;color:var(--text3);" id="waProductSubtitle">via WhatsApp</p>
        </div>
      </div>
      <button onclick="closeWaShare()" type="button"
              style="color:var(--text3);cursor:pointer;padding:4px;border:none;
                     background:none;border-radius:6px;transition:background .15s;"
              onmouseenter="this.style.background='var(--surface3)'"
              onmouseleave="this.style.background='none'">
        <?= icon('close', 18) ?>
      </button>
    </div>

    <!-- ── Body ────────────────────────────────────────────────────────── -->
    <div style="padding:22px 22px 20px;" id="waModalBody">

      <!-- STEP 1: Input form -->
      <div id="waStep1">

        <!-- Product mini-preview -->
        <div style="display:flex;gap:12px;align-items:center;
                    padding:11px 14px;background:var(--surface2);
                    border-radius:10px;margin-bottom:20px;
                    border:1px solid var(--border);">
          <div id="waThumbWrap"
               style="width:46px;height:46px;border-radius:8px;overflow:hidden;
                      flex-shrink:0;background:var(--surface3);"></div>
          <div style="flex:1;min-width:0;">
            <p id="waProductName"
               style="font-size:13px;font-weight:700;color:var(--text);
                      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></p>
            <p id="waProductQuarry" style="font-size:11px;color:var(--text3);margin-top:2px;"></p>
          </div>
          <span class="badge" style="background:#e8faf0;color:#25D366;flex-shrink:0;">PDF</span>
        </div>

        <!-- Mobile number -->
        <div style="margin-bottom:16px;">
          <label class="admin-label" style="margin-bottom:7px;">
            Recipient Mobile Number <span style="color:var(--danger);">*</span>
          </label>
          <div style="display:flex;border:1.5px solid var(--border);
                      border-radius:8px;overflow:hidden;background:var(--surface);
                      transition:border-color .15s;" id="waInputWrap">
            <select id="waCountryCode"
                    style="border:none;outline:none;background:var(--surface2);
                           padding:10px 6px 10px 12px;font-size:13px;font-weight:600;
                           color:var(--text2);cursor:pointer;flex-shrink:0;
                           border-right:1px solid var(--border);font-family:inherit;
                           -webkit-appearance:none;appearance:none;
                           background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%238FA3B1' stroke-width='1.5' fill='none'/%3E%3C/svg%3E\");
                           background-repeat:no-repeat;background-position:right 4px center;
                           padding-right:20px;min-width:90px;">
              <option value="91">🇮🇳 +91</option>
              <option value="1">🇺🇸 +1</option>
              <option value="44">🇬🇧 +44</option>
              <option value="971">🇦🇪 +971</option>
              <option value="966">🇸🇦 +966</option>
              <option value="65">🇸🇬 +65</option>
              <option value="60">🇲🇾 +60</option>
              <option value="61">🇦🇺 +61</option>
              <option value="49">🇩🇪 +49</option>
              <option value="33">🇫🇷 +33</option>
              <option value="39">🇮🇹 +39</option>
              <option value="81">🇯🇵 +81</option>
              <option value="86">🇨🇳 +86</option>
            </select>
            <input type="tel" id="waMobileInput"
                   placeholder="Mobile number"
                   autocomplete="tel"
                   style="border:none;outline:none;flex:1;padding:10px 12px;
                          font-size:14px;color:var(--text);background:transparent;
                          font-family:inherit;min-width:0;"/>
          </div>
          <p id="waMobileError"
             style="display:none;font-size:11px;color:var(--danger);
                    margin-top:5px;display:none;align-items:center;gap:4px;">
            <?= icon('info', 11) ?> Please enter a valid mobile number.
          </p>
        </div>

        <!-- PDF info note -->
        <div style="background:var(--accent-light);border-radius:8px;padding:10px 13px;
                    margin-bottom:18px;display:flex;gap:10px;align-items:flex-start;
                    border:1px solid var(--border);">
          <span style="color:var(--accent);flex-shrink:0;margin-top:1px;"><?= icon('file', 14) ?></span>
          <p style="font-size:12px;color:var(--text2);line-height:1.5;">
            A branded PDF with product details and photo will be generated and
            a <strong>download link</strong> will be sent via WhatsApp.
            The PDF is available for <strong>1 hour</strong>.
          </p>
        </div>

        <!-- Action buttons -->
        <div style="display:flex;gap:10px;">
          <button type="button" onclick="doWaShare()" id="waShareBtn"
                  style="flex:1;display:inline-flex;align-items:center;justify-content:center;
                         gap:8px;padding:11px 16px;background:#25D366;color:#fff;
                         border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;
                         border:none;transition:background .15s,opacity .15s;">
            <?= icon('whatsapp', 16) ?> Generate &amp; Share
          </button>
          <button type="button" onclick="closeWaShare()"
                  class="btn-admin-secondary" style="flex-shrink:0;">
            Cancel
          </button>
        </div>

      </div><!-- /#waStep1 -->

      <!-- STEP 2: Generating spinner -->
      <div id="waStep2" style="display:none;text-align:center;padding:30px 0;">
        <div style="width:56px;height:56px;border:3px solid var(--border);
                    border-top-color:#25D366;border-radius:50%;
                    animation:waSpin .75s linear infinite;margin:0 auto 18px;"></div>
        <p style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px;">
          Generating PDF…
        </p>
        <p style="font-size:12px;color:var(--text3);">This takes just a moment.</p>
      </div>

      <!-- STEP 3: Success -->
      <div id="waStep3" style="display:none;text-align:center;padding:10px 0 6px;">
        <div style="width:56px;height:56px;border-radius:50%;background:#e8faf0;
                    color:#25D366;display:flex;align-items:center;justify-content:center;
                    margin:0 auto 14px;">
          <?= icon('check', 24) ?>
        </div>
        <p style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:5px;">
          WhatsApp Opened!
        </p>
        <p style="font-size:12px;color:var(--text3);margin-bottom:18px;line-height:1.5;">
          The PDF link has been placed in your WhatsApp message.<br/>
          Send it to complete sharing.
        </p>
        <a id="waPdfDownloadLink" href="#" target="_blank" download
           style="display:inline-flex;align-items:center;gap:7px;
                  padding:9px 18px;background:var(--surface2);
                  border:1.5px solid var(--border);border-radius:8px;
                  font-size:12px;font-weight:600;color:var(--text);
                  text-decoration:none;margin-bottom:16px;transition:background .15s;"
           onmouseenter="this.style.background='var(--surface3)'"
           onmouseleave="this.style.background='var(--surface2)'">
          <?= icon('download', 14) ?> Download PDF
        </a>
        <br/>
        <button type="button" onclick="closeWaShare()"
                style="font-size:12px;color:var(--text3);border:none;
                       background:none;cursor:pointer;text-decoration:underline;">
          Close
        </button>
      </div>

      <!-- STEP 4: Error -->
      <div id="waStep4" style="display:none;text-align:center;padding:10px 0 6px;">
        <div style="width:56px;height:56px;border-radius:50%;background:var(--danger-bg);
                    color:var(--danger);display:flex;align-items:center;justify-content:center;
                    margin:0 auto 14px;">
          <?= icon('info', 24) ?>
        </div>
        <p style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:5px;">
          PDF Generation Failed
        </p>
        <p id="waErrorMsg"
           style="font-size:12px;color:var(--text3);margin-bottom:16px;line-height:1.5;"></p>
        <div style="display:flex;gap:10px;justify-content:center;">
          <button type="button" onclick="waGoToStep(1)"
                  class="btn-admin-secondary btn-admin-sm">Try Again</button>
          <button type="button" onclick="closeWaShare()"
                  class="btn-admin-secondary btn-admin-sm">Close</button>
        </div>
      </div>

    </div><!-- /#waModalBody -->
  </div>
</div>
<!-- /waShareModal -->

<style>
@keyframes waModalIn {
  from { opacity:0; transform:scale(.95) translateY(8px); }
  to   { opacity:1; transform:scale(1)  translateY(0);    }
}
@keyframes waSpin { to { transform:rotate(360deg); } }
</style>

<script>
(function () {
  'use strict';

  var _productId = null;

  // ── Public: open ───────────────────────────────────────────────────────────
  window.openWaShare = function (productId, productName, quarryNo, thumbSrc) {
    _productId = productId;

    // Thumbnail
    var tw = document.getElementById('waThumbWrap');
    tw.innerHTML = '';
    if (thumbSrc) {
      var img = document.createElement('img');
      img.src = thumbSrc;
      img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
      tw.appendChild(img);
    }
    document.getElementById('waProductName').textContent    = productName || '';
    document.getElementById('waProductQuarry').textContent  = quarryNo ? 'Lot ' + quarryNo : '';
    document.getElementById('waProductSubtitle').textContent = productName || 'Product details';

    // Reset inputs / state
    document.getElementById('waMobileInput').value = '';
    document.getElementById('waMobileError').style.display  = 'none';
    document.getElementById('waInputWrap').style.borderColor = '';
    waGoToStep(1);

    document.getElementById('waShareModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(function () { document.getElementById('waMobileInput').focus(); }, 120);
  };

  // ── Public: close ──────────────────────────────────────────────────────────
  window.closeWaShare = function () {
    document.getElementById('waShareModal').style.display = 'none';
    document.body.style.overflow = '';
  };

  // Close on overlay click
  document.getElementById('waShareModal').addEventListener('click', function (e) {
    if (e.target === this) window.closeWaShare();
  });

  // ── Step navigation ────────────────────────────────────────────────────────
  window.waGoToStep = function (n) {
    [1,2,3,4].forEach(function (i) {
      var el = document.getElementById('waStep' + i);
      if (el) el.style.display = (i === n) ? '' : 'none';
    });
  };

  // ── Validation ─────────────────────────────────────────────────────────────
  function validateMobile(num, code) {
    var clean = num.replace(/[\s\-\(\)\.]+/g, '');
    if (!clean) return false;
    if (code === '91') return /^[6-9]\d{9}$/.test(clean);
    return /^\d{7,15}$/.test(clean);
  }

  function cleanNum(num) {
    return num.replace(/[\s\-\(\)\.]+/g, '');
  }

  // ── Input focus/blur styling ───────────────────────────────────────────────
  var mInp = document.getElementById('waMobileInput');
  if (mInp) {
    mInp.addEventListener('focus',  function () { document.getElementById('waInputWrap').style.borderColor = '#25D366'; });
    mInp.addEventListener('blur',   function () { document.getElementById('waInputWrap').style.borderColor = ''; });
    mInp.addEventListener('input',  function () { document.getElementById('waMobileError').style.display = 'none'; });
    mInp.addEventListener('keydown',function (e){ if (e.key === 'Enter') { e.preventDefault(); window.doWaShare(); } });
  }

  // ── Main share action ──────────────────────────────────────────────────────
  window.doWaShare = function () {
    var code   = document.getElementById('waCountryCode').value;
    var number = document.getElementById('waMobileInput').value.trim();
    var errEl  = document.getElementById('waMobileError');
    var wrap   = document.getElementById('waInputWrap');

    if (!validateMobile(number, code)) {
      errEl.style.display = 'flex';
      wrap.style.borderColor = 'var(--danger)';
      document.getElementById('waMobileInput').focus();
      return;
    }
    errEl.style.display = 'none';
    wrap.style.borderColor = '';

    var fullNumber = code + cleanNum(number);

    // Step 2: spinner
    waGoToStep(2);

    // AJAX — generate PDF
    fetch('index.php?wa_pdf=1&product_id=' + _productId)
      .then(function (r) {
        if (!r.ok) throw new Error('Server error: HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        if (!data.success) {
          throw new Error(data.error || 'PDF generation failed.');
        }

        var pdfUrl = data.url;
        var productName = document.getElementById('waProductName').textContent || 'Product';

        // Build WhatsApp message
        var msg = '*' + productName + ' — Product Details PDF*'
          + '\n\n' + pdfUrl
          + '\n\n_Tap the link above to view or download the full product PDF._'
          + '\n\nRegards,\n' + <?= json_encode(APP_NAME) ?>;

        var waUrl = 'https://wa.me/' + fullNumber + '?text=' + encodeURIComponent(msg);
        window.open(waUrl, '_blank', 'noopener,noreferrer');

        // Update download link
        document.getElementById('waPdfDownloadLink').href = pdfUrl;
        document.getElementById('waPdfDownloadLink').download = (data.filename || 'product.pdf');

        // Step 3: success
        waGoToStep(3);
      })
      .catch(function (err) {
        document.getElementById('waErrorMsg').textContent = err.message || 'Unknown error.';
        waGoToStep(4);
      });
  };

})();
</script>