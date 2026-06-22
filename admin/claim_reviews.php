<?php
require '../includes/auth_check.php';
require '../includes/admin_check.php';
require '../config/db.php';

$stmt = $pdo->prepare("SELECT username, course_year, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['claim_id'])) {
    $claim_id  = (int)$_POST['claim_id'];
    $action    = $_POST['action'];
    $adminNote = htmlspecialchars(trim($_POST['admin_note'] ?? ''));

    if (!in_array($action, ['approve','reject'])) {
        $error = 'Invalid action.';
    } else {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        $cInfo = $pdo->prepare(
            "SELECT c.user_id, i.item_id, i.item_name FROM claims c JOIN items i ON c.item_id=i.item_id WHERE c.claim_id=?"
        );
        $cInfo->execute([$claim_id]);
        $claimInfo = $cInfo->fetch();

        $pdo->prepare("UPDATE claims SET claim_status=?, admin_note=?, reviewed_at=NOW() WHERE claim_id=?")
            ->execute([$newStatus, $adminNote, $claim_id]);

        if ($action === 'approve' && $claimInfo) {
            $pdo->prepare("UPDATE items SET status='claimed' WHERE item_id=?")->execute([$claimInfo['item_id']]);
            $pdo->prepare(
                "UPDATE claims SET claim_status='rejected', admin_note='Another claim was approved for this item.'
                 WHERE item_id=? AND claim_id != ? AND claim_status='pending'"
            )->execute([$claimInfo['item_id'], $claim_id]);
        }

        if ($claimInfo) {
            $msg = $action === 'approve'
                ? "✅ Your claim for '{$claimInfo['item_name']}' was approved! Please visit the admin office to collect your item."
                : "❌ Your claim for '{$claimInfo['item_name']}' was rejected. " . ($adminNote ?: '');
            $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?,?)")->execute([$claimInfo['user_id'], $msg]);
        }

        $success = "Claim #$claim_id has been " . ucfirst($newStatus) . ".";
    }
}

$filter = $_GET['filter'] ?? 'pending';
if (!in_array($filter, ['pending','approved','rejected','all'])) $filter = 'pending';
$whereClause = $filter !== 'all' ? "WHERE c.claim_status = '$filter'" : '';

$claims = $pdo->query(
    "SELECT c.claim_id, c.claim_status, c.claim_details, c.admin_note, c.claimed_at,
            u.username AS claimant, u.email AS claimant_email,
            i.item_name, i.category, i.location_found, i.item_id
     FROM claims c
     JOIN users u ON c.user_id = u.id
     JOIN items i ON c.item_id = i.item_id
     $whereClause ORDER BY c.claimed_at DESC"
)->fetchAll();

