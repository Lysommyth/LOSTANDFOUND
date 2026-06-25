<?php
require 'includes/auth_check.php';
require 'config/db.php';

// Students only
if ($_SESSION['role'] === 'admin') {
    header("Location: dashboard.php");
    exit();
}

$item_id = (int)($_GET['item_id'] ?? 0);
if (!$item_id) { header("Location: dashboard.php"); exit(); }

// Load item
$stmt = $pdo->prepare(
    "SELECT i.item_id, i.item_name, i.category, i.description,
            i.location_found, i.status, i.image_path, i.created_at,
            u.username AS finder_name
     FROM items i
     JOIN users u ON i.finder_id = u.id
     WHERE i.item_id = ?"
);
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item || $item['status'] !== 'available') {
    header("Location: dashboard.php?error=unavailable");
    exit();
}

// Can't claim your own item
$selfStmt = $pdo->prepare("SELECT finder_id FROM items WHERE item_id = ?");
$selfStmt->execute([$item_id]);
$selfCheck = $selfStmt->fetch();
if ($selfCheck['finder_id'] == $_SESSION['user_id']) {
    header("Location: dashboard.php?error=own_item");
    exit();
}

// Already claimed?
$exists = $pdo->prepare("SELECT claim_id FROM claims WHERE item_id = ? AND user_id = ?");
$exists->execute([$item_id, $_SESSION['user_id']]);
$alreadyClaimed = $exists->fetch();

// Determine flow
$adminCategories = ['Electronics', 'IDs/Wallets', 'Keys'];
$isPeerToPeer    = !in_array($item['category'], $adminCategories);

// Category emoji
$icons = [
    'Electronics'  => '💻', 'IDs/Wallets' => '🪪',
    'Bags'         => '🎒', 'Accessories' => '⌚',
    'Books'        => '📚', 'Clothing'    => '🧥',
    'Keys'         => '🔑', 'Other'       => '📦',
];
$icon = $icons[$item['category']] ?? '📦';

$stmt2 = $pdo->prepare("SELECT username, course_year, role FROM users WHERE id = ?");
$stmt2->execute([$_SESSION['user_id']]);
$user = $stmt2->fetch();

