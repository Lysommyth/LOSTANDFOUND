<?php
require '../includes/auth_check.php';
require '../includes/admin_check.php';
require '../config/db.php';

$stmt = $pdo->prepare("SELECT username, course_year, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Filter
$filter = $_GET['filter'] ?? 'all';
$where  = $filter === 'completed' ? "WHERE ps.status='completed'"
        : ($filter === 'active'   ? "WHERE ps.status='active'"   : '');

$logs = $pdo->query(
    "SELECT ps.session_id, ps.status, ps.created_at, ps.completed_at,
            i.item_name, i.category, i.location_found,
            f.username AS finder_name,  f.email AS finder_email,
            c.username AS claimant_name, c.email AS claimant_email,
            el.exchange_photo_path, el.exchange_notes,
            el.confirmed_by_finder, el.confirmed_by_claimant,
            (SELECT COUNT(*) FROM peer_messages pm WHERE pm.session_id=ps.session_id) AS msg_count
     FROM peer_sessions ps
     JOIN items i ON ps.item_id     = i.item_id
     JOIN users f ON ps.finder_id   = f.id
     JOIN users c ON ps.claimant_id = c.id
     LEFT JOIN exchange_logs el ON el.session_id = ps.session_id
     $where
     ORDER BY ps.created_at DESC"
)->fetchAll();

$activePage = 'exchange_logs';
$rootPath   = '../';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Exchange Logs – SU Lost &amp; Found</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../includes/common.css">
  <style>
    .filter-tabs { display:flex; gap:6px; margin-bottom:18px; }
    .filter-tab  { padding:6px 16px; border-radius:999px; font-size:12px; font-weight:600; border:1px solid var(--gray-border); background:#fff; color:var(--text-muted); text-decoration:none; transition:background 160ms,color 160ms; }
    .filter-tab.active { background:var(--navy); color:#fff; border-color:var(--navy); }
    .log-detail  { font-size:12px; color:var(--text-muted); }
    .expand-btn  { background:none; border:1px solid var(--gray-border); border-radius:6px; padding:3px 10px; font-size:11px; cursor:pointer; color:var(--navy); font-weight:600; }
    .detail-row  { background:#f8faff; }
    .detail-inner{ padding:16px 20px; }
    .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    @media(max-width:700px){ .detail-grid{grid-template-columns:1fr;} }
    .detail-card { background:#fff; border:1px solid var(--gray-border); border-radius:8px; padding:14px; }
    .detail-label{ font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:8px; }
    .msg-log     { max-height:200px; overflow-y:auto; display:flex; flex-direction:column; gap:6px; }
    .msg-log-item{ font-size:12px; padding:6px 10px; border-radius:6px; background:var(--gray-bg); }
    .msg-log-item strong { color:var(--navy); }
    .msg-log-item span   { color:var(--text-light); font-size:10px; }
  </style>
</head>
<body>
<div class="app">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main">
    <header class="topbar">
      <span class="topbar-title">Exchange Logs</span>
      <div class="topbar-actions">
        <span class="badge badge-admin" style="font-size:12px;padding:5px 12px;">Admin</span>
        <div class="avatar-btn"><?= strtoupper(substr($user['username'],0,1)) ?></div>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div class="page-header-left">
          <div class="page-title">Peer Exchange Logs</div>
          <div class="page-sub">Full audit trail of all peer-to-peer item exchanges. Use for dispute resolution.</div>
        </div>
      </div>

      <div class="filter-tabs">
        <a href="?filter=all"       class="filter-tab <?= $filter==='all'?'active':'' ?>">All</a>
        <a href="?filter=active"    class="filter-tab <?= $filter==='active'?'active':'' ?>">🔄 Active</a>
        <a href="?filter=completed" class="filter-tab <?= $filter==='completed'?'active':'' ?>">✅ Completed</a>
      </div>

      <div class="card">
        <div class="table-wrap">
          <?php if ($logs): ?>
          <table class="su-table">
            <thead>
              <tr>
                <th>#</th><th>Item</th><th>Finder</th><th>Claimant</th>
                <th>Messages</th><th>Started</th><th>Completed</th><th>Status</th><th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($logs as $log): ?>
              <!-- MAIN ROW -->
              <tr>
                <td style="color:var(--text-muted);font-weight:600;">#<?= $log['session_id'] ?></td>
                <td>
                  <strong><?= htmlspecialchars($log['item_name']) ?></strong><br>
                  <span class="log-detail"><?= htmlspecialchars($log['category']) ?></span>
                </td>
                <td>
                  <?= htmlspecialchars($log['finder_name']) ?><br>
                  <span class="log-detail"><?= htmlspecialchars($log['finder_email']) ?></span>
                </td>
                <td>
                  <?= htmlspecialchars($log['claimant_name']) ?><br>
                  <span class="log-detail"><?= htmlspecialchars($log['claimant_email']) ?></span>
                </td>
                <td style="text-align:center;"><?= $log['msg_count'] ?></td>
                <td><?= date('M j, Y g:i a', strtotime($log['created_at'])) ?></td>
                <td>
                  <?= $log['completed_at']
                    ? date('M j, Y g:i a', strtotime($log['completed_at']))
                    : '<span style="color:var(--text-light);">—</span>' ?>
                </td>
                <td>
                  <span class="badge badge-<?= $log['status']==='completed'?'claimed':'pending' ?>">
                    <?= ucfirst($log['status']) ?>
                  </span>
                </td>
                <td>
                  <button class="expand-btn" onclick="toggleDetail('detail-<?= $log['session_id'] ?>')">
                    View Details
                  </button>
                </td>
              </tr>

              <!-- DETAIL ROW (expandable) -->
              <tr class="detail-row" id="detail-<?= $log['session_id'] ?>" style="display:none;">
                <td colspan="9">
                  <div class="detail-inner">
                    <div class="detail-grid">

                      <!-- Contact info -->
                      <div class="detail-card">
                        <div class="detail-label">Contact Details</div>
                        <div style="font-size:13px;margin-bottom:6px;">
                          <strong>Finder:</strong> <?= htmlspecialchars($log['finder_name']) ?>
                          · <a href="mailto:<?= htmlspecialchars($log['finder_email']) ?>"><?= htmlspecialchars($log['finder_email']) ?></a>
                        </div>
                        <div style="font-size:13px;margin-bottom:6px;">
                          <strong>Claimant:</strong> <?= htmlspecialchars($log['claimant_name']) ?>
                          · <a href="mailto:<?= htmlspecialchars($log['claimant_email']) ?>"><?= htmlspecialchars($log['claimant_email']) ?></a>
                        </div>
                        <?php if ($log['exchange_notes']): ?>
                        <div style="font-size:13px;margin-top:8px;padding-top:8px;border-top:1px solid var(--gray-border);">
                          <strong>Exchange Notes:</strong> <?= htmlspecialchars($log['exchange_notes']) ?>
                        </div>
                        <?php endif; ?>
                        <div style="font-size:12px;margin-top:8px;color:var(--text-muted);">
                          Claimant confirmed: <?= $log['confirmed_by_claimant'] ? '✅' : '⏳' ?>
                          &nbsp;|&nbsp;
                          Finder confirmed: <?= $log['confirmed_by_finder'] ? '✅' : '⏳' ?>
                        </div>
                        <?php if ($log['exchange_photo_path']): ?>
                        <div style="margin-top:10px;">
                          <div class="detail-label">Exchange Photo</div>
                          <img src="../<?= htmlspecialchars($log['exchange_photo_path']) ?>"
                               alt="Exchange photo"
                               style="max-width:180px;border-radius:8px;border:1px solid var(--gray-border);">
                        </div>
                        <?php endif; ?>
                      </div>

                      <!-- Chat log -->
                      <div class="detail-card">
                        <div class="detail-label">Chat Log (<?= $log['msg_count'] ?> messages)</div>
                        <?php
                        $msgs = $pdo->prepare(
                            "SELECT pm.message, pm.created_at, u.username
                             FROM peer_messages pm
                             JOIN users u ON pm.sender_id=u.id
                             WHERE pm.session_id=?
                             ORDER BY pm.created_at ASC"
                        );
                        $msgs->execute([$log['session_id']]);
                        $chatMsgs = $msgs->fetchAll();
                        ?>
                        <?php if ($chatMsgs): ?>
                        <div class="msg-log">
                          <?php foreach ($chatMsgs as $m): ?>
                          <div class="msg-log-item">
                            <strong><?= htmlspecialchars($m['username']) ?>:</strong>
                            <?= htmlspecialchars($m['message']) ?>
                            <br><span><?= date('M j, g:i a', strtotime($m['created_at'])) ?></span>
                          </div>
                          <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p style="font-size:13px;color:var(--text-muted);">No messages yet.</p>
                        <?php endif; ?>
                      </div>

                    </div>
                  </div>
                </td>
              </tr>

              <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?>
          <div class="empty-state">
            <div class="icon">🔄</div>
            <h3>No exchange sessions yet</h3>
            <p>Sessions appear here when a finder approves a claim.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
function toggleDetail(id) {
  const row = document.getElementById(id);
  row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>