$activePage = 'claim_reviews';
$rootPath   = '../';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Claim Reviews – SU Lost &amp; Found</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../includes/common.css">
  <style>
    .filter-tabs { display:flex; gap:6px; margin-bottom:18px; flex-wrap:wrap; }
    .filter-tab {
      padding:6px 16px; border-radius:999px; font-size:12px; font-weight:600;
      border:1px solid var(--gray-border); background:#fff;
      color:var(--text-muted); text-decoration:none;
      transition:background var(--trans),color var(--trans);
    }
    .filter-tab.active { background:var(--navy); color:#fff; border-color:var(--navy); }
    .details-cell { font-size:12px; color:var(--text-muted); max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  </style>
</head>
<body>
<div class="app">
  <?php include '../includes/sidebar.php';?>
  <main class="main">
    <header class="topbar">
      <span class="topbar-title">Claim Reviews</span>
      <div class="topbar-actions">
        <span class="badge badge-admin" style="font-size:12px;padding:5px 12px;">Admin</span>
        <div class="avatar-btn"><?= strtoupper(substr($user['username'],0,1)) ?></div>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div class="page-header-left">
          <div class="page-title">Claim Reviews</div>
          <div class="page-sub">Approve or reject student claims. Approving marks the item as claimed.</div>
        </div>
      </div>

      <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>
      <?php if ($error):   ?><div class="alert alert-danger">❌ <?= $error ?></div><?php endif; ?>

      <div class="filter-tabs">
        <?php foreach(['pending'=>'⏳ Pending','approved'=>'✅ Approved','rejected'=>'❌ Rejected','all'=>'All'] as $f=>$label): ?>
        <a href="?filter=<?= $f ?>" class="filter-tab <?= $filter===$f?'active':'' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>

      <div class="card">
        <div class="table-wrap">
          <?php if ($claims): ?>
          <table class="su-table">
            <thead>
              <tr><th>#</th><th>Item</th><th>Claimant</th><th>Details</th><th>Submitted</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($claims as $c): ?>
              <tr>
                <td style="font-weight:600;color:var(--text-muted);">#<?= $c['claim_id'] ?></td>
                <td>
                  <strong><?= htmlspecialchars($c['item_name']) ?></strong><br>
                  <span style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($c['category']) ?> · <?= htmlspecialchars($c['location_found']) ?></span>
                </td>
                <td>
                  <?= htmlspecialchars($c['claimant']) ?><br>
                  <span style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($c['claimant_email']) ?></span>
                </td>
                <td class="details-cell" title="<?= htmlspecialchars($c['claim_details']) ?>">
                  <?= htmlspecialchars($c['claim_details']) ?>
                </td>
                <td><?= date('M j, Y', strtotime($c['claimed_at'])) ?></td>
                <td><span class="badge badge-<?= $c['claim_status'] ?>"><?= ucfirst($c['claim_status']) ?></span></td>
                <td>
                  <?php if ($c['claim_status'] === 'pending'): ?>
                  <div class="d-flex gap-1">
                    <button class="btn-success" style="font-size:11px;padding:4px 10px;"
                            onclick="openReview(<?= $c['claim_id'] ?>,'approve','<?= htmlspecialchars(addslashes($c['item_name'])) ?>','<?= htmlspecialchars(addslashes($c['claimant'])) ?>')">✅ Approve</button>
                    <button class="btn-danger" style="font-size:11px;padding:4px 10px;"
                            onclick="openReview(<?= $c['claim_id'] ?>,'reject','<?= htmlspecialchars(addslashes($c['item_name'])) ?>','<?= htmlspecialchars(addslashes($c['claimant'])) ?>')">❌ Reject</button>
                  </div>
                  <?php else: ?>
                  <span style="font-size:12px;color:var(--text-light);"><?= $c['admin_note'] ? htmlspecialchars(substr($c['admin_note'],0,40)).'…' : 'Reviewed' ?></span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?>
          <div class="empty-state">
            <div class="icon">📋</div>
            <h3>No <?= $filter==='all'?'':$filter ?> claims</h3>
            <p>Nothing to review right now.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- REVIEW MODAL -->
<div class="modal-overlay" id="reviewOverlay" onclick="if(event.target===this)closeReview()">
  <div class="modal">
    <div class="modal-body">
      <div class="modal-header">
        <div class="modal-title" id="reviewTitle">Review Claim</div>
        <button class="close-btn" onclick="closeReview()">✕</button>
      </div>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;" id="reviewDesc"></p>
      <form method="POST">
        <input type="hidden" name="claim_id" id="rClaimId">
        <input type="hidden" name="action"   id="rAction">
        <div class="form-group">
          <label class="form-label">Note to student (optional)</label>
          <textarea name="admin_note" class="form-control" rows="3"
            placeholder="e.g. Please visit the security office with your student ID by Friday."></textarea>
        </div>
        <div class="modal-actions" style="margin-top:14px;">
          <button type="submit" id="rSubmitBtn" class="btn-gold" style="flex:1;">Confirm</button>
          <button type="button" class="btn-outline" onclick="closeReview()" style="flex:1;text-align:center;">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openReview(id, action, item, claimant) {
  document.getElementById('rClaimId').value = id;
  document.getElementById('rAction').value  = action;
  const isApprove = action === 'approve';
  document.getElementById('reviewTitle').textContent = (isApprove ? '✅ Approve' : '❌ Reject') + ' Claim #' + id;
  document.getElementById('reviewDesc').textContent  = (isApprove ? 'Approve' : 'Reject') + ' claim by ' + claimant + ' for "' + item + '"?';
  const btn = document.getElementById('rSubmitBtn');
  btn.className   = isApprove ? 'btn-success' : 'btn-danger';
  btn.style.flex  = '1';
  btn.textContent = isApprove ? '✅ Approve' : '❌ Reject';
  document.getElementById('reviewOverlay').classList.add('open');
}
function closeReview() { document.getElementById('reviewOverlay').classList.remove('open'); }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>