$activePage = 'browse';
$rootPath   = '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Claim Item – SU Lost &amp; Found</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="includes/common.css">
  <style>
    .claim-wrap   { max-width: 600px; }
    .item-preview {
      background: #fff; border: 1px solid var(--gray-border);
      border-radius: var(--radius-lg); overflow: hidden;
      display: flex; gap: 0; margin-bottom: 20px;
    }
    .item-preview-img {
      width: 120px; min-height: 120px; background: var(--gray-bg);
      display: flex; align-items: center; justify-content: center;
      font-size: 40px; flex-shrink: 0; overflow: hidden;
    }
    .item-preview-img img { width:100%; height:100%; object-fit:cover; }
    .item-preview-body { padding: 14px 16px; flex: 1; }
    .item-preview-title { font-size: 15px; font-weight: 700; color: var(--navy); margin-bottom: 4px; }
    .item-preview-meta  { font-size: 12px; color: var(--text-muted); margin-bottom: 6px; }

    /* Flow badge */
    .flow-badge {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 6px 14px; border-radius: 999px;
      font-size: 12px; font-weight: 700; margin-bottom: 20px;
    }
    .flow-peer  { background: #D1FAE5; color: #065F46; border: 1px solid #6EE7B7; }
    .flow-admin { background: #DBEAFE; color: #1E40AF; border: 1px solid #BFDBFE; }

    /* Flow info box */
    .flow-info {
      border-radius: var(--radius); padding: 14px 16px;
      font-size: 13px; margin-bottom: 20px; line-height: 1.6;
    }
    .flow-info.peer  { background: #F0FDF4; border: 1px solid #6EE7B7; color: #065F46; }
    .flow-info.admin { background: #EFF6FF; border: 1px solid #BFDBFE; color: #1E40AF; }
    .flow-info strong { display: block; margin-bottom: 4px; font-size: 13px; }

    /* Steps */
    .steps { display: flex; flex-direction: column; gap: 6px; margin-top: 8px; }
    .step  { display: flex; align-items: flex-start; gap: 8px; font-size: 12px; }
    .step-num {
      width: 20px; height: 20px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 10px; font-weight: 700; flex-shrink: 0; margin-top: 1px;
    }
    .peer  .step-num  { background: #059669; color: #fff; }
    .admin .step-num  { background: #2563EB; color: #fff; }
  </style>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar.php'; ?>
  <main class="main">
    <header class="topbar">
      <span class="topbar-title">Submit a Claim</span>
      <div class="topbar-actions">
        <a href="javascript:history.back()" class="btn-outline" style="font-size:12px;padding:5px 12px;">← Back</a>
        <div class="avatar-btn"><?= strtoupper(substr($user['username'],0,1)) ?></div>
      </div>
    </header>

    <div class="content">
      <div class="claim-wrap">

        <!-- ITEM PREVIEW -->
        <div class="item-preview">
          <div class="item-preview-img">
            <?php if ($item['image_path'] && file_exists($item['image_path'])): ?>
              <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>">
            <?php else: ?>
              <?= $icon ?>
            <?php endif; ?>
          </div>
          <div class="item-preview-body">
            <div class="item-preview-title"><?= htmlspecialchars($item['item_name']) ?></div>
            <div class="item-preview-meta">
              📂 <?= htmlspecialchars($item['category']) ?>
              &nbsp;·&nbsp;  <?= htmlspecialchars($item['location_found']) ?>
              &nbsp;·&nbsp; 🗓 <?= date('M j, Y', strtotime($item['created_at'])) ?>
            </div>
            <div style="font-size:12px;color:var(--text-main);"><?= htmlspecialchars($item['description']) ?></div>
          </div>
        </div>

        <?php if ($alreadyClaimed): ?>
          <!-- Already claimed -->
          <div class="alert alert-danger">
            ❌ You have already submitted a claim for this item.
            <a href="my_claims.php" style="color:inherit;font-weight:700;">View your claims →</a>
          </div>

        <?php else: ?>

          <!-- FLOW BADGE -->
          <?php if ($isPeerToPeer): ?>
            <div class="flow-badge flow-peer"> Peer-to-Peer Exchange</div>
            <div class="flow-info peer">
              <strong>How this works:</strong>
              <div class="steps">
                <div class="step"><span class="step-num">1</span> You describe why this item is yours below</div>
                <div class="step"><span class="step-num">2</span> The finder reviews your claim in their My Reports</div>
                <div class="step"><span class="step-num">3</span> If approved, a private chat opens between you and the finder</div>
                <div class="step"><span class="step-num">4</span> You coordinate a meetup on campus to collect your item</div>
                <div class="step"><span class="step-num">5</span> Both of you confirm the exchange — item marked as returned</div>
              </div>
            </div>
          <?php else: ?>
            <div class="flow-badge flow-admin"> Admin Verified Claim</div>
            <div class="flow-info admin">
              <strong>How this works:</strong>
              <div class="steps">
                <div class="step"><span class="step-num">1</span> You describe why this item is yours below</div>
                <div class="step"><span class="step-num">2</span> An admin reviews your claim and verifies your details</div>
                <div class="step"><span class="step-num">3</span> If approved, you'll be notified to collect from the admin office</div>
                <div class="step"><span class="step-num">4</span> Bring your student ID when collecting</div>
              </div>
            </div>
          <?php endif; ?>

          <!-- CLAIM FORM -->
          <div class="card">
            <div class="card-body">
              <form action="claim_submit.php" method="POST">
                <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">

                <div class="form-group">
                  <label class="form-label">
                    <?php if ($isPeerToPeer): ?>
                      Why is this item yours? *
                    <?php else: ?>
                      Proof of ownership — describe your item in detail *
                    <?php endif; ?>
                  </label>
                  <textarea name="claim_details" class="form-control" rows="5" required
                    placeholder="<?php if ($isPeerToPeer): ?>
Describe something only the real owner would know — colour, what's inside, any marks or stickers, where you last had it…
                    <?php else: ?>
For high-value items, be very specific — serial number, purchase receipt details, unique markings, what was stored on/in it, accessories included…
                    <?php endif; ?>"></textarea>
                  <div style="font-size:11px;color:var(--text-muted);margin-top:5px;">
                    <?php if ($isPeerToPeer): ?>
                      💡 The finder will read this before deciding to approve your claim.
                    <?php else: ?>
                      💡 An admin will verify this against details only the real owner would know.
                    <?php endif; ?>
                  </div>
                </div>

                <?php if (!$isPeerToPeer): ?>
                <!-- Extra field for admin flow - contact number -->
                <div class="form-group">
                  <label class="form-label">Your Phone Number (for admin to contact you) *</label>
                  <input type="text" name="phone_number" class="form-control"
                         placeholder="e.g. 0712 345 678" required>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn-gold w-100" style="justify-content:center;padding:12px;font-size:14px;">
                  <?= $isPeerToPeer ? ' Submit Claim to Finder' : ' Submit Claim to Admin' ?>
                </button>
              </form>
            </div>
          </div>

        <?php endif; ?>

      </div>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>