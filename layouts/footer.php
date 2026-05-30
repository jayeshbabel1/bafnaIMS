<?php if (!empty($showNav) && isLoggedIn()): ?>
<nav class="bottom-nav">
    <a href="index.php?page=catalog"   class="nav-item<?= navActive('catalog') ?>">
        <span class="nav-icon"><?= icon('grid',22) ?></span>
        <span class="nav-label">Catalog</span>
    </a>
    <a href="index.php?page=shortlist" class="nav-item<?= navActive('shortlist') ?>">
        <span class="nav-icon"><?= icon('heart',22) ?></span>
        <span class="nav-label">Saved</span>
        <?php $sc = shortlistCount(); if ($sc): ?><span class="nav-badge"><?= $sc ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=inquiries" class="nav-item<?= navActive('inquiries') ?>">
        <span class="nav-icon"><?= icon('msg',22) ?></span>
        <span class="nav-label">Inquiries</span>
        <?php $ic = inquiryCount(); if ($ic): ?><span class="nav-badge"><?= $ic ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=profile"   class="nav-item<?= navActive('profile') ?>">
        <span class="nav-icon"><?= icon('user',22) ?></span>
        <span class="nav-label">Profile</span>
    </a>
</nav>
<?php endif; ?>

</div><!-- .app-shell -->

<script src="assets/js/app.js"></script>
<?php if (!empty($extraJS)) foreach ($extraJS as $f): ?>
<script src="assets/js/<?= h($f) ?>"></script>
<?php endforeach; ?>
</body>
</html>
