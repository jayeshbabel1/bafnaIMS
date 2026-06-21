<?php
/**
 * admin/views/_wa_share_modal.php
 * Reusable WhatsApp Share Modal — include once per page.
 * Activated via: openWaShare(productId, productName)
 */
?>

<!-- ── WhatsApp Share Modal ─────────────────────────────────────────────── -->
<div id="waShareModal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);
            z-index:9500;align-items:center;justify-content:center;padding:16px;">
  <div style="background:var(--surface);border-radius:var(--card-radius);
              width:100%;max-width:480px;box-shadow:0 16px 48px rgba(0,0,0,.2);
              overflow:hidden;">

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;
                padding:16px 20px;border-bottom:1px solid var(--border);
                background:var(--surface2);">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:10px;background:#e8faf0;
                    color:#25D366;display:flex;align-items:center;justify-content:center;">
          <?= icon('whatsapp', 18) ?>
        </div>
        <div>
          <p style="font-size:14px;font-weight:700;color:var(--text);">Share via WhatsApp</p>
          <p style="font-size:11px;color:var(--text3);" id="waProductSubtitle">Product details</p>
        </div>
      </div>
      <button onclick="closeWaShare()" type="button"
              style="color:var(--text3);cursor:pointer;padding:4px;border:none;background:none;">
        <?= icon('close', 18) ?>
      </button>
    </div>

    <!-- Body -->
    <div style="padding:20px;">

      <!-- Product preview (mini) -->
      <div id="waProductPreview"
           style="display:flex;gap:10px;align-items:center;padding:10px 12px;
                  background:var(--surface2);border-radius:8px;margin-bottom:18px;
                  border:1px solid var(--border);">
        <div id="waThumbWrap"
             style="width:44px;height:44px;border-radius:6px;overflow:hidden;
                    flex-shrink:0;background:var(--surface3);">
        </div>
        <div style="flex:1;min-width:0;">
          <p id="waProductName" style="font-size:13px;font-weight:600;color:var(--text);
             white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></p>
          <p id="waProductQuarry" style="font-size:11px;color:var(--text3);margin-top:1px;"></p>
        </div>
      </div>

      <!-- Mobile input -->
      <div style="margin-bottom:16px;">
        <label class="admin-label" style="margin-bottom:6px;">
          Recipient Mobile Number <span style="color:var(--danger);">*</span>
        </label>
        <div style="display:flex;gap:0;border:1.5px solid var(--border);border-radius:8px;
                    overflow:hidden;background:var(--surface);
                    transition:border-color .15s;" id="waInputWrap">
          <!-- Country code selector -->
          <select id="waCountryCode"
                  style="border:none;outline:none;background:var(--surface2);
                         padding:10px 8px 10px 12px;font-size:13px;font-weight:600;
                         color:var(--text2);cursor:pointer;flex-shrink:0;
                         border-right:1px solid var(--border);font-family:inherit;
                         -webkit-appearance:none;appearance:none;
                         background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%238FA3B1' stroke-width='1.5' fill='none'/%3E%3C/svg%3E\");
                         background-repeat:no-repeat;background-position:right 6px center;
                         padding-right:22px;min-width:80px;">
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
           style="display:none;font-size:11px;color:var(--danger);margin-top:5px;">
          <?= icon('info', 11) ?> Please enter a valid mobile number.
        </p>
      </div>

      <!-- Message preview (collapsible) -->
      <div style="margin-bottom:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;
                    margin-bottom:6px;">
          <label class="admin-label" style="margin-bottom:0;">Message Preview</label>
          <button type="button" onclick="toggleWaPreview()"
                  style="font-size:11px;font-weight:600;color:var(--accent);
                         border:none;background:none;cursor:pointer;"
                  id="waPreviewToggle">Hide</button>
        </div>
        <div id="waMessagePreview"
             style="background:var(--surface2);border:1px solid var(--border);
                    border-radius:8px;padding:12px;font-size:12px;
                    color:var(--text2);white-space:pre-wrap;line-height:1.6;
                    max-height:200px;overflow-y:auto;font-family:monospace;">
          Loading…
        </div>
      </div>

      <!-- Action buttons -->
      <div style="display:flex;gap:10px;">
        <button type="button" onclick="doWaShare()"
                style="flex:1;display:inline-flex;align-items:center;justify-content:center;
                       gap:8px;padding:11px 18px;background:#25D366;color:#fff;
                       border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;
                       border:none;transition:background .15s;" id="waShareBtn">
          <?= icon('whatsapp', 16) ?> Send on WhatsApp
        </button>
        <button type="button" onclick="closeWaShare()"
                class="btn-admin-secondary" style="flex-shrink:0;">
          Cancel
        </button>
      </div>

    </div><!-- /body -->
  </div>
