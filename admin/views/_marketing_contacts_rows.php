<?php
/**
 * admin/views/_marketing_contacts_rows.php — Partial: contact table rows
 * Expects: $contacts, $total
 */
$sourceLabels = ['user' => 'App User', 'client' => 'Client', 'manual' => 'Manual', 'import' => 'Imported'];
?>
<?php if (empty($contacts)): ?>
<tr><td colspan="8" class="admin-table-empty">No contacts found.</td></tr>
<?php else: foreach ($contacts as $c): ?>
<tr>
  <td><input type="checkbox" class="mkt-contact-check" value="<?= $c['id'] ?>"/></td>
  <td>
    <p style="font-weight:600;font-size:13px;color:var(--admin-text,var(--text));"><?= h($c['name']) ?></p>
    <span class="badge badge-gray" style="font-size:9px;"><?= h($sourceLabels[$c['source_type']] ?? $c['source_type']) ?></span>
    <?php if ($c['status'] === 'inactive'): ?><span class="badge badge-gray" style="font-size:9px;">Inactive</span><?php endif; ?>
  </td>
  <td style="font-size:12px;"><?= h($c['mobile'] ?: '—') ?></td>
  <td style="font-size:12px;"><?= h($c['email'] ?: '—') ?></td>
  <td style="font-size:12px;color:var(--admin-text3,var(--text3));"><?= h($c['city'] ?: '—') ?></td>
  <td>
    <?php if (!empty($c['tags'])): foreach ($c['tags'] as $t): ?>
    <span class="badge badge-blue" style="font-size:9px;margin:1px;"><?= h($t['name']) ?></span>
    <?php endforeach; else: ?><span style="color:var(--admin-text3,var(--text3));font-size:11px;">—</span><?php endif; ?>
  </td>
  <td>
    <span class="badge <?= $c['whatsapp_opt_in'] ? 'badge-green' : 'badge-gray' ?>" style="font-size:9px;"><?= icon('whatsapp',10) ?></span>
    <span class="badge <?= $c['email_opt_in'] ? 'badge-green' : 'badge-gray' ?>" style="font-size:9px;"><?= icon('mail',10) ?></span>
  </td>
  <td>
    <div style="display:flex;gap:5px;">
      <a href="index.php?page=marketing_contact_profile&id=<?= $c['id'] ?>" class="btn-admin-secondary btn-admin-sm" title="View Profile"><?= icon('eye', 13) ?></a>
      <button type="button" class="btn-admin-secondary btn-admin-sm mkt-edit-btn"
              data-id="<?= $c['id'] ?>"
              data-name="<?= h($c['name']) ?>"
              data-mobile="<?= h($c['mobile'] ?? '') ?>"
              data-email="<?= h($c['email'] ?? '') ?>"
              data-city="<?= h($c['city'] ?? '') ?>"
              data-status="<?= h($c['status']) ?>"
              data-wa-optin="<?= (int)$c['whatsapp_opt_in'] ?>"
              data-email-optin="<?= (int)$c['email_opt_in'] ?>"
              title="Edit"><?= icon('edit', 13) ?></button>
      <button type="button" class="btn-admin-danger btn-admin-sm mkt-delete-btn"
              data-id="<?= $c['id'] ?>" data-name="<?= h(addslashes($c['name'])) ?>"
              title="Delete"><?= icon('trash', 13) ?></button>
    </div>
  </td>
</tr>
<?php endforeach; endif; ?>