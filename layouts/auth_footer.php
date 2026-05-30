</div><!-- .app-shell -->
<script src="assets/js/app.js"></script>
<?php if (!empty($extraJS)) foreach ($extraJS as $f): ?>
<script src="assets/js/<?= h($f) ?>"></script>
<?php endforeach; ?>
</body>
</html>