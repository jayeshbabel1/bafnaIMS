<?php
$adminTitle = 'Users';
include __DIR__ . '/../_layout_top.php';
$db = getDB();
$search = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND (name LIKE ? OR email LIKE ? OR firm LIKE ?)"; $p="%$search%"; $params=[$p,$p,$p]; }
$sql .= " ORDER BY created_at DESC";
$st = $db->prepare($sql); $st->execute($params);
$users = $st->fetchAll();
?>

<div style="display:flex;gap:12px;align-items:center;margin-bottom:20px;">
  <form method="GET" action="index.php" style="display:flex;gap:8px;margin-left:auto;">
    <input type="hidden" name="page" value="users"/>
    <input type="text" name="q" class="admin-input" placeholder="Search name / email / firm…" value="<?= h($search) ?>" style="width:220px;"/>
    <button type="submit" class="btn-admin-secondary btn-admin-sm"><?= icon('search',14) ?></button>
    <?php if ($search): ?><a href="index.php?page=users" class="btn-admin-secondary btn-admin-sm">Clear</a><?php endif; ?>
  </form>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Firm</th>
        <th>City</th>
        <th>Verified</th>
        <th>Joined</th>
        <th>Password Reset</th>
        <th>Clients</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($users)): ?>
      <tr><td colspan="10" style="text-align:center;padding:30px;color:var(--text3);">No users found.</td></tr>
      <?php else: foreach ($users as $u):
        $initials = strtoupper(($u['name'][0] ?? 'U'));
        $roleLabel = ROLES[$u['role'] ?? ''] ?? $u['role'];

        // Client count for this user
        $ccSt = $db->prepare("SELECT COUNT(*) as c FROM clients WHERE user_id=?");
        $ccSt->execute([$u['id']]); $cc = (int)$ccSt->fetch()['c'];
      ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-mid));color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;"><?= h($initials) ?></div>
            <span style="font-weight:500;"><?= h($u['name']) ?></span>
          </div>
        </td>
        <td style="color:var(--text2);font-size:12px;"><?= h($u['email']) ?></td>
        <td><span class="badge badge-blue" style="font-size:10px;"><?= h($roleLabel) ?></span></td>
        <td style="color:var(--text2);font-size:12px;"><?= h($u['firm']) ?></td>
        <td style="color:var(--text3);font-size:12px;"><?= h($u['city']) ?></td>
        <td>
          <form method="POST" action="index.php" style="display:inline;">
            <input type="hidden" name="action"   value="update_user_status"/>
            <input type="hidden" name="user_id"  value="<?= $u['id'] ?>"/>
            <input type="hidden" name="verified" value="<?= $u['verified'] ? 0 : 1 ?>"/>
            <button type="submit" class="<?= $u['verified'] ? 'badge badge-green' : 'badge badge-gray' ?>" style="cursor:pointer;border:none;">
              <?= $u['verified'] ? '✓ Verified' : '✗ Pending' ?>
            </button>
          </form>
        </td>
        <td style="color:var(--text3);font-size:11px;"><?= date('d M Y', $u['created_at']) ?></td>
        <td>
          <form method="POST" action="index.php" style="display:inline;">
            <input type="hidden" name="action" value="send_password_reset"/>
            <input type="hidden" name="user_id" value="<?= $u['id'] ?>"/>
            <button type="submit" class="btn-admin-secondary btn-admin-sm" style="border:none;cursor:pointer;font-size:10px;">
              <?= icon('mail',13) ?> Reset Email
            </button>
          </form>
        </td>
        <td>
          <a href="index.php?page=user_clients&user_id=<?= $u['id'] ?>"
             class="btn-admin-secondary btn-admin-sm"
             style="display:inline-flex;align-items:center;gap:5px;text-decoration:none;">
            <?= icon('users',13) ?>
            <span><?= $cc ?> Clients</span>
          </a>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<p style="font-size:12px;color:var(--text3);margin-top:10px;"><?= count($users) ?> users</p>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>