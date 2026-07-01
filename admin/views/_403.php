<?php
/**
 * admin/views/_403.php
 * Shown when an admin tries to access a page they don't have permission for.
 * Can be included inline (within layout) or standalone (before layout loads).
 */
$standalone = !defined('ADMIN_LAYOUT_LOADED');
if ($standalone):
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Access Denied — <?= defined('APP_NAME') ? APP_NAME : 'Admin' ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'DM Sans',system-ui,sans-serif;background:#F2F5F9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{background:#fff;border-radius:16px;padding:40px 36px;max-width:480px;width:100%;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.1)}
  .icon{width:72px;height:72px;border-radius:50%;background:#FFF0F0;color:#E84040;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:32px}
  h1{font-size:22px;font-weight:700;color:#1A2837;margin-bottom:8px}
  p{font-size:14px;color:#8FA3B1;line-height:1.6;margin-bottom:24px}
  a{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;background:#2C6E8A;color:#fff;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;transition:background .15s}
  a:hover{background:#1A4D65}
</style>
</head>
<body>
<?php endif; ?>

<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 24px;text-align:center;min-height:<?= $standalone ? '0' : '60vh' ?>;">
  <div style="width:72px;height:72px;border-radius:50%;background:#FFF0F0;color:#E84040;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:28px;font-weight:700;flex-shrink:0;">
    403
  </div>
  <h2 style="font-size:22px;font-weight:700;color:var(--admin-text,#1A2837);margin-bottom:8px;">Access Denied</h2>
  <p style="font-size:14px;color:var(--admin-text3,#8FA3B1);line-height:1.6;max-width:380px;margin-bottom:28px;">
    You don't have permission to access this page.<br/>
    Please contact your Super Admin if you believe this is a mistake.
  </p>
  <a href="index.php" style="display:inline-flex;align-items:center;gap:6px;padding:10px 22px;
     background:var(--admin-accent,#2C6E8A);color:#fff;border-radius:8px;text-decoration:none;
     font-size:13px;font-weight:600;transition:background .15s;">
    ← Back to Dashboard
  </a>
</div>

<?php if ($standalone): ?>
</body>
</html>
<?php endif; ?>