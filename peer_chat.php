<?php
require 'includes/auth_check.php';
require 'config/db.php';

$stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$session_id = (int)($_GET['session'] ?? 0);
if (!$session_id) { header("Location: dashboard.php"); exit(); }

// Load session — verify current user is part of it
$sStmt = $pdo->prepare(
    "SELECT ps.*, i.item_name, i.item_id, i.category,
            f.username AS finder_name,  f.email AS finder_email,
            c.username AS claimant_name, c.email AS claimant_email
     FROM peer_sessions ps
     JOIN items i ON ps.item_id    = i.item_id
     JOIN users f ON ps.finder_id  = f.id
     JOIN users c ON ps.claimant_id= c.id
     WHERE ps.session_id = ?
     AND (ps.finder_id = ? OR ps.claimant_id = ?)"
);
$sStmt->execute([$session_id, $_SESSION['user_id'], $_SESSION['user_id']]);
$session = $sStmt->fetch();

if (!$session) { header("Location: dashboard.php?error=unauthorized"); exit(); }

$isCompleted = $session['status'] === 'completed';
$isFinder    = $_SESSION['user_id'] == $session['finder_id'];
$isClaimant  = $_SESSION['user_id'] == $session['claimant_id'];
$otherName   = $isFinder ? $session['claimant_name'] : $session['finder_name'];
$otherEmail  = $isFinder ? $session['claimant_email'] : $session['finder_email'];

// Load exchange log
$logStmt = $pdo->prepare("SELECT * FROM exchange_logs WHERE session_id = ?");
$logStmt->execute([$session_id]);
$exchangeLog = $logStmt->fetch();

$msgError = $msgSuccess = '';

// Handle sending a message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $msg = htmlspecialchars(trim($_POST['message']));
    if ($msg && !$isCompleted) {
        $pdo->prepare("INSERT INTO peer_messages (session_id, sender_id, message) VALUES (?,?,?)")
            ->execute([$session_id, $_SESSION['user_id'], $msg]);
        header("Location: peer_chat.php?session=$session_id");
        exit();
    }
}

// Handle confirmation (claimant confirms pickup)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_pickup']) && $isClaimant && !$isCompleted) {
    $pdo->prepare("UPDATE exchange_logs SET confirmed_by_claimant=1 WHERE session_id=?")
        ->execute([$session_id]);
    $logStmt->execute([$session_id]);
    $exchangeLog = $logStmt->fetch();

    // Notify finder
    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?,?)")
        ->execute([$session['finder_id'],
            "📦 {$session['claimant_name']} has confirmed they received '{$session['item_name']}'. Please confirm handover."]);
    header("Location: peer_chat.php?session=$session_id");
    exit();
}

// Handle confirmation (finder confirms handover) + photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_handover']) && $isFinder && !$isCompleted) {
    $photoPath = $exchangeLog['exchange_photo_path'] ?? null;
    $notes     = htmlspecialchars(trim($_POST['exchange_notes'] ?? ''));

    // Handle photo upload
    if (!empty($_FILES['exchange_photo']['name'])) {
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        if (in_array($_FILES['exchange_photo']['type'], $allowed) && $_FILES['exchange_photo']['size'] < 5_000_000) {
            $ext      = pathinfo($_FILES['exchange_photo']['name'], PATHINFO_EXTENSION);
            $filename = 'uploads/exchange_' . uniqid('', true) . '.' . $ext;
            if (!is_dir('uploads')) mkdir('uploads', 0755, true);
            move_uploaded_file($_FILES['exchange_photo']['tmp_name'], $filename);
            $photoPath = $filename;
        }
    }

    $pdo->prepare(
        "UPDATE exchange_logs SET confirmed_by_finder=1, exchange_photo_path=?, exchange_notes=? WHERE session_id=?"
    )->execute([$photoPath, $notes, $session_id]);

    // Check if both confirmed → close session
    $logStmt->execute([$session_id]);
    $exchangeLog = $logStmt->fetch();

    if ($exchangeLog['confirmed_by_claimant'] && $exchangeLog['confirmed_by_finder']) {
        // Close session
        $pdo->prepare("UPDATE peer_sessions SET status='completed', completed_at=NOW() WHERE session_id=?")
            ->execute([$session_id]);

        // Mark item as returned
        $pdo->prepare("UPDATE items SET status='returned' WHERE item_id=?")
            ->execute([$session['item_id']]);

        // Notify both students
        $completionMsg = "✅ Exchange for '{$session['item_name']}' is complete! The item has been marked as returned.";
        $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?,?)")
            ->execute([$session['finder_id'], $completionMsg]);
        $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?,?)")
            ->execute([$session['claimant_id'], $completionMsg]);

        // Notify admins
        $admins = $pdo->query("SELECT id FROM users WHERE role='admin'")->fetchAll();
        $nStmt  = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?,?)");
        foreach ($admins as $admin) {
            $nStmt->execute([$admin['id'],
                "📋 Peer exchange completed for '{$session['item_name']}' between {$session['finder_name']} and {$session['claimant_name']}."]);
        }

        $isCompleted = true;
    }
    header("Location: peer_chat.php?session=$session_id");
    exit();
}