</div>
<!-- /waShareModal -->

<script>
(function () {
  'use strict';

  var _productId   = null;
  var _previewOpen = true;

  // ── Open modal ─────────────────────────────────────────────────────────────
  window.openWaShare = function (productId, productName, quarryNo, thumbSrc) {
    _productId = productId;

    // Populate preview thumbnail
    var thumbWrap = document.getElementById('waThumbWrap');
    thumbWrap.innerHTML = '';
    if (thumbSrc) {
      var img = document.createElement('img');
      img.src = thumbSrc;
      img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
      thumbWrap.appendChild(img);
    }

    document.getElementById('waProductName').textContent   = productName || '';
    document.getElementById('waProductQuarry').textContent = quarryNo    ? 'Lot ' + quarryNo : '';
    document.getElementById('waProductSubtitle').textContent = productName || 'Product details';

    document.getElementById('waMobileInput').value = '';
    document.getElementById('waMobileError').style.display = 'none';
    document.getElementById('waInputWrap').style.borderColor = '';

    // Build message preview
    buildPreview(productId);

    var modal = document.getElementById('waShareModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(function () {
      document.getElementById('waMobileInput').focus();
    }, 120);
  };

  // ── Close modal ────────────────────────────────────────────────────────────
  window.closeWaShare = function () {
    document.getElementById('waShareModal').style.display = 'none';
    document.body.style.overflow = '';
  };

  // Close on overlay click
  document.getElementById('waShareModal').addEventListener('click', function (e) {
    if (e.target === this) window.closeWaShare();
  });

  // ── Toggle message preview ─────────────────────────────────────────────────
  window.toggleWaPreview = function () {
    var el = document.getElementById('waMessagePreview');
    var btn = document.getElementById('waPreviewToggle');
    if (_previewOpen) {
      el.style.display = 'none';
      btn.textContent  = 'Show';
      _previewOpen = false;
    } else {
      el.style.display = '';
      btn.textContent  = 'Hide';
      _previewOpen = true;
    }
  };

  // ── Build message preview via AJAX ─────────────────────────────────────────
  function buildPreview(productId) {
    var el = document.getElementById('waMessagePreview');
    el.textContent = 'Loading…';
    fetch('index.php?wa_preview=1&product_id=' + productId)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        el.textContent = d.message || '—';
      })
      .catch(function () {
        el.textContent = 'Unable to load preview.';
      });
  }

  // ── Validate mobile ────────────────────────────────────────────────────────
  function validateMobile(num, code) {
    var clean = num.replace(/[\s\-\(\)\.]+/g, '');
    if (!clean) return false;
    // Indian numbers: must be 10 digits starting 6-9
    if (code === '91') return /^[6-9]\d{9}$/.test(clean);
    // Generic: 7-15 digits
    return /^\d{7,15}$/.test(clean);
  }

  // ── Focus / blur styling ───────────────────────────────────────────────────
  var mobileInp = document.getElementById('waMobileInput');
  if (mobileInp) {
    mobileInp.addEventListener('focus', function () {
      document.getElementById('waInputWrap').style.borderColor = '#25D366';
    });
    mobileInp.addEventListener('blur', function () {
      document.getElementById('waInputWrap').style.borderColor = '';
    });
    mobileInp.addEventListener('input', function () {
      document.getElementById('waMobileError').style.display = 'none';
      document.getElementById('waInputWrap').style.borderColor = '#25D366';
    });
    // Enter key triggers share
    mobileInp.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); window.doWaShare(); }
    });
  }

  // ── Perform share ──────────────────────────────────────────────────────────
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

    var clean = number.replace(/[\s\-\(\)\.]+/g, '');
    var fullNumber = code + clean;

    // Fetch the real message text + image URL from server
    fetch('index.php?wa_preview=1&product_id=' + _productId)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var msg    = ('Product Photo:' + d.image_url ? '\n\n' + d.image_url : '')+ ('\n'+ d.message || '') ;
        var waUrl  = 'https://wa.me/' + fullNumber + '?text=' + encodeURIComponent(msg);
        window.open(waUrl, '_blank', 'noopener,noreferrer');

        // Flash success
        var btn = document.getElementById('waShareBtn');
        var orig = btn.innerHTML;
        btn.innerHTML = '<?= icon('check', 16) ?> Opened WhatsApp!';
        btn.style.background = 'var(--success)';
        setTimeout(function () {
          btn.innerHTML = orig;
          btn.style.background = '#25D366';
          window.closeWaShare();
        }, 1800);
      })
      .catch(function () {
        alert('Could not load product data. Please try again.');
      });
  };

})();
</script>