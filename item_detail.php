<?php
require 'includes/auth_check.php';
require 'config/db.php';

$item_id = (int)($_GET['id'] ?? 0);
if (!$item_id) {
    header("Location: dashboard.php");
    exit();
}

// Load current user
$stmt = $pdo->prepare("SELECT id, username, email, course_year, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$isAdmin = $user['role'] === 'admin';

// ── Load item + finder ──────────────────────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT i.item_id, i.item_name, i.category, i.description,
            i.location_found, i.status, i.image_path, i.created_at,
            i.finder_id,
            u.username AS finder_name, u.email AS finder_email,
            u.course_year AS finder_course
     FROM items i
     JOIN users u ON i.finder_id = u.id
     WHERE i.item_id = ?"
);
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: dashboard.php?error=notfound");
    exit();
}

$isOwnItem = ($item['finder_id'] == $_SESSION['user_id']);

$adminCategories = ['Electronics', 'IDs/Wallets', 'Keys'];
$isPeer          = !in_array($item['category'], $adminCategories);

$icons = [
    'Electronics' => '💻', 'IDs/Wallets' => '🪪', 'Bags'        => '🎒',
    'Accessories' => '⌚', 'Books'        => '📚', 'Clothing'    => '🧥',
    'Keys'        => '🔑', 'Other'        => '📦',
];
$icon = $icons[$item['category']] ?? '📦';

// ── Load approved claim (if any) ────────────────────────────────────────────
$claimStmt = $pdo->prepare(
    "SELECT c.claim_id, c.claim_details, c.claimed_at, c.admin_note, c.claim_status,
            u.username AS claimant_name, u.email AS claimant_email,
            u.course_year AS claimant_course
     FROM claims c
     JOIN users u ON c.user_id = u.id
     WHERE c.item_id = ? AND c.claim_status = 'approved'
     LIMIT 1"
);
$claimStmt->execute([$item_id]);
$approvedClaim = $claimStmt->fetch();

// ── Load peer session + exchange log (if any) ───────────────────────────────
$sessionStmt = $pdo->prepare(
    "SELECT ps.session_id, ps.status AS session_status,
            ps.created_at AS session_started, ps.completed_at,
            el.confirmed_by_claimant, el.confirmed_by_finder,
            el.exchange_photo_path, el.exchange_notes,
            el.finder_contact, el.claimant_contact
     FROM peer_sessions ps
     LEFT JOIN exchange_logs el ON el.session_id = ps.session_id
     WHERE ps.item_id = ?
     ORDER BY ps.created_at DESC
     LIMIT 1"
);
$sessionStmt->execute([$item_id]);
$session = $sessionStmt->fetch();

// ── Load ALL claims for admin view ──────────────────────────────────────────
$allClaims = [];
if ($isAdmin) {
    $acStmt = $pdo->prepare(
        "SELECT c.claim_id, c.claim_status, c.claim_details, c.claimed_at, c.admin_note,
                u.username AS claimant_name, u.email AS claimant_email
         FROM claims c
         JOIN users u ON c.user_id = u.id
         WHERE c.item_id = ?
         ORDER BY c.claimed_at DESC"
    );
    $acStmt->execute([$item_id]);
    $allClaims = $acStmt->fetchAll();
}

// check if current student already claimed this?
$myClaimStmt = $pdo->prepare(
    "SELECT claim_id, claim_status FROM claims WHERE item_id = ? AND user_id = ?"
);
$myClaimStmt->execute([$item_id, $_SESSION['user_id']]);
$myClaim = $myClaimStmt->fetch();

