<?php
/**
 * admin/views/logo.php — Task 1: Logo Management
 */
$adminTitle = 'Logo Management';
include __DIR__ . '/../_layout_top.php';
require_once BASE_PATH . '/includes/logo.php';
$currentLogo = getLogo(true);
?>

<div class="logo-mgmt-wrap">

  <!-- Current logo card -->
  <div class="admin-form-section logo-preview-section">
    <p class="admin-form-section-title">Current Logo</p>
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

      <div class="logo-upload-zone" id="logoDropZone">
        <input type="file" name="logo_file" id="logoFileInput" accept=".png,.jpg,.jpeg,.webp" class="logo-file-input"/>
        <div class="logo-upload-inner" id="logoUploadInner">
          <?= icon('image', 32) ?>
          <p class="logo-upload-title">Click or drag to upload logo</p>
          <p class="logo-upload-hint">PNG, JPG, JPEG, WEBP — max 2 MB</p>
        </div>
        <!-- Preview of newly selected file -->
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
          <button type="submit" class="btn-admin-danger btn-admin-sm"
                  data-confirm="Remove logo and use default?"><?= icon('trash', 13) ?> Remove Logo</button>
        </form>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Guidelines -->
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