// Load messages
$messages = $pdo->prepare(
    "SELECT pm.*, u.username AS sender_name
     FROM peer_messages pm
     JOIN users u ON pm.sender_id = u.id
     WHERE pm.session_id = ?
     ORDER BY pm.created_at ASC"
);
$messages->execute([$session_id]);
$allMessages = $messages->fetchAll();

// Reload log
$logStmt->execute([$session_id]);
$exchangeLog = $logStmt->fetch();
$isCompleted = $session['status'] === 'completed'
    || ($pdo->prepare("SELECT status FROM peer_sessions WHERE session_id=?")
        ->execute([$session_id]) && false); // re-fetch status
$statusCheck = $pdo->prepare("SELECT status FROM peer_sessions WHERE session_id=?");
$statusCheck->execute([$session_id]);
$currentStatus = $statusCheck->fetchColumn();
$isCompleted = $currentStatus === 'completed';

$activePage = 'my_reports';
$rootPath   = '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Chat – <?= htmlspecialchars($session['item_name']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="includes/common.css">
  <style>
    .chat-wrap    { display:flex; flex-direction:column; height:calc(100vh - 56px); }
    .chat-header  { background:#fff; border-bottom:1px solid var(--gray-border); padding:14px 20px; flex-shrink:0; }
    .chat-header-title { font-size:15px; font-weight:700; color:var(--navy); }
    .chat-header-sub   { font-size:12px; color:var(--text-muted); }
    .chat-body    { flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:10px; background:var(--gray-bg); }
    .chat-footer  { background:#fff; border-top:1px solid var(--gray-border); padding:14px 20px; flex-shrink:0; }
    /* Messages */
    .msg          { display:flex; gap:8px; max-width:70%; }
    .msg.mine     { align-self:flex-end; flex-direction:row-reverse; }
    .msg-avatar   { width:30px; height:30px; border-radius:50%; background:var(--navy); color:var(--gold); font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .msg.mine .msg-avatar { background:var(--gold); color:var(--navy); }
    .msg-bubble   { background:#fff; border:1px solid var(--gray-border); border-radius:12px 12px 12px 2px; padding:10px 14px; font-size:13px; line-height:1.5; }
    .msg.mine .msg-bubble { background:var(--navy); color:#fff; border-color:var(--navy); border-radius:12px 12px 2px 12px; }
    .msg-time     { font-size:10px; color:var(--text-light); margin-top:4px; }
    .msg.mine .msg-time { text-align:right; }
    /* Info panel */
    .info-panel   { background:#fff; border:1px solid var(--gray-border); border-radius:var(--radius); padding:14px; margin-bottom:14px; }
    .info-label   { font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:8px; }
    .info-row     { display:flex; gap:8px; margin-bottom:6px; font-size:13px; }
    .info-key     { color:var(--text-muted); min-width:80px; }
    .info-val     { font-weight:600; color:var(--navy); }
    /* Confirmation panel */
    .confirm-panel{ background:#EFF6FF; border:1px solid #BFDBFE; border-radius:var(--radius); padding:16px; margin-top:14px; }
    .confirm-title{ font-size:13px; font-weight:700; color:#1E40AF; margin-bottom:10px; }
    .completed-banner { background:#D1FAE5; border:1px solid #6EE7B7; border-radius:var(--radius); padding:16px; text-align:center; }
    .completed-banner h4 { color:#065F46; font-weight:700; margin:0 0 6px; }
    .completed-banner p  { color:#047857; font-size:13px; margin:0; }
  </style>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar.php'; ?>
  <main class="main" style="overflow:hidden;">
    <div class="chat-wrap">

      <!-- CHAT HEADER -->
      <div class="chat-header d-flex align-items-center justify-content-between">
        <div>
          <div class="chat-header-title">
            💬 <?= htmlspecialchars($session['item_name']) ?>
          </div>
          <div class="chat-header-sub">
            Chatting with <strong><?= htmlspecialchars($otherName) ?></strong>
            · <?= htmlspecialchars($otherEmail) ?>
            <?php if ($isCompleted): ?>
              · <span style="color:#059669;font-weight:600;">✅ Exchange Complete</span>
            <?php else: ?>
              · <span style="color:#D97706;font-weight:600;">🔄 Active Session</span>
            <?php endif; ?>
          </div>
        </div>
        <a href="my_reports.php" class="btn-outline" style="font-size:12px;padding:5px 12px;">← Back</a>
      </div>

      <!-- CHAT MESSAGES -->
      <div class="chat-body" id="chatBody">

        <!-- Item info card -->
        <div class="info-panel" style="align-self:center;width:100%;max-width:500px;">
          <div class="info-label">Item Details</div>
          <div class="info-row"><span class="info-key">Item</span><span class="info-val"><?= htmlspecialchars($session['item_name']) ?></span></div>
          <div class="info-row"><span class="info-key">Category</span><span class="info-val"><?= htmlspecialchars($session['category']) ?></span></div>
          <div class="info-row"><span class="info-key">Finder</span><span class="info-val"><?= htmlspecialchars($session['finder_name']) ?> · <?= htmlspecialchars($session['finder_email']) ?></span></div>
          <div class="info-row"><span class="info-key">Claimant</span><span class="info-val"><?= htmlspecialchars($session['claimant_name']) ?> · <?= htmlspecialchars($session['claimant_email']) ?></span></div>
          <div class="info-row"><span class="info-key">Started</span><span class="info-val"><?= date('M j, Y g:i a', strtotime($session['created_at'])) ?></span></div>
        </div>

        <!-- System message -->
        <div style="text-align:center;font-size:12px;color:var(--text-muted);padding:4px 0;">
          🔒 This is a private exchange session. Coordinate your meetup here.
        </div>

        <!-- Messages -->
        <?php foreach ($allMessages as $m): ?>
          <?php $mine = $m['sender_id'] == $_SESSION['user_id']; ?>
          <div class="msg <?= $mine ? 'mine' : '' ?>">
            <div class="msg-avatar"><?= strtoupper(substr($m['sender_name'],0,1)) ?></div>
            <div>
              <div class="msg-bubble"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
              <div class="msg-time"><?= date('M j, g:i a', strtotime($m['created_at'])) ?></div>
            </div>
          </div>
        <?php endforeach; ?>

        <?php if (empty($allMessages)): ?>
        <div style="text-align:center;font-size:13px;color:var(--text-muted);padding:20px 0;">
          No messages yet. Say hello and plan your meetup! 👋
        </div>
        <?php endif; ?>

        <!-- Completion banner -->
        <?php if ($isCompleted): ?>
        <div class="completed-banner">
          <h4>✅ Exchange Complete</h4>
          <p>This item has been successfully returned. Session closed on <?= date('M j, Y g:i a', strtotime($session['completed_at'])) ?>.</p>
          <?php if ($exchangeLog['exchange_photo_path']): ?>
          <img src="<?= htmlspecialchars($exchangeLog['exchange_photo_path']) ?>"
               alt="Exchange photo" style="max-width:200px;border-radius:8px;margin-top:10px;">
          <?php endif; ?>
        </div>
        <?php endif; ?>

      </div><!-- end chat-body -->

      <!-- CHAT FOOTER -->
      <?php if (!$isCompleted): ?>
      <div class="chat-footer">

        <!-- Confirmation panels -->
        <?php if ($isClaimant && !($exchangeLog['confirmed_by_claimant'] ?? false)): ?>
        <div class="confirm-panel mb-3">
          <div class="confirm-title">📦 Have you received your item?</div>
          <p style="font-size:13px;color:#1E40AF;margin-bottom:10px;">
            Only click this once you have physically received the item from the finder.
          </p>
          <form method="POST" onsubmit="return confirm('Confirm that you have received this item?')">
            <button type="submit" name="confirm_pickup" class="btn-success" style="padding:8px 20px;">
              ✅ I have received my item
            </button>
          </form>
        </div>
        <?php elseif ($isClaimant && ($exchangeLog['confirmed_by_claimant'] ?? false)): ?>
        <div class="alert alert-success mb-3" style="font-size:13px;">
          ✅ You confirmed pickup. Waiting for the finder to confirm handover.
        </div>
        <?php endif; ?>

        <?php if ($isFinder): ?>
          <?php if ($exchangeLog['confirmed_by_claimant'] ?? false): ?>
            <?php if (!($exchangeLog['confirmed_by_finder'] ?? false)): ?>
            <div class="confirm-panel mb-3">
              <div class="confirm-title">🤝 Confirm Handover</div>
              <p style="font-size:13px;color:#1E40AF;margin-bottom:10px;">
                The claimant has confirmed they received the item. Please confirm handover and optionally upload a photo as proof.
              </p>
              <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('Confirm you have handed over the item? This will close the session.')">
                <div class="form-group mb-2">
                  <label class="form-label">Exchange Notes (optional)</label>
                  <input type="text" name="exchange_notes" class="form-control"
                         placeholder="e.g. Met at Library entrance, 2pm">
                </div>
                <div class="form-group mb-3">
                  <label class="form-label">Upload Exchange Photo (optional but recommended)</label>
                  <input type="file" name="exchange_photo" class="form-control" accept="image/*">
                  <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                    A photo serves as proof in case of any dispute.
                  </div>
                </div>
                <button type="submit" name="confirm_handover" class="btn-success" style="padding:8px 20px;">
                  ✅ Confirm Handover &amp; Close Session
                </button>
              </form>
            </div>
            <?php else: ?>
            <div class="alert alert-success mb-3" style="font-size:13px;">
              ✅ You confirmed handover. Waiting for claimant confirmation.
            </div>
            <?php endif; ?>
          <?php else: ?>
          <div class="alert mb-3" style="background:#FEF3C7;border-left:4px solid #D97706;font-size:13px;">
            ⏳ Waiting for <?= htmlspecialchars($session['claimant_name']) ?> to confirm they received the item.
          </div>
          <?php endif; ?>
        <?php endif; ?>

        <!-- Message input -->
        <form method="POST" class="d-flex gap-2">
          <input type="text" name="message" class="form-control"
                 placeholder="Type a message… e.g. 'I'll be at the library at 2pm'"
                 autocomplete="off" required>
          <button type="submit" name="send_message" class="btn-navy" style="padding:9px 18px;white-space:nowrap;">
            Send ↗
          </button>
        </form>
      </div>
      <?php else: ?>
      <div class="chat-footer" style="text-align:center;color:var(--text-muted);font-size:13px;">
        🔒 This session is closed. Exchange has been completed and logged.
      </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<script>
  // Auto scroll to bottom of chat
  const chatBody = document.getElementById('chatBody');
  chatBody.scrollTop = chatBody.scrollHeight;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>