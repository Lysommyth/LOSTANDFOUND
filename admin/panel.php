<?php
require '../includes/auth_check.php';
require '../includes/admin_check.php';
require '../config/db.php';

$stmt = $pdo->prepare("SELECT username, course_year, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$totalUsers    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$totalItems    = (int)$pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
$pendingClaims = (int)$pdo->query("SELECT COUNT(*) FROM claims WHERE claim_status='pending'")->fetchColumn();
$returnedItems = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE status='returned'")->fetchColumn();

$recent = $pdo->query(
    "SELECT c.claim_id, c.claim_status, c.claimed_at,
            u.username AS claimant, i.item_name
     FROM claims c
     JOIN users u ON c.user_id = u.id
     JOIN items i ON c.item_id = i.item_id
     ORDER BY c.claimed_at DESC LIMIT 8"
)->fetchAll();

$byCategory = $pdo->query(
    "SELECT category, COUNT(*) AS cnt FROM items GROUP BY category ORDER BY cnt DESC"
)->fetchAll();

$activePage = 'admin_panel';
$rootPath   = '../';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Admin Panel – SU Lost &amp; Found</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../includes/common.css">
  <style>
    .quick-links { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:22px; }
    @media(max-width:700px){ .quick-links{grid-template-columns:1fr;} }
    .quick-card {
      background:#fff; border:1px solid var(--gray-border); border-radius:var(--radius);
      padding:22px; text-align:center; text-decoration:none;
      transition:border-color var(--trans), box-shadow var(--trans);
    }
    .quick-card:hover { border-color:var(--gold); box-shadow:0 4px 16px rgba(11,45,94,.08); }
    .quick-card .q-icon  { font-size:28px; margin-bottom:8px; }
    .quick-card .q-label { font-size:14px; font-weight:700; color:var(--navy); }
    .quick-card .q-sub   { font-size:12px; color:var(--text-muted); margin-top:3px; }
    .two-col { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
    @media(max-width:800px){ .two-col{grid-template-columns:1fr;} }
    .bar-wrap { display:flex; flex-direction:column; gap:10px; }
    .bar-row  { display:flex; align-items:center; gap:10px; }
    .bar-label{ font-size:12px; color:var(--text-muted); min-width:100px; }
    .bar-track{ flex:1; background:var(--gray-bg); border-radius:999px; height:10px; overflow:hidden; }
    .bar-fill { height:100%; background:var(--gold); border-radius:999px; transition:width .6s ease; }
    .bar-count{ font-size:12px; font-weight:700; color:var(--navy); min-width:24px; text-align:right; }
  </style>
</head>
<body>
<div class="app">
  <?php include '../includes/sidebar.php';?>
  <main class="main">
    <header class="topbar">
      <span class="topbar-title">Admin Panel</span>
      <div class="topbar-actions">
        <span class="badge badge-admin" style="font-size:12px;padding:5px 12px;">Admin</span>
        <div class="avatar-btn"><?= strtoupper(substr($user['username'],0,1)) ?></div>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div class="page-header-left">
          <div class="page-title">Overview</div>
          <div class="page-sub">System-wide stats and quick access to admin tools.</div>
        </div>
      </div>

      <!-- STATS -->
      <div class="stats-row">
        <div class="stat-card accent">
          <div class="stat-label">Registered Students</div>
          <div class="stat-value"><?= $totalUsers ?></div>
          <div class="stat-meta">Active accounts</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Total Items</div>
          <div class="stat-value"><?= $totalItems ?></div>
          <div class="stat-meta">All time</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Pending Claims</div>
          <div class="stat-value" style="color:#2563EB;"><?= $pendingClaims ?></div>
          <div class="stat-meta">Awaiting review</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Items Returned</div>
          <div class="stat-value" style="color:#059669;"><?= $returnedItems ?></div>
          <div class="stat-meta">Successfully reunited</div>
        </div>
      </div>

      <!-- QUICK LINKS -->
      <div class="quick-links">
        <a href="claim_reviews.php" class="quick-card">
          <div class="q-icon">↺</div>
          <div class="q-label">Claim Reviews</div>
          <div class="q-sub"><?= $pendingClaims ?> pending</div>
        </a>
        <a href="manage_items.php" class="quick-card">
          <div class="q-icon">📦</div>
          <div class="q-label">Manage Items</div>
          <div class="q-sub"><?= $totalItems ?> total</div>
        </a>
        <a href="manage_users.php" class="quick-card">
          <div class="q-icon">👥</div>
          <div class="q-label">Manage Users</div>
          <div class="q-sub"><?= $totalUsers ?> students</div>
        </a>
      </div>

      <div class="two-col">
        <!-- RECENT CLAIMS -->
        <div class="card">
          <div class="card-head">
            <span class="card-head-title">Recent Claims</span>
            <a href="claim_reviews.php" class="btn-outline" style="font-size:12px;padding:4px 12px;">View All</a>
          </div>
          <div class="table-wrap">
            <?php if ($recent): ?>
            <table class="su-table">
              <thead><tr><th>Item</th><th>Claimant</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($recent as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['item_name']) ?></td>
                  <td><?= htmlspecialchars($r['claimant']) ?></td>
                  <td><span class="badge badge-<?= $r['claim_status'] ?>"><?= ucfirst($r['claim_status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state" style="padding:28px;"><p>No claims yet.</p></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ITEMS BY CATEGORY -->
        <div class="card">
          <div class="card-head"><span class="card-head-title">Items by Category</span></div>
          <div class="card-body">
            <?php $maxCnt = $byCategory ? max(array_column($byCategory,'cnt')) : 1; ?>
            <?php if ($byCategory): ?>
            <div class="bar-wrap">
              <?php foreach ($byCategory as $row): ?>
              <div class="bar-row">
                <span class="bar-label"><?= htmlspecialchars($row['category']) ?></span>
                <div class="bar-track">
                  <div class="bar-fill" style="width:<?= round($row['cnt']/$maxCnt*100) ?>%"></div>
                </div>
                <span class="bar-count"><?= $row['cnt'] ?></span>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:28px;"><p>No items yet.</p></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>