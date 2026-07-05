<?php
/**
 * admin/scripts/generate_initial_license.php
 * ─────────────────────────────────────────────────────────────────────────
 * ONE-TIME initial license/activation-key generator, for first deployment
 * before any admin session or license exists yet.
 *
 * SECURITY: This script self-deletes immediately after it successfully
 * generates a key. It also requires an existing logged-in admin session
 * (matching the fix already applied to the other admin/scripts/*.php
 * diagnostic scripts) so it cannot be reached by an unauthenticated
 * visitor even in the brief window before it deletes itself.
 *
 * Usage: log into /admin, then visit this URL once, fill the form, submit.
 * The file removes itself on success — reload the URL afterward and it
 * will 404. Regular license management from then on happens exclusively
 * through Admin → Access Control → Settings → License & Activation.
 * ─────────────────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/license.php';

startSecureSession();

// Guard: only a logged-in admin may even load this page (mirrors the
// isAdmin()-gated fix applied to debug_photo.php / fix_folder_casing.php).
if (!isAdmin()) {
    http_response_code(403);
    die('Forbidden. Log in to the admin panel first, then reload this URL.');
}

$selfPath = __FILE__;
$result   = null;
$error    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $result = createLicense([
        'customer_name' => trim($_POST['customer_name'] ?? ''),
        'project_name'  => trim($_POST['project_name']  ?? ''),
        'expiry_date'   => trim($_POST['expiry_date']   ?? ''),
        'is_lifetime'   => !empty($_POST['is_lifetime']),
    ]);

    if ($result['success']) {
        // Immediately activate this installation with the freshly minted key,
        // so the admin doesn't have to copy/paste it into /activation manually.
        activateLicenseKey($result['plain_key']);
    } else {
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<title>Initial License Generation — One-Time Setup</title>
<style>
  body{font-family:-apple-system,Segoe UI,sans-serif;background:#F2F5F9;padding:32px;color:#1A2837;}
  .box{background:#fff;border-radius:12px;padding:28px 32px;max-width:520px;margin:0 auto 16px;box-shadow:0 2px 12px rgba(0,0,0,.06);}
  h1{font-size:18px;margin-bottom:6px;}
  p.hint{color:#8FA3B1;font-size:13px;line-height:1.6;margin-bottom:20px;}
  label{display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#4A6070;margin-bottom:5px;}
  input{width:100%;padding:9px 12px;border:1.5px solid #DDE4EB;border-radius:8px;font-size:14px;margin-bottom:16px;box-sizing:border-box;}
  button{background:#2C6E8A;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;}
  .key{font-family:monospace;font-size:18px;font-weight:700;background:#E3F2E8;border:1px solid #3D8B6E;border-radius:8px;padding:12px 16px;letter-spacing:1px;margin:14px 0;}
  .error{background:#F8E3E3;border:1px solid #B23A3A;color:#B23A3A;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;}
  .warn{background:#FFF8E6;border:1px solid #E8C468;color:#92600A;border-radius:8px;padding:10px 14px;font-size:12.5px;line-height:1.6;}
</style>
</head>
<body>

<div class="box">
  <h1>Initial License / Activation Setup</h1>
  <p class="hint">One-time script to generate and activate the first license key for this installation. <strong>This file deletes itself automatically after a successful generation.</strong></p>

  <?php if ($error): ?>
    <div class="error"><?= h($error) ?></div>
  <?php endif; ?>

  <?php if ($result && $result['success']): ?>
    <p style="font-weight:700;color:#3D8B6E;">✅ License generated and activated successfully.</p>
    <p style="font-size:13px;color:#555;margin:10px 0 4px;">Activation key (copy and store this securely — it will not be shown again):</p>
    <div class="key"><?= h($result['plain_key']) ?></div>

    <?php
    // Self-delete on success — this is the whole point of the script.
    $deleted = @unlink($selfPath);
    ?>
    <?php if ($deleted): ?>
    <p class="warn">🔒 This script has been deleted from the server. Reloading this URL will now 404. All further license management is available at Admin → Settings → License &amp; Activation.</p>
    <?php else: ?>
    <p class="error">⚠️ The license was created successfully, but this script file could not be auto-deleted (check file permissions). <strong>Please delete <?= h(basename($selfPath)) ?> manually right now</strong> — leaving it in place would let anyone who finds this URL mint additional activation keys.</p>
    <?php endif; ?>

    <p style="margin-top:16px;"><a href="../index.php">← Go to Admin Dashboard</a></p>

  <?php else: ?>

    <form method="POST" action="">
      <?= csrfField() ?>
      <label>Customer Name</label>
      <input type="text" name="customer_name" required/>

      <label>Project Name</label>
      <input type="text" name="project_name" value="<?= h(APP_NAME) ?>" required/>

      <label>Expiry Date</label>
      <input type="date" name="expiry_date" required/>

      <label style="display:flex;align-items:center;gap:8px;text-transform:none;font-weight:500;">
        <input type="checkbox" name="is_lifetime" value="1" style="width:auto;margin:0;"/>
        Lifetime License (never expires)
      </label>

      <button type="submit">Generate &amp; Activate</button>
    </form>

  <?php endif; ?>
</div>

</body>
</html>