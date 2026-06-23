<?php
require 'includes/auth_check.php';
require 'config/db.php';

$stmt = $pdo->prepare("SELECT username, course_year, role, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$success = $error = '';

// Finder approves a claim → create peer session, reject others
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_claim'])) {
    $claim_id = (int)$_POST['claim_id'];
    $item_id  = (int)$_POST['item_id'];

    // Verify this item belongs to the current user
    $check = $pdo->prepare("SELECT finder_id FROM items WHERE item_id = ?");
    $check->execute([$item_id]);
    $itemRow = $check->fetch();

    if ($itemRow && $itemRow['finder_id'] == $_SESSION['user_id']) {
        // Get claim details
        $cStmt = $pdo->prepare("SELECT user_id FROM claims WHERE claim_id = ?");
        $cStmt->execute([$claim_id]);
        $claim = $cStmt->fetch();

        if ($claim) {
            // Check no active session already exists
            $existing = $pdo->prepare("SELECT session_id FROM peer_sessions WHERE item_id = ? AND status = 'active'");
            $existing->execute([$item_id]);

            if (!$existing->fetch()) {
                // Create peer session
                $pdo->prepare(
                    "INSERT INTO peer_sessions (item_id, finder_id, claimant_id, claim_id)
                     VALUES (?, ?, ?, ?)"
                )->execute([$item_id, $_SESSION['user_id'], $claim['user_id'], $claim_id]);

                $session_id = $pdo->lastInsertId();

                // Create exchange log entry
                $pdo->prepare(
                    "INSERT INTO exchange_logs (session_id, item_id, finder_id, claimant_id,
                     finder_contact, claimant_contact)
                     VALUES (?, ?, ?, ?,
                     (SELECT email FROM users WHERE id = ?),
                     (SELECT email FROM users WHERE id = ?))"
                )->execute([$session_id, $item_id, $_SESSION['user_id'], $claim['user_id'],
                            $_SESSION['user_id'], $claim['user_id']]);

                // Update approved claim status
                $pdo->prepare("UPDATE claims SET claim_status='approved' WHERE claim_id=?")
                    ->execute([$claim_id]);

                // Reject all other pending claims for this item
                $pdo->prepare(
                    "UPDATE claims SET claim_status='rejected',
                     admin_note='Another claim was approved by the finder.'
                     WHERE item_id=? AND claim_id != ? AND claim_status='pending'"
                )->execute([$item_id, $claim_id]);

                // Notify claimant
                $itemName = $pdo->prepare("SELECT item_name FROM items WHERE item_id=?");
                $itemName->execute([$item_id]);
                $iName = $itemName->fetchColumn();

                $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?,?)")
                    ->execute([$claim['user_id'],
                        "✅ Your claim for '$iName' was approved by the finder! Open your chat to coordinate pickup."]);

                // Notify admin
                $admins = $pdo->query("SELECT id FROM users WHERE role='admin'")->fetchAll();
                $nStmt  = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?,?)");
                foreach ($admins as $admin) {
                    $nStmt->execute([$admin['id'],
                        "🔄 A peer exchange session has started for: $iName"]);
                }

                $success = "Claim approved! A chat session has been opened with the claimant.";
            } else {
                $error = "An active session already exists for this item.";
            }
        }
    } else {
        $error = "Unauthorized action.";
    }
}

// Finder rejects a specific claim
if (isset($_GET['reject_claim'])) {
    $claim_id = (int)$_GET['reject_claim'];
    $check = $pdo->prepare(
        "SELECT c.claim_id FROM claims c
         JOIN items i ON c.item_id = i.item_id
         WHERE c.claim_id = ? AND i.finder_id = ?"
    );
    $check->execute([$claim_id, $_SESSION['user_id']]);
    if ($check->fetch()) {
        $pdo->prepare("UPDATE claims SET claim_status='rejected',
                       admin_note='Rejected by the finder.' WHERE claim_id=?")
            ->execute([$claim_id]);

        // Notify claimant
        $cInfo = $pdo->prepare(
            "SELECT c.user_id, i.item_name FROM claims c
             JOIN items i ON c.item_id=i.item_id WHERE c.claim_id=?"
        );
        $cInfo->execute([$claim_id]);
        $cRow = $cInfo->fetch();
        if ($cRow) {
            $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?,?)")
                ->execute([$cRow['user_id'],
                    "❌ Your claim for '{$cRow['item_name']}' was reviewed by the finder."]);
        }
        $success = "Claim rejected.";
    }
}

