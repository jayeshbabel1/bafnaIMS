<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0"/>
<meta name="theme-color" content="#0a0a0a"/>
<title><?= h($pageTitle ?? APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="<?= getFontEmbedUrl(false) ?>" rel="stylesheet"/>
<?php $_langFontUrl = getLangFontEmbedUrl(currentLang()); ?>
<?php if ($_langFontUrl): ?>
<link href="<?= h($_langFontUrl) ?>" rel="stylesheet"/>
<?php endif; ?>
  
<style><?= getCSSVariables() ?></style>
<link rel="stylesheet" href="assets/css/style.css"/>
  <link rel="stylesheet" href="assets/css/auth.css"/>
</head>
<body class="auth-body">
<div class="app-shell">
<div style="position:absolute;top:14px;right:14px;z-index:50;display:flex;gap:4px;">
  <?php foreach (LANG_LABELS as $code => $label): ?>
  <form method="POST" action="index.php" style="display:inline;">
    <input type="hidden" name="action" value="switch_language"/>
    <input type="hidden" name="lang" value="<?= h($code) ?>"/>
    <input type="hidden" name="return_url" value="<?= h($_SERVER['REQUEST_URI'] ?? 'index.php?page=login') ?>"/>
    <?= csrfField() ?>
    <button type="submit" style="font-size:11px;font-weight:700;padding:5px 9px;border-radius:6px;border:1px solid #e0d8ce;background:<?= currentLang()===$code?'#111':'#fff' ?>;color:<?= currentLang()===$code?'#fff':'#555' ?>;cursor:pointer;">
      <?= strtoupper($code) ?>
    </button>
  </form>
  <?php endforeach; ?>
</div>
<?php
$_toast   = getFlash('toast');
$_error   = getFlash('error');
$_success = getFlash('success');
if (!function_exists('getLogo')) require_once BASE_PATH . '/includes/logo.php';
$_authLogo = getLogo(false);
?>
<?php if ($_toast || $_success): ?>
<div class="toast" id="app-toast"><?= h($_toast ?: $_success) ?></div>
<?php elseif ($_error): ?>
<div class="toast toast-error" id="app-toast"><?= h($_error) ?></div>
<?php endif; ?>