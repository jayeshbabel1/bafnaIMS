</div><!-- .admin-content -->
  </main>
</div><!-- .admin-shell -->
<script src="../assets/js/app.js"></script>
<script src="../assets/js/admin.js"></script>
<?php
// Page-specific JS injection (Task 1 + Task 2)
$currentAdminPage = $_GET['page'] ?? 'dashboard';
if ($currentAdminPage === 'products'):
?>
<script src="../assets/js/admin.products.js"></script>
<?php endif; ?>
<?php if ($currentAdminPage === 'logo'): ?>
<script src="../assets/js/admin.logo.js"></script>
<?php endif; ?>
</body>
</html>