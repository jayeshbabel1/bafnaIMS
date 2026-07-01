<?php
/**
 * admin/views/logo.php — Company Profile (logo + business details)
 */
$adminTitle = 'Company Profile';
requireAdminPermission('settings.logo');
include __DIR__ . '/../_layout_top.php';
require_once BASE_PATH . '/includes/logo.php';
$currentLogo = getLogo(true);

// Load saved company profile values
$cp = [
    'company_name'          => getSetting('company_name',          APP_NAME),
    'company_short_name'    => getSetting('company_short_name',    ''),
    'company_tagline'       => getSetting('company_tagline',       ''),
    'company_address'       => getSetting('company_address',       ''),
    'company_gst'           => getSetting('company_gst',           ''),
    'company_whatsapp'      => getSetting('company_whatsapp',      ''),
    'company_support_phone' => getSetting('company_support_phone', ''),
    'company_email'         => getSetting('company_email',         ''),
    'company_location_url'  => getSetting('company_location_url',  ''),
];
?>

<style>
/* Company Profile responsive grid */
.cp-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
}
@media (min-width: 640px) {
    .cp-grid { grid-template-columns: 1fr 1fr; }
}
@media (min-width: 1024px) {
    .cp-grid { grid-template-columns: 1fr 1fr 1fr; }
}
.cp-full { grid-column: 1 / -1; }

.cp-hint {
    font-size: 11px;
    color: var(--text3);
    margin-top: 4px;
    line-height: 1.5;
    display: flex;
    align-items: flex-start;
    gap: 5px;
}
.logo-mgmt-wrap { max-width: 860px; }

/* Mobile-friendly upload zone */
@media (max-width: 639px) {
    .logo-upload-zone { padding: 20px 14px; }
    .logo-form-actions { flex-direction: column; }
    .logo-form-actions button,
    .logo-form-actions form { width: 100%; }
    .logo-form-actions form button { width: 100%; justify-content: center; }
}
</style>

<div class="logo-mgmt-wrap">

  <!-- ══ SECTION 1: Company Profile ══════════════════════════════════════ -->
  <form method="POST" action="index.php" id="companyProfileForm">
    <input type="hidden" name="action" value="save_company_profile"/>
