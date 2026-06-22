<?php
require '../includes/auth_check.php';
require '../includes/admin_check.php';
require '../config/db.php';

$stmt = $pdo->prepare("SELECT username, course_year, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $item_id   = (int)$_POST['item_id'];
    $newStatus = $_POST['new_status'];
    if (in_array($newStatus, ['available','claimed','returned'])) {
        $pdo->prepare("UPDATE items SET status=? WHERE item_id=?")->execute([$newStatus, $item_id]);
        $success = "Item #$item_id updated to '$newStatus'.";
    }
}

if (isset($_GET['delete'])) {
    $del_id  = (int)$_GET['delete'];
    $row     = $pdo->prepare("SELECT image_path FROM items WHERE item_id=?");
    $row->execute([$del_id]);
    $delItem = $row->fetch();
    if ($delItem && $delItem['image_path'] && file_exists('../' . $delItem['image_path'])) {
        unlink('../' . $delItem['image_path']);
    }
    $pdo->prepare("DELETE FROM items WHERE item_id=?")->execute([$del_id]);
    $success = "Item deleted.";
}

$statusF = $_GET['status'] ?? '';
$catF    = $_GET['cat']    ?? '';
$where   = []; $params = [];
if ($statusF && in_array($statusF, ['available','claimed','returned'])) { $where[] = "i.status=?"; $params[] = $statusF; }
if ($catF)  { $where[] = "i.category=?"; $params[] = $catF; }
$wSql  = $where ? 'WHERE '.implode(' AND ',$where) : '';

$items = $pdo->prepare(
    "SELECT i.item_id, i.item_name, i.category, i.location_found, i.status, i.created_at, u.username AS finder
     FROM items i JOIN users u ON i.finder_id=u.id $wSql ORDER BY i.created_at DESC"
);
$items->execute($params);
$allItems = $items->fetchAll();

$activePage = 'manage_items';
$rootPath   = '../';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Manage Items – SU Lost &amp; Found</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../includes/common.css">
</head>
<body>
<div class="app">
  <?php include '../includes/sidebar.php';?>
  <main class="main">
    <header class="topbar">
      <span class="topbar-title">Manage Items</span>
      <div class="topbar-actions">
        <span class="badge badge-admin" style="font-size:12px;padding:5px 12px;">Admin</span>
        <div class="avatar-btn"><?= strtoupper(substr($user['username'],0,1)) ?></div>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div class="page-header-left">
          <div class="page-title">Manage Items</div>
          <div class="page-sub">Update statuses, remove invalid entries, oversee all found items.</div>
        </div>
      </div>

      <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>
      <?php if ($error):   ?><div class="alert alert-danger">❌ <?= $error ?></div><?php endif; ?>

      <!-- FILTERS -->
      <form class="d-flex flex-wrap gap-2 align-items-center mb-3" method="GET">
        <select name="status" class="form-control" style="width:auto;" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <option value="available" <?= $statusF==='available'?'selected':'' ?>>Available</option>
          <option value="claimed"   <?= $statusF==='claimed'?'selected':'' ?>>Claimed</option>
          <option value="returned"  <?= $statusF==='returned'?'selected':'' ?>>Returned</option>
        </select>
        <select name="cat" class="form-control" style="width:auto;" onchange="this.form.submit()">
          <option value="">All Categories</option>
          <?php foreach(['Electronics','Books','Clothing','Keys','IDs/Wallets','Bags','Accessories','Other'] as $cat): ?>
          <option value="<?= $cat ?>" <?= $catF===$cat?'selected':'' ?>><?= $cat ?></option>
          <?php endforeach; ?>
        </select>
        <a href="manage_items.php" class="btn-outline" style="padding:7px 14px;font-size:13px;">Clear</a>
        <span class="ms-auto" style="font-size:13px;color:var(--text-muted);"><?= count($allItems) ?> items</span>
      </form>

      <div class="card">
        <div class="table-wrap">
          <?php if ($allItems): ?>
          <table class="su-table">
            <thead>
              <tr><th>#</th><th>Item</th><th>Category</th><th>Location</th><th>Reported By</th><th>Date</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($allItems as $it): ?>
              <tr>
                <td style="color:var(--text-muted);font-weight:600;">#<?= $it['item_id'] ?></td>
                <td><strong><?= htmlspecialchars($it['item_name']) ?></strong></td>
                <td><?= htmlspecialchars($it['category']) ?></td>
                <td><?= htmlspecialchars($it['location_found']) ?></td>
                <td><?= htmlspecialchars($it['finder']) ?></td>
                <td><?= date('M j, Y', strtotime($it['created_at'])) ?></td>
                <td><span class="badge badge-<?= $it['status'] ?>"><?= ucfirst($it['status']) ?></span></td>
                <td>
                  <form method="POST" class="d-inline-flex gap-1 align-items-center">
                    <input type="hidden" name="item_id" value="<?= $it['item_id'] ?>">
                    <select name="new_status" class="form-control" style="font-size:11px;padding:3px 6px;width:auto;">
                      <option value="available" <?= $it['status']==='available'?'selected':'' ?>>Available</option>
                      <option value="claimed"   <?= $it['status']==='claimed'?'selected':'' ?>>Claimed</option>
                      <option value="returned"  <?= $it['status']==='returned'?'selected':'' ?>>Returned</option>
                    </select>
                    <button type="submit" name="update_status" class="btn-navy" style="font-size:11px;padding:4px 8px;">Save</button>
                  </form>
                  <a href="?delete=<?= $it['item_id'] ?><?= $statusF?"&status=$statusF":'' ?><?= $catF?"&cat=$catF":'' ?>"
                     onclick="return confirm('Permanently delete this item?')"
                     class="btn-danger ms-1" style="font-size:11px;padding:4px 8px;">🗑</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?>
          <div class="empty-state">
            <div class="icon">📦</div>
            <h3>No items found</h3>
            <p>Try adjusting the filters above.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>