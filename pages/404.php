<?php
/**
 * pages/404.php
 * Shown whenever a requested page/route does not exist in the user panel.
 */
http_response_code(404);
$pageTitle = 'Page Not Found — ' . APP_NAME;
$showNav   = isLoggedIn();
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content" style="display:flex;align-items:center;justify-content:center;min-height:70vh;">
  <div style="text-align:center;max-width:420px;padding:20px;">
    <div style="width:88px;height:88px;border-radius:50%;background:var(--gray-100,#f2f2f2);
                display:flex;align-items:center;justify-content:center;margin:0 auto 24px;
                font-size:30px;font-weight:800;color:var(--black,#0a0a0a);">
      404
    </div>
    <h1 style="font-family:var(--font-display);font-size:24px;font-weight:700;color:var(--text);margin-bottom:10px;">
      Page Not Found
    </h1>
    <p style="font-size:14px;color:var(--text3);line-height:1.6;margin-bottom:28px;">
      The page you're looking for doesn't exist or may have been moved.
    </p>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
      <a href="index.php?page=<?= isLoggedIn() ? 'catalog' : 'login' ?>" class="btn btn-primary" style="text-decoration:none;">
        <?= icon('home', 15) ?>&nbsp; <?= isLoggedIn() ? 'Go to Catalog' : 'Go to Login' ?>
      </a>
      <a href="javascript:history.back()" class="btn btn-secondary" style="text-decoration:none;">
        <?= icon('back', 15) ?>&nbsp; Go Back
      </a>
    </div>
  </div>
</div>

<?php include BASE_PATH . '/layouts/footer.php'; ?>