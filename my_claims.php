<?php
require 'includes/auth_check.php';
require 'config/db.php';

$stmt = $pdo->prepare("SELECT username, course_year, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$claims = $pdo->prepare(
    "SELECT c.claim_id, c.claim_status, c.claim_details, c.admin_note, c.claimed_at,
            i.item_name, i.category, i.location_found,
            ps.session_id, ps.status AS session_status
     FROM claims c
     JOIN items i ON c.item_id = i.item_id
     LEFT JOIN peer_sessions ps ON ps.claim_id = c.claim_id
     WHERE c.user_id = ?
     ORDER BY c.claimed_at DESC"
);
$claims->execute([$_SESSION['user_id']]);
$myClaims = $claims->fetchAll();

// Determine which categories are peer vs admin
$adminCategories = ['Electronics', 'IDs/Wallets', 'Keys'];

$activePage = 'my_claims';
$rootPath   = '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>My Claims – SU Lost &amp; Found</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="includes/common.css">
  <style>
    .chat-btn { background:var(--navy); color:var(--gold); border:none; border-radius:6px; padding:5px 12px; font-size:12px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:5px; }
    .chat-btn:hover { opacity:.85; color:var(--gold); }
    .chat-btn.completed { background:#059669; color:#fff; }
    .flow-tag { font-size:10px; font-weight:600; padding:2px 8px; border-radius:999px; }
    .flow-tag.peer  { background:#D1FAE5; color:#065F46; }
    .flow-tag.admin { background:#DBEAFE; color:#1E40AF; }
  </style>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar.php'; ?>
  <main class="main">
    <header class="topbar">
      <span class="topbar-title">My Claims</span>
      <div class="topbar-actions">
        <div class="avatar-btn"><?= strtoupper(substr($user['username'],0,1)) ?></div>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div class="page-header-left">
          <div class="page-title">My Claim Requests</div>
          <div class="page-sub">Track your claims. Peer claims are reviewed by the finder. Admin claims are reviewed by staff.</div>
        </div>
      </div>

      <?php if (isset($_GET['success'])): ?>
        <?php if ($_GET['success'] === 'peer'): ?>
          <div class="alert alert-success">
            ✅ Claim submitted! The finder has been notified and will review your claim.
            Once approved a chat will open so you can coordinate pickup.
          </div>
        <?php elseif ($_GET['success'] === 'admin'): ?>
          <div class="alert alert-success">
            ✅ Claim submitted! An admin will verify your details and get back to you.
            Please visit the admin office with your student ID if approved.
          </div>
        <?php else: ?>
          <div class="alert alert-success">✅ Claim submitted successfully!</div>
        <?php endif; ?>
      <?php endif; ?>

      <div class="card">
        <div class="table-wrap">
          <?php if ($myClaims): ?>
          <table class="su-table">
            <thead>
              <tr>
                <th>Item</th><th>Type</th><th>Location</th>
                <th>Submitted</th><th>Status</th><th>Note</th><th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($myClaims as $c): ?>
              <?php $isPeer = !in_array($c['category'], $adminCategories); ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($c['item_name']) ?></strong><br>
                  <span style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($c['category']) ?></span>
                </td>
                <td>
                  <span class="flow-tag <?= $isPeer ? 'peer' : 'admin' ?>">
                    <?= $isPeer ? '🤝 Peer' : '🔒 Admin' ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($c['location_found']) ?></td>
                <td><?= date('M j, Y', strtotime($c['claimed_at'])) ?></td>
                <td><span class="badge badge-<?= $c['claim_status'] ?>"><?= ucfirst($c['claim_status']) ?></span></td>
                <td style="font-size:12px;color:var(--text-muted);max-width:150px;">
                  <?= $c['admin_note'] ? htmlspecialchars($c['admin_note']) : '—' ?>
                </td>
                <td>
                  <?php if ($c['claim_status'] === 'approved' && $c['session_id']): ?>
                    <?php if ($c['session_status'] === 'active'): ?>
                      <a href="peer_chat.php?session=<?= $c['session_id'] ?>" class="chat-btn">
                        💬 Open Chat
                      </a>
                    <?php elseif ($c['session_status'] === 'completed'): ?>
                      <a href="peer_chat.php?session=<?= $c['session_id'] ?>" class="chat-btn completed">
                        ✅ View Log
                      </a>
                    <?php endif; ?>
                  <?php elseif ($c['claim_status'] === 'approved' && !$c['session_id']): ?>
                    <!-- Admin approved — go collect -->
                    <span style="font-size:12px;color:#059669;font-weight:600;">
                      ✅ Visit admin office
                    </span>
                  <?php elseif ($c['claim_status'] === 'pending' && $isPeer): ?>
                    <span style="font-size:12px;color:var(--text-muted);">⏳ Awaiting finder</span>
                  <?php elseif ($c['claim_status'] === 'pending'): ?>
                    <span style="font-size:12px;color:var(--text-muted);">⏳ Awaiting admin</span>
                  <?php else: ?>
                    <span style="font-size:12px;color:var(--text-light);">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?>
          <div class="empty-state">
            <div class="icon">📋</div>
            <h3>No claims submitted</h3>
            <p>Browse items and submit a claim if you find something that belongs to you.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- LEGEND -->
      <div style="font-size:12px;color:var(--text-muted);margin-top:8px;display:flex;gap:16px;flex-wrap:wrap;">
        <span><span class="flow-tag peer" style="font-size:11px;">🤝 Peer</span> — Reviewed by the finder directly</span>
        <span><span class="flow-tag admin" style="font-size:11px;">🔒 Admin</span> — Reviewed by admin staff (Electronics, IDs, Keys)</span>
      </div>

    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>