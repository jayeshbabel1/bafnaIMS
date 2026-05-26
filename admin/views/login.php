<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Admin Login — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet"/>
<style><?= getCSSVariables() ?></style>
<link rel="stylesheet" href="../assets/css/style.css"/>
<link rel="stylesheet" href="../assets/css/admin.css"/>
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg);">
<div style="width:100%;max-width:380px;padding:24px;">
  <div style="text-align:center;margin-bottom:32px;">
    <div style="width:56px;height:56px;background:linear-gradient(135deg,var(--accent2),var(--text));border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
      <svg width="28" height="28" viewBox="0 0 36 36" fill="none"><polygon points="18,4 32,28 4,28" fill="rgba(255,255,255,0.2)" stroke="white" stroke-width="1.5"/><polygon points="18,10 26,24 10,24" fill="rgba(255,255,255,0.35)" stroke="white" stroke-width="1"/></svg>
    </div>
    <h1 style="font-family:'Cormorant Garamond',serif;font-size:24px;font-weight:600;color:var(--text);">Admin Panel</h1>
    <p style="font-size:13px;color:var(--text3);margin-top:4px;"><?= APP_NAME ?></p>
  </div>

  <?php if ($adminError ?? null): ?>
  <div class="alert alert-error" style="margin-bottom:16px;"><?= h($adminError) ?></div>
  <?php endif; ?>

  <div class="admin-form-section">
    <form method="POST" action="index.php">
      <input type="hidden" name="action" value="admin_login"/>
      <div style="margin-bottom:14px;">
        <label class="admin-label">Username</label>
        <input type="text" name="username" class="admin-input" placeholder="admin" required autocomplete="username"/>
      </div>
      <div style="margin-bottom:20px;">
        <label class="admin-label">Password</label>
        <input type="password" name="password" class="admin-input" placeholder="••••••••" required autocomplete="current-password"/>
      </div>
      <button type="submit" class="btn-admin-primary" style="width:100%;justify-content:center;padding:11px;">Sign In to Admin</button>
    </form>
  </div>
  <p style="text-align:center;margin-top:16px;font-size:12px;color:var(--text3);">
    <a href="../index.php?page=catalog" style="color:var(--accent);">← Back to App</a>
  </p>
</div>
<script src="../assets/js/app.js"></script>
</body>
</html>
