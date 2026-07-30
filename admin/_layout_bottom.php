</div><!-- .admin-content -->
  </main>
</div><!-- .admin-shell -->
<script src="../assets/js/app.js"></script>
<script src="../assets/js/admin.js"></script>
<script src="../assets/js/pagination.js"></script>
<?php
$currentAdminPage = $_GET['page'] ?? 'dashboard';
if ($currentAdminPage === 'products'): ?>
<script src="../assets/js/admin.products.js"></script>
<?php endif; ?>
<?php if ($currentAdminPage === 'logo'): ?>
<script src="../assets/js/admin.logo.js"></script>
<?php endif; ?>
</body>
</html>