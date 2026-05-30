<?php if (!empty($showNav) && isLoggedIn()): ?>
<nav class="bottom-nav">
  <a href="index.php?page=catalog"   class="nav-item<?= navActive('catalog') ?>">
    <?= icon('grid',21) ?>
    <span class="nav-label">Catalog</span>
  </a>
  <a href="index.php?page=shortlist" class="nav-item<?= navActive('shortlist') ?>">
    <span style="position:relative;display:inline-flex;">
      <?= icon('heart',21) ?>
      <?php $sc = shortlistCount(); if ($sc): ?>
      <span class="nav-badge"><?= $sc ?></span>
      <?php endif; ?>
    </span>
    <span class="nav-label">Saved</span>
  </a>
  <a href="index.php?page=inquiries" class="nav-item<?= navActive('inquiries') ?>">
    <span style="position:relative;display:inline-flex;">
      <?= icon('msg',21) ?>
      <?php $ic = inquiryCount(); if ($ic): ?>
      <span class="nav-badge"><?= $ic ?></span>
      <?php endif; ?>
    </span>
    <span class="nav-label">Inquiries</span>
  </a>
  <a href="index.php?page=profile"   class="nav-item<?= navActive('profile') ?>">
    <?= icon('user',21) ?>
    <span class="nav-label">Profile</span>
  </a>
</nav>
<?php endif; ?>

</div><!-- .page-wrapper -->
</div><!-- .app-shell -->
<script src="assets/js/app.js"></script>
<?php if (!empty($extraJS)) foreach ($extraJS as $f): ?>
<script src="assets/js/<?= h($f) ?>"></script>
<?php endforeach; ?>
</body>
</html>