// Fetch this user's reported items
$items = $pdo->prepare(
    "SELECT item_id, item_name, category, location_found, status, created_at
     FROM items WHERE finder_id = ? ORDER BY created_at DESC"
);
$items->execute([$_SESSION['user_id']]);
$myItems = $items->fetchAll();

// Fetch pending claims on this user's items
$claimsStmt = $pdo->prepare(
    "SELECT c.claim_id, c.item_id, c.claim_details, c.claim_status, c.claimed_at,
            u.username AS claimant_name, u.email AS claimant_email,
            i.item_name,
            ps.session_id
     FROM claims c
     JOIN users u  ON c.user_id   = u.id
     JOIN items i  ON c.item_id   = i.item_id
     LEFT JOIN peer_sessions ps ON ps.claim_id = c.claim_id AND ps.status = 'active'
     WHERE i.finder_id = ?
     ORDER BY c.claimed_at DESC"
);
$claimsStmt->execute([$_SESSION['user_id']]);
$itemClaims = $claimsStmt->fetchAll();

// Group claims by item_id
$claimsByItem = [];
foreach ($itemClaims as $c) {
    $claimsByItem[$c['item_id']][] = $c;
}

$activePage = 'my_reports';
$rootPath   = '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>My Reports – SU Lost &amp; Found</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="includes/common.css">
  <style>
    .claims-panel { background:#f8faff; border:1px solid var(--gray-border); border-radius:var(--radius); margin-top:8px; padding:14px; }
    .claims-panel-title { font-size:12px; font-weight:700; color:var(--navy); margin-bottom:10px; text-transform:uppercase; letter-spacing:.05em; }
    .claim-row { background:#fff; border:1px solid var(--gray-border); border-radius:8px; padding:12px 14px; margin-bottom:8px; }
    .claim-row:last-child { margin-bottom:0; }
    .claim-name  { font-size:13px; font-weight:700; color:var(--navy); }
    .claim-email { font-size:11px; color:var(--text-muted); }
    .claim-detail{ font-size:12px; color:var(--text-main); margin:6px 0; line-height:1.5; }
    .claim-date  { font-size:11px; color:var(--text-light); }
    .chat-link   { background:var(--navy); color:var(--gold); border:none; border-radius:6px; padding:5px 12px; font-size:12px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:5px; }
    .chat-link:hover { opacity:.85; color:var(--gold); }
    .toggle-claims { font-size:12px; font-weight:600; color:var(--navy); background:none; border:1px solid var(--gray-border); border-radius:6px; padding:3px 10px; cursor:pointer; }
  </style>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar.php'; ?>
  <main class="main">
    <header class="topbar">
      <span class="topbar-title">My Reports</span>
      <div class="topbar-actions">
        <a href="report_item.php" class="btn-gold">＋ Report New Item</a>
        <div class="avatar-btn"><?= strtoupper(substr($user['username'],0,1)) ?></div>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div class="page-header-left">
          <div class="page-title">Items I've Reported</div>
          <div class="page-sub">Review claims on your reported items and approve the rightful owner.</div>
        </div>
      </div>

      <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>
      <?php if ($error):   ?><div class="alert alert-danger">❌ <?= $error ?></div><?php endif; ?>
      <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">✅ Item removed.</div><?php endif; ?>

      <div class="card">
        <div class="table-wrap">
          <?php if ($myItems): ?>
          <table class="su-table">
            <thead>
              <tr>
                <th>Item</th><th>Category</th><th>Location</th>
                <th>Status</th><th>Date Reported</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($myItems as $it): ?>
              <!-- ITEM ROW -->
              <tr>
                <td><strong><?= htmlspecialchars($it['item_name']) ?></strong></td>
                <td><?= htmlspecialchars($it['category']) ?></td>
                <td><?= htmlspecialchars($it['location_found']) ?></td>
                <td><span class="badge badge-<?= $it['status'] ?>"><?= ucfirst($it['status']) ?></span></td>
                <td><?= date('M j, Y', strtotime($it['created_at'])) ?></td>
                <td class="d-flex gap-1 flex-wrap">
                  <?php if ($it['status'] === 'available'): ?>
                    <a href="delete_item.php?id=<?= $it['item_id'] ?>"
                       onclick="return confirm('Remove this item?')"
                       class="btn-danger" style="font-size:12px;padding:4px 10px;">Remove</a>
                  <?php else: ?>
                    <span style="font-size:12px;color:var(--text-light);">Locked</span>
                  <?php endif; ?>

                  <?php if (!empty($claimsByItem[$it['item_id']])): ?>
                    <button class="toggle-claims"
                            onclick="toggleClaims('claims-<?= $it['item_id'] ?>')">
                      👁 Claims (<?= count($claimsByItem[$it['item_id']]) ?>)
                    </button>
                  <?php endif; ?>
                </td>
              </tr>

              <!-- CLAIMS PANEL (collapsible) -->
              <?php if (!empty($claimsByItem[$it['item_id']])): ?>
              <tr id="claims-<?= $it['item_id'] ?>" style="display:none;">
                <td colspan="6" style="padding:0 14px 14px;">
                  <div class="claims-panel">
                    <div class="claims-panel-title">
                      Claims for "<?= htmlspecialchars($it['item_name']) ?>"
                    </div>

                    <?php foreach ($claimsByItem[$it['item_id']] as $c): ?>
                    <div class="claim-row">
                      <div class="d-flex justify-content-between align-items-start">
                        <div>
                          <div class="claim-name"><?= htmlspecialchars($c['claimant_name']) ?></div>
                          <div class="claim-email"><?= htmlspecialchars($c['claimant_email']) ?></div>
                          <div class="claim-detail">"<?= htmlspecialchars($c['claim_details']) ?>"</div>
                          <div class="claim-date">Submitted <?= date('M j, Y g:i a', strtotime($c['claimed_at'])) ?></div>
                        </div>
                        <div class="d-flex gap-2 align-items-center ms-3 flex-shrink-0">
                          <span class="badge badge-<?= $c['claim_status'] ?>"><?= ucfirst($c['claim_status']) ?></span>

                          <?php if ($c['claim_status'] === 'pending'): ?>
                            <!-- Approve → opens peer session -->
                            <form method="POST" style="display:inline;">
                              <input type="hidden" name="claim_id" value="<?= $c['claim_id'] ?>">
                              <input type="hidden" name="item_id"  value="<?= $c['item_id'] ?>">
                              <button type="submit" name="approve_claim"
                                      class="btn-success" style="font-size:12px;padding:4px 12px;"
                                      onclick="return confirm('Approve this claim and open a chat session?')">
                                ✅ Approve
                              </button>
                            </form>
                            <a href="?reject_claim=<?= $c['claim_id'] ?>"
                               onclick="return confirm('Reject this claim?')"
                               class="btn-danger" style="font-size:12px;padding:4px 10px;">❌ Reject</a>

                          <?php elseif ($c['claim_status'] === 'approved' && $c['session_id']): ?>
                            <!-- Chat opened -->
                            <a href="peer_chat.php?session=<?= $c['session_id'] ?>" class="chat-link">
                              💬 Open Chat
                            </a>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <?php endforeach; ?>

                  </div>
                </td>
              </tr>
              <?php endif; ?>

              <?php endforeach; ?>
            </tbody>
          </table>

          <?php else: ?>
          <div class="empty-state">
            <div class="icon">📭</div>
            <h3>No reports yet</h3>
            <p>Found something on campus? <a href="report_item.php" style="color:var(--gold);font-weight:600;">Report it here</a>.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
function toggleClaims(id) {
  const row = document.getElementById(id);
  row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>