// ── Back URL
$backUrl    = $_SERVER['HTTP_REFERER'] ?? 'browse.php';
$activePage = 'browse';
$rootPath   = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= htmlspecialchars($item['item_name']) ?> – SU Lost &amp; Found</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="includes/common.css">
  <style>
    
  </style>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar.php'; ?>
  <main class="main">

    <!-- TOPBAR -->
    <header class="topbar">
      <span class="topbar-title">Item Details</span>
      <div class="topbar-actions">
        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn-outline" style="font-size:12px;padding:5px 12px;">← Back</a>
        <div class="avatar-btn"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
      </div>
    </header>

    <div class="content">
      <div class="detail-wrap">

        <!-- PAGE TITLE -->
        <div class="page-header">
          <div class="page-header-left">
            <div class="page-title"><?= htmlspecialchars($item['item_name']) ?></div>
            <div class="page-sub">
              Item #LF-<?= str_pad($item['item_id'], 4, '0', STR_PAD_LEFT) ?>
              &nbsp;·&nbsp; Reported <?= date('M j, Y', strtotime($item['created_at'])) ?>
            </div>
          </div>
        </div>

        <div class="detail-grid">

          <!-- LEFT: IMAGE + QUICK META -->
          <div>
            <div class="item-image-box">
              <div class="item-image-area">
                <?php if ($item['image_path'] && file_exists($item['image_path'])): ?>
                  <img src="<?= htmlspecialchars($item['image_path']) ?>"
                       alt="<?= htmlspecialchars($item['item_name']) ?>">
                <?php else: ?>
                  <?= $icon ?>
                <?php endif; ?>
              </div>
              <div class="item-image-meta">
                <div class="d-flex gap-2 flex-wrap align-items-center">
                  <span class="badge badge-<?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span>
                  <span class="flow-tag <?= $isPeer ? 'flow-peer' : 'flow-admin' ?>">
                    <?= $isPeer ? '🤝 Peer' : '🔒 Admin' ?>
                  </span>
                </div>
                <div style="margin-top:10px;font-size:12px;color:var(--text-muted);">
                  📂 <?= htmlspecialchars($item['category']) ?>
                </div>
                <div style="margin-top:4px;font-size:12px;color:var(--text-muted);">
                   <?= htmlspecialchars($item['location_found']) ?>
                </div>
                <div style="margin-top:4px;font-size:12px;color:var(--text-muted);">
                  🗓 <?= date('M j, Y · g:i a', strtotime($item['created_at'])) ?>
                </div>
              </div>
            </div>
          </div>

          <!-- RIGHT: ALL DETAIL CARDS -->
          <div>

            <!-- ── STATUS BANNER ─────────────────────────────────── -->
            <?php if ($item['status'] === 'available'): ?>
              <div class="status-banner available">
                <span class="banner-icon">🔍</span>
                <div>
                  <strong>Awaiting its owner</strong><br>
                  <span style="font-size:12px;">This item is available to claim. If it belongs to you, submit a claim below.</span>
                </div>
              </div>

            <?php elseif ($item['status'] === 'claimed'): ?>
              <div class="status-banner claimed">
                <span class="banner-icon">📋</span>
                <div>
                  <strong>Claim approved</strong><br>
                  <span style="font-size:12px;">
                    <?php if ($isPeer): ?>
                      A peer exchange session is open. The finder and claimant are coordinating pickup.
                    <?php else: ?>
                      An admin has approved a claim. The student has been asked to collect from the office.
                    <?php endif; ?>
                  </span>
                </div>
              </div>

            <?php else: ?>
              <div class="status-banner returned">
                <span class="banner-icon">✅</span>
                <div>
                  <strong>Successfully returned</strong><br>
                  <span style="font-size:12px;">This item has been reunited with its owner and is no longer available.</span>
                </div>
              </div>
            <?php endif; ?>

            <!-- ── ITEM DETAILS ──────────────────────────────────── -->
            <div class="info-card">
              <div class="info-card-title">Item Details</div>
              <div class="detail-row">
                <span class="detail-key">Item Name</span>
                <span class="detail-val"><?= htmlspecialchars($item['item_name']) ?></span>
              </div>
              <div class="detail-row">
                <span class="detail-key">Category</span>
                <span class="detail-val"><?= $icon ?> <?= htmlspecialchars($item['category']) ?></span>
              </div>
              <div class="detail-row">
                <span class="detail-key">Location Found</span>
                <span class="detail-val"> <?= htmlspecialchars($item['location_found']) ?></span>
              </div>
              <div class="detail-row">
                <span class="detail-key">Date Reported</span>
                <span class="detail-val"><?= date('M j, Y g:i a', strtotime($item['created_at'])) ?></span>
              </div>
              <div class="detail-row">
                <span class="detail-key">Description</span>
                <span class="detail-val"><?= nl2br(htmlspecialchars($item['description'])) ?></span>
              </div>
              <div class="detail-row">
                <span class="detail-key">Claim Type</span>
                <span class="detail-val">
                  <span class="flow-tag <?= $isPeer ? 'flow-peer' : 'flow-admin' ?>">
                    <?= $isPeer ? ' Peer-to-Peer' : ' Admin Verified' ?>
                  </span>
                </span>
              </div>
            </div>

            <!-- ── REPORTED BY ────────────────────────────────────── -->
            <div class="info-card">
              <div class="info-card-title">Reported By</div>
              <div class="person-card">
                <div class="person-avatar">
                  <?= strtoupper(substr($item['finder_name'], 0, 1)) ?>
                </div>
                <div>
                  <div class="person-name"><?= htmlspecialchars($item['finder_name']) ?></div>
                  <div class="person-meta">
                    <?php if ($isAdmin || $isOwnItem): ?>
                      <?= htmlspecialchars($item['finder_email']) ?>
                      <?php if ($item['finder_course']): ?>
                        · <?= htmlspecialchars($item['finder_course']) ?>
                      <?php endif; ?>
                    <?php else: ?>
                      <?= htmlspecialchars($item['finder_course'] ?? 'Student') ?>
                    <?php endif; ?>
                  </div>
                </div>
                <?php if ($isOwnItem): ?>
                  <span class="badge badge-admin ms-auto" style="font-size:10px;">You</span>
                <?php endif; ?>
              </div>
            </div>

            <!-- ── CLAIM INFO ─────────────────────────────────────── -->
            <?php if ($approvedClaim): ?>
            <div class="info-card">
              <div class="info-card-title">
                <?= $item['status'] === 'returned' ? 'Claimed & Returned By' : 'Approved Claimant' ?>
              </div>

              <?php if ($isAdmin || $isOwnItem): ?>
                <!-- Admin and the finder see full claimant details -->
                <div class="person-card">
                  <div class="person-avatar gold">
                    <?= strtoupper(substr($approvedClaim['claimant_name'], 0, 1)) ?>
                  </div>
                  <div>
                    <div class="person-name"><?= htmlspecialchars($approvedClaim['claimant_name']) ?></div>
                    <div class="person-meta">
                      <?= htmlspecialchars($approvedClaim['claimant_email']) ?>
                      <?php if ($approvedClaim['claimant_course']): ?>
                        · <?= htmlspecialchars($approvedClaim['claimant_course']) ?>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <div style="margin-top:12px;">
                  <div class="detail-row">
                    <span class="detail-key">Claim submitted</span>
                    <span class="detail-val"><?= date('M j, Y g:i a', strtotime($approvedClaim['claimed_at'])) ?></span>
                  </div>
                  <?php if ($approvedClaim['claim_details']): ?>
                  <div class="detail-row">
                    <span class="detail-key">Their reason</span>
                    <span class="detail-val" style="font-style:italic;">
                      "<?= htmlspecialchars($approvedClaim['claim_details']) ?>"
                    </span>
                  </div>
                  <?php endif; ?>
                  <?php if ($approvedClaim['admin_note']): ?>
                  <div class="detail-row">
                    <span class="detail-key">Admin note</span>
                    <span class="detail-val"><?= htmlspecialchars($approvedClaim['admin_note']) ?></span>
                  </div>
                  <?php endif; ?>
                </div>

              <?php else: ?>
                <!-- Other students just see that it's been claimed -->
                <p style="font-size:13px;color:var(--text-muted);margin:0;">
                  This item has been claimed by a verified student.
                  Claimant details are kept private.
                </p>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ── EXCHANGE LOG (returned items) ─────────────────── -->
            <?php if ($session && ($isAdmin || $isOwnItem || ($approvedClaim && $approvedClaim['claimant_email'] === $user['email']))): ?>
            <div class="info-card">
              <div class="info-card-title">Exchange Log</div>

              <!-- Timeline -->
              <div class="timeline">
                <div class="tl-item">
                  <div class="tl-dot gold">📋</div>
                  <div class="tl-content">
                    <div class="tl-label">Claim approved</div>
                    <div class="tl-date">
                      <?= date('M j, Y g:i a', strtotime($approvedClaim['claimed_at'] ?? $session['session_started'])) ?>
                    </div>
                  </div>
                </div>
                <div class="tl-item">
                  <div class="tl-dot blue">💬</div>
                  <div class="tl-content">
                    <div class="tl-label">Chat session opened</div>
                    <div class="tl-date"><?= date('M j, Y g:i a', strtotime($session['session_started'])) ?></div>
                  </div>
                </div>
                <?php if ($session['session_status'] === 'completed'): ?>
                <div class="tl-item">
                  <div class="tl-dot done">✅</div>
                  <div class="tl-content">
                    <div class="tl-label">Exchange completed</div>
                    <div class="tl-date"><?= date('M j, Y g:i a', strtotime($session['completed_at'])) ?></div>
                  </div>
                </div>
                <?php endif; ?>
              </div>

              <!-- Confirmation status -->
              <div class="exchange-box">
                <div class="exchange-row">
                  <span class="exchange-key">Claimant confirmed</span>
                  <span class="exchange-val">
                    <span class="confirm-dot <?= $session['confirmed_by_claimant'] ? 'yes' : 'no' ?>">
                      <?= $session['confirmed_by_claimant'] ? '✅ Yes' : '⏳ Pending' ?>
                    </span>
                  </span>
                </div>
                <div class="exchange-row">
                  <span class="exchange-key">Finder confirmed</span>
                  <span class="exchange-val">
                    <span class="confirm-dot <?= $session['confirmed_by_finder'] ? 'yes' : 'no' ?>">
                      <?= $session['confirmed_by_finder'] ? '✅ Yes' : '⏳ Pending' ?>
                    </span>
                  </span>
                </div>
                <?php if ($session['exchange_notes']): ?>
                <div class="exchange-row">
                  <span class="exchange-key">Handover notes</span>
                  <span class="exchange-val"><?= htmlspecialchars($session['exchange_notes']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($session['finder_contact']): ?>
                <div class="exchange-row">
                  <span class="exchange-key">Finder contact</span>
                  <span class="exchange-val"><?= htmlspecialchars($session['finder_contact']) ?></span>
                </div>
                <?php endif; ?>
              </div>

              <!-- Exchange photo -->
              <?php if ($session['exchange_photo_path'] && file_exists($session['exchange_photo_path'])): ?>
              <div style="margin-top:12px;">
                <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:6px;">
                  📸 Handover Photo
                </div>
                <img src="<?= htmlspecialchars($session['exchange_photo_path']) ?>"
                     alt="Exchange photo"
                     style="max-width:240px;border-radius:8px;border:1px solid var(--gray-border);">
              </div>
              <?php endif; ?>

              <!-- Link to chat for admin -->
              <?php if ($isAdmin && $session['session_id']): ?>
              <div style="margin-top:14px;">
                <a href="peer_chat.php?session=<?= $session['session_id'] ?>" class="btn-navy" style="font-size:12px;padding:7px 14px;">
                  💬 View Full Chat Log
                </a>
              </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ── ALL CLAIMS (admin only) ────────────────────────── -->
            <?php if ($isAdmin && $allClaims): ?>
            <div class="info-card">
              <div class="info-card-title">All Claims (<?= count($allClaims) ?>)</div>
              <div class="claims-section">
                <div class="table-wrap">
                  <table class="su-table">
                    <thead>
                      <tr>
                        <th>Claimant</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Note</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($allClaims as $c): ?>
                      <tr>
                        <td>
                          <strong><?= htmlspecialchars($c['claimant_name']) ?></strong><br>
                          <span style="font-size:11px;color:var(--text-muted);">
                            <?= htmlspecialchars($c['claimant_email']) ?>
                          </span>
                        </td>
                        <td style="font-size:12px;"><?= date('M j, Y', strtotime($c['claimed_at'])) ?></td>
                        <td>
                          <span class="badge badge-<?= $c['claim_status'] ?>">
                            <?= ucfirst($c['claim_status']) ?>
                          </span>
                        </td>
                        <td style="font-size:12px;color:var(--text-muted);max-width:160px;">
                          <?= $c['admin_note'] ? htmlspecialchars($c['admin_note']) : '—' ?>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <!-- ── ACTION BAR   -->
            <div class="action-bar">
              <?php if (!$isAdmin && $item['status'] === 'available' && !$isOwnItem): ?>
                <?php if ($myClaim): ?>
                  <span style="font-size:13px;color:var(--text-muted);padding:8px 0;">
                    ⏳ You submitted a claim on <?= date('M j, Y', strtotime($myClaim['claimed_at'] ?? 'now')) ?>
                    — status: <strong><?= ucfirst($myClaim['claim_status']) ?></strong>
                  </span>
                <?php else: ?>
                  <a href="claim_modal.php?item_id=<?= $item['item_id'] ?>" class="btn-gold">
                    <?= $isPeer ? ' Submit a Claim' : ' Submit a Claim' ?>
                  </a>
                <?php endif; ?>
              <?php endif; ?>

              <?php if (!$isAdmin && $item['status'] === 'claimed' && $session && $session['session_status'] === 'active'): ?>
                <?php
                  // Check if this student is part of the session
                  $sessionCheckStmt = $pdo->prepare(
                      "SELECT session_id FROM peer_sessions
                       WHERE session_id = ? AND (finder_id = ? OR claimant_id = ?)"
                  );
                  $sessionCheckStmt->execute([$session['session_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
                  if ($sessionCheckStmt->fetch()):
                ?>
                  <a href="peer_chat.php?session=<?= $session['session_id'] ?>" class="btn-gold">
                    💬 Open Chat
                  </a>
                <?php endif; ?>
              <?php endif; ?>

              <?php if ($isAdmin): ?>
                <a href="admin/claim_reviews.php" class="btn-navy" style="font-size:13px;padding:8px 16px;">
                  ↺ Claim Reviews
                </a>
                <a href="admin/manage_items.php" class="btn-outline" style="font-size:13px;padding:8px 16px;">
                  ≡ Manage Items
                </a>
              <?php endif; ?>

              <a href="browse.php" class="btn-outline" style="font-size:13px;padding:8px 16px;">
                ← Browse Items
              </a>
            </div>

          </div><!-- end right col -->
        </div><!-- end detail-grid -->
      </div><!-- end detail-wrap -->
    </div><!-- end content -->
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>