<?php
/**
 * admin/views/404.php
 * Shown when a requested admin page/route does not exist.
 * Rendered standalone (before the admin layout/sidebar loads), mirroring
 * the pattern used by admin/views/_403.php.
 */
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Page Not Found — <?= defined('APP_NAME') ? APP_NAME : 'Admin' ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'DM Sans',system-ui,sans-serif;background:#F2F5F9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{background:#fff;border-radius:16px;padding:40px 36px;max-width:480px;width:100%;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.1)}
  .icon{width:72px;height:72px;border-radius:50%;background:#EEF2F7;color:#2C6E8A;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:24px;font-weight:800;}
  h1{font-size:22px;font-weight:700;color:#1A2837;margin-bottom:8px}
  p{font-size:14px;color:#8FA3B1;line-height:1.6;margin-bottom:24px}
  a{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;background:#2C6E8A;color:#fff;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;transition:background .15s}
  a:hover{background:#1A4D65}
</style>
</head>
<body>
<div class="card">
  <div class="icon">404</div>
  <h1>Page Not Found</h1>
  <p>The admin page you're looking for doesn't exist or may have been moved.</p>
  <a href="index.php">← Back to Dashboard</a>
</div>
</body>
</html>