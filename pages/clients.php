<?php
/**
 * pages/clients.php — Client list with search & pagination
 */
$pageTitle = 'Clients — ' . APP_NAME;
$showNav   = true;
$extraJS   = ['pagination.js'];
require_once BASE_PATH . '/includes/clients.php';

$perPage     = 9;
$currentPage = max(1, (int)($_GET['p'] ?? 1));
$search      = trim($_GET['q'] ?? '');
$isAjax      = !empty($_GET['ajax']);

$result = getClients($_SESSION['user_id'], [
    'search' => $search,
    'limit'  => $perPage,
    'offset' => ($currentPage - 1) * $perPage,
]);
$clients    = $result['rows'];
$total      = $result['total'];
$totalPages = max(1, (int)ceil($total / $perPage));

if ($isAjax) {
    ob_start();
    include BASE_PATH . '/pages/_clients_rows.php';
    $html = ob_get_clean();
    header('Content-Type: application/json');
    echo json_encode(['html' => $html, 'total' => $total, 'pages' => $totalPages, 'current' => $currentPage]);
    exit;
}
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content">

  <!-- Page header -->
  <div class="page-header">
    <div class="page-header-left">
      <p class="page-eyebrow">My Contacts</p>
      <h1 class="page-title">Clients</h1>
    </div>
    <div class="page-header-right">
      <a href="index.php?page=client_form" class="btn btn-primary btn-sm">
        <?= icon('plus', 14) ?>&nbsp; Add Client
      </a>
    </div>
  </div>

  <!-- Search bar -->
  <div class="catalog-search-wrap" style="margin-bottom:20px;">
    <span class="catalog-search-icon"><?= icon('search', 16) ?></span>
    <input type="search" id="clientSearch" class="catalog-search-input"
           placeholder="Search by name, mobile or mason name…"
           value="<?= h($search) ?>" autocomplete="off"/>
    <?php if ($search): ?>
    <span class="catalog-search-clear" id="clientSearchClear"><?= icon('close', 13) ?></span>
    <?php endif; ?>
  </div>

  <!-- Stats row -->
  <div id="clientCount" style="font-size:13px;color:var(--text3);margin-bottom:16px;display:flex;align-items:center;gap:8px;">
    <strong style="font-size:18px;color:var(--text);"><?= $total ?></strong> clients
    <?php if ($search): ?>
    · <a href="index.php?page=clients" style="font-size:12px;font-weight:600;color:var(--text3);text-decoration:underline;">Clear</a>
    <?php endif; ?>
  </div>

  <!-- AJAX loader -->
  <div id="clientLoader" class="ajax-loader"><div class="loader-spinner"></div></div>

  <!-- Content -->
  <div id="clientsContent">
    <?php include BASE_PATH . '/pages/_clients_rows.php'; ?>
  </div>

</div>

<script>
(function () {
  const content   = document.getElementById('clientsContent');
  const loader    = document.getElementById('clientLoader');
  const countEl   = document.getElementById('clientCount');
  const searchEl  = document.getElementById('clientSearch');
  const clearBtn  = document.getElementById('clientSearchClear');

  let state = { q: <?= json_encode($search) ?>, page: <?= $currentPage ?> };
  let timer = null;
  let totalPages = <?= (int)$totalPages ?>;
  let pager = null;

  async function load(page, push) {
    state.page = page;
    if (loader) loader.style.display = 'flex';
    const params = new URLSearchParams({ page: 'clients', ajax: '1', p: page });
    if (state.q) params.set('q', state.q);
    try {
      const r = await fetch('index.php?' + params);
      const d = await r.json();
      content.innerHTML = d.html;
      if (countEl) countEl.innerHTML = '<strong style="font-size:18px;color:var(--text);">' + d.total + '</strong> clients';
      totalPages = d.pages;
    if (pager) {
       pager.setWrapEl(document.getElementById('paginationWrap'));
       pager.render(d.current, d.pages);
     }
      if (push !== false) {
        const hp = new URLSearchParams({ page: 'clients' });
        if (state.q) hp.set('q', state.q);
        if (page > 1) hp.set('p', page);
        history.pushState({}, '', 'index.php?' + hp);
      }
    } finally {
      if (loader) loader.style.display = 'none';
    }
  }

  
  if (searchEl) {
    searchEl.addEventListener('input', () => {
      const v = searchEl.value.trim();
      clearTimeout(timer);
      if (v.length > 0 && v.length < 3) return;
      timer = setTimeout(() => { state.q = v; load(1); }, 350);
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      searchEl.value = ''; state.q = ''; load(1);
    });
  }

 document.addEventListener('DOMContentLoaded', () => {
   pager = initPagination({
     wrapEl: document.getElementById('paginationWrap'),
     btnClass: 'pag-btn',
     onPage: (page) => load(page)
   });
   pager.render(state.page, totalPages);
 });
})();
</script>

<?php include BASE_PATH . '/layouts/footer.php'; ?>