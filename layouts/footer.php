</div><!-- .page-wrapper -->
</div><!-- .app-shell -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script src="assets/js/app.js"></script>
<?php if (!empty($extraJS)) foreach ($extraJS as $f): ?>
<script src="assets/js/<?= h($f) ?>"></script>
<?php endforeach; ?>
</body>
</html>