<?= csrfField() ?>
    <div class="admin-form-section">
      <p class="admin-form-section-title">Company Information</p>
      <p style="font-size:12px;color:var(--text3);margin-bottom:16px;line-height:1.6;">
        This information is displayed on the user-facing Support page, PDF headers, and WhatsApp messages.
      </p>

      <div class="cp-grid">

        <!-- Company Name -->
        <div>
          <label class="admin-label">Company Name <span style="color:var(--danger)">*</span></label>
          <input type="text" name="company_name" class="admin-input"
                 value="<?= h($cp['company_name']) ?>"
                 placeholder="Bafna Marble & Granites"/>
          <p class="cp-hint"><?= icon('info',11) ?> Shown in page titles and email footers.</p>
        </div>

        <!-- Short Name -->
        <div>
          <label class="admin-label">Short Name / Brand</label>
          <input type="text" name="company_short_name" class="admin-input"
                 value="<?= h($cp['company_short_name']) ?>"
                 placeholder="Bafna Marble"/>
          <p class="cp-hint"><?= icon('info',11) ?> Used in compact spaces like navbar.</p>
        </div>

        <!-- Tagline -->
        <div>
          <label class="admin-label">Tagline / Slogan</label>
          <input type="text" name="company_tagline" class="admin-input"
                 value="<?= h($cp['company_tagline']) ?>"
                 placeholder="Premium Stone Catalog Platform"/>
          <p class="cp-hint"><?= icon('info',11) ?> Shown on login page and support section.</p>
        </div>

        <!-- GST Number -->
        <div>
          <label class="admin-label">GST Number</label>
          <input type="text" name="company_gst" class="admin-input"
                 value="<?= h($cp['company_gst']) ?>"
                 placeholder="24AABCB1234A1Z5"
                 maxlength="15" style="font-family:monospace;letter-spacing:.5px;"/>
          <p class="cp-hint"><?= icon('info',11) ?> 15-digit GST Identification Number.</p>
        </div>

        <!-- WhatsApp -->
        <div>
          <label class="admin-label">WhatsApp Mobile</label>
          <div style="display:flex;border:1.5px solid var(--admin-input-border,var(--border));border-radius:var(--admin-input-radius,8px);overflow:hidden;background:var(--admin-input-bg,#fff);">
            <span style="padding:9px 10px;background:var(--surface2);border-right:1px solid var(--border);font-size:13px;color:var(--text2);flex-shrink:0;">+91</span>
            <input type="tel" name="company_whatsapp" class="admin-input"
                   value="<?= h($cp['company_whatsapp']) ?>"
                   placeholder="9898074441" maxlength="10"
                   style="border:none;border-radius:0;"/>
          </div>
          <p class="cp-hint"><?= icon('info',11) ?> Used for "Share on WhatsApp" links on user panel.</p>
        </div>

        <!-- Support Phone -->
        <div>
          <label class="admin-label">Support Mobile No.</label>
          <div style="display:flex;border:1.5px solid var(--admin-input-border,var(--border));border-radius:var(--admin-input-radius,8px);overflow:hidden;background:var(--admin-input-bg,#fff);">
            <span style="padding:9px 10px;background:var(--surface2);border-right:1px solid var(--border);font-size:13px;color:var(--text2);flex-shrink:0;">+91</span>
            <input type="tel" name="company_support_phone" class="admin-input"
                   value="<?= h($cp['company_support_phone']) ?>"
                   placeholder="9898074441" maxlength="10"
                   style="border:none;border-radius:0;"/>
          </div>
          <p class="cp-hint"><?= icon('info',11) ?> Shown on Support page under "Call Us".</p>
        </div>

        <!-- Email -->
        <div>
          <label class="admin-label">Support Email Address</label>
          <input type="email" name="company_email" class="admin-input"
                 value="<?= h($cp['company_email']) ?>"
                 placeholder="sales@bafnamarbles.com"/>
          <p class="cp-hint"><?= icon('info',11) ?> Shown on Support page under "Email Us".</p>
        </div>

        <!-- Google Location URL -->
        <div class="cp-full">
          <label class="admin-label">Google Maps Location URL</label>
          <input type="url" name="company_location_url" class="admin-input"
                 value="<?= h($cp['company_location_url']) ?>"
                 placeholder="https://maps.app.goo.gl/..."/>
          <p class="cp-hint"><?= icon('info',11) ?> Paste your Google Maps share link. Displayed as "View on Google Maps" on the Support page.</p>
        </div>

        <!-- Address -->
        <div class="cp-full">
          <label class="admin-label">Full Address</label>
          <textarea name="company_address" class="admin-input" rows="3"
                    placeholder="Block No.40, Near Puniya Bhumi, Second VIP Road, Surat-395007, Gujarat, India"><?= h($cp['company_address']) ?></textarea>
          <p class="cp-hint"><?= icon('info',11) ?> Shown on Support page and in PDF footers.</p>
        </div>

      </div><!-- /.cp-grid -->

      <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
        <button type="submit" class="btn-admin-primary">
          <?= icon('check',15) ?> Save Company Profile
        </button>
      </div>
    </div>

    <!-- Guidelines -->
    <div class="admin-form-section" style="background:var(--accent-light);border-color:var(--accent-mid);">
      <p class="admin-form-section-title" style="color:var(--accent2);">
        <?= icon('info',14) ?> Company Profile Guidelines
      </p>
      <ul style="display:flex;flex-direction:column;gap:8px;list-style:none;">
        <li style="display:flex;align-items:flex-start;gap:8px;font-size:12px;color:var(--text2);">
          <?= icon('check',12) ?> <span><strong>Company Name</strong> is required — it appears across the entire platform.</span>
        </li>
        <li style="display:flex;align-items:flex-start;gap:8px;font-size:12px;color:var(--text2);">
          <?= icon('check',12) ?> <span><strong>WhatsApp &amp; Support Phone</strong> — enter 10-digit number without country code (+91 is added automatically).</span>
        </li>
        <li style="display:flex;align-items:flex-start;gap:8px;font-size:12px;color:var(--text2);">
          <?= icon('check',12) ?> <span><strong>Google Maps URL</strong> — open Google Maps, find your business, tap Share → Copy link, and paste here.</span>
        </li>
        <li style="display:flex;align-items:flex-start;gap:8px;font-size:12px;color:var(--text2);">
          <?= icon('check',12) ?> <span><strong>GST Number</strong> — 15-character alphanumeric as issued by GST portal.</span>
        </li>
        <li style="display:flex;align-items:flex-start;gap:8px;font-size:12px;color:var(--text2);">
          <?= icon('info',12) ?> <span>Changes saved here reflect immediately on the Support page and PDF exports.</span>
        </li>
      </ul>
    </div>

  </form>

  <!-- ══ SECTION 2: Logo ══════════════════════════════════════════════════ -->

  <!-- Current logo card -->
  <div class="admin-form-section logo-preview-section">
    <p class="admin-form-section-title">Site Logo</p>
    <div class="logo-current-preview">
      <?php if ($currentLogo): ?>
        <img src="<?= h($currentLogo) ?>" alt="Site Logo" class="logo-preview-img" id="logoPreviewCurrent"/>
      <?php else: ?>
        <div class="logo-placeholder" id="logoPreviewCurrent">
          <svg width="48" height="48" viewBox="0 0 36 36" fill="none">
            <polygon points="18,4 32,28 4,28" fill="rgba(44,110,138,.18)" stroke="rgba(44,110,138,.9)" stroke-width="1.5"/>
            <polygon points="18,10 26,24 10,24" fill="rgba(44,110,138,.35)" stroke="rgba(44,110,138,.7)" stroke-width="1"/>
          </svg>
          <p class="logo-placeholder-text">No logo uploaded — using default</p>
        </div>
      <?php endif; ?>
    </div>
    <div class="logo-meta">
      <span class="badge badge-gray">Displayed in: Admin Panel · User Navbar · Login Page · All Pages</span>
    </div>
  </div>

  <!-- Upload form -->
  <div class="admin-form-section">
    <p class="admin-form-section-title">Upload New Logo</p>

    <form method="POST" action="index.php" enctype="multipart/form-data" id="logoUploadForm">
      <input type="hidden" name="action" value="upload_logo"/>
      <?= csrfField() ?>
      <div class="logo-upload-zone" id="logoDropZone">
        <input type="file" name="logo_file" id="logoFileInput" accept=".png,.jpg,.jpeg,.webp" class="logo-file-input"/>
        <div class="logo-upload-inner" id="logoUploadInner">
          <?= icon('image', 32) ?>
          <p class="logo-upload-title">Click or drag to upload logo</p>
          <p class="logo-upload-hint">PNG, JPG, JPEG, WEBP — max 2 MB</p>
        </div>
        <img src="" alt="Preview" class="logo-new-preview" id="logoNewPreview"/>
      </div>

      <p class="logo-selected-name" id="logoSelectedName"></p>

      <div class="logo-form-actions">
        <button type="submit" class="btn-admin-primary" id="logoSubmitBtn" disabled>
          <?= icon('upload', 16) ?> Upload &amp; Save Logo
        </button>
        <?php if ($currentLogo): ?>
        <form method="POST" action="index.php" style="display:inline;" id="logoRemoveForm">
          <input type="hidden" name="action" value="remove_logo"/>
          <?= csrfField() ?>
          <button type="submit" class="btn-admin-danger btn-admin-sm"
                  data-confirm="Remove logo and use default?"><?= icon('trash', 13) ?> Remove Logo</button>
        </form>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Logo Guidelines -->
  <div class="admin-form-section logo-tips-section">
    <p class="admin-form-section-title">Logo Guidelines</p>
    <ul class="logo-tips-list">
      <li><?= icon('check', 13) ?> Recommended: <strong>SVG or transparent PNG</strong> for crisp display on all backgrounds</li>
      <li><?= icon('check', 13) ?> Ideal dimensions: <strong>200 × 60 px</strong> or wider at 2× for retina</li>
      <li><?= icon('check', 13) ?> Max file size: <strong>2 MB</strong></li>
      <li><?= icon('check', 13) ?> Accepted formats: PNG, JPG, JPEG, WEBP</li>
      <li><?= icon('info', 13) ?> Uploading a new logo automatically replaces the previous one</li>
    </ul>
  </div>

</div><!-- .logo-mgmt-wrap -->

<?php include __DIR__ . '/../_layout_bottom.php'; ?>