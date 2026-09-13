<?php
/**
 * pages/activation.php
 * Public activation / license-status screen. Always reachable, even when
 * the license is invalid, expired, or missing — this is the *only* page
 * enforceLicense() lets through unconditionally.
 */
require_once BASE_PATH . '/includes/license.php';

$status     = checkLicenseStatus();
$err        = $inlineError ?? null;
$blockState = $_SESSION['license_block_state'] ?? $status['state'];
unset($_SESSION['license_block_state']);

$messages = [
    'not_activated'   => 'This project requires a valid activation key before it can be used.',
    'invalid'         => 'Invalid activation key. Please contact the administrator.',
    'revoked'         => 'This license has been revoked. Please contact the administrator.',
    'domain_mismatch' => 'This license is bound to a different domain. Please contact the administrator.',
    'expired'         => $status['license']
        ? 'Your license expired on ' . date('d/m/Y', strtotime($status['license']['expiry_date'])) . '. Please contact the administrator to renew your license.'
        : 'Your license has expired. Please contact the administrator to renew your license.',
    'lifetime' => 'Lifetime License Activated.',
    'active'   => 'License is active.',
];
$statusMessage = $messages[$blockState] ?? $messages['not_activated'];
$isGood        = in_array($blockState, ['lifetime', 'active'], true);

if (!function_exists('getLogo')) require_once BASE_PATH . '/includes/logo.php';
$_authLogo = getLogo(false);
$pageTitle = 'Product Activation — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title><?= h($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="<?= getFontEmbedUrl(false) ?>" rel="stylesheet"/>
<style><?= getCSSVariables() ?></style>
<link rel="stylesheet" href="assets/css/style.css"/>
<link rel="stylesheet" href="assets/css/auth.css"/>
</head>
<body class="auth-body">
<div class="app-shell">
<div class="auth-page" style="align-items:center;justify-content:center;">
  <div class="auth-right-panel" style="flex:none;width:100%;max-width:460px;margin:40px auto;">
    <div class="auth-logo-block">
      <?php if (!empty($_authLogo)): ?>
        <img src="<?= h($_authLogo) ?>" alt="<?= h(APP_NAME) ?>"/>
      <?php else: ?>
        <img src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1" alt="<?= h(APP_NAME) ?>"/>
      <?php endif; ?>
    </div>
    <div class="auth-card">
      <span class="auth-card-accent"></span>
      <p class="auth-card-title">Product Activation</p>
      <p class="auth-card-sub"><?= h(APP_NAME) ?></p>

      <?php if ($err): ?>
      <div class="alert alert-error"><?= h($err) ?></div>
      <?php endif; ?>

      <div class="alert <?= $isGood ? 'alert-success' : (in_array($blockState,['expired','revoked','domain_mismatch'],true) ? 'alert-error' : 'alert-info') ?>">
        <?= h($statusMessage) ?>
      </div>

      <?php if ($isGood): ?>
      <a href="index.php?page=catalog" class="btn btn-primary btn-block btn-lg" style="text-decoration:none;">Continue →</a>
      <?php else: ?>
      <form method="POST" action="index.php?page=activation" novalidate>
        <input type="hidden" name="action" value="activate_license"/>
        <?= csrfField() ?>
        <div class="input-group">
          <label class="input-label">Activation Key</label>
          <input type="text" name="activation_key" class="input-field"
                 placeholder="XXXXX-XXXXX-XXXXX-XXXXX" required autocomplete="off"
                 style="font-family:monospace;letter-spacing:1px;text-transform:uppercase;"/>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Activate</button>
      </form>
      <?php endif; ?>

      <p class="auth-footer-text">
        Status: <strong><?= $blockState === 'not_activated' ? 'Project is not activated.' : ucfirst(str_replace('_', ' ', $blockState)) ?></strong>
      </p>
    </div>
  </div>
</div>
</div>
</body>
</html>