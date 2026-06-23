<?php
require 'includes/auth_check.php';
require 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}
// Students only
if ($_SESSION['role'] === 'admin') {
    header("Location: dashboard.php?error=admin_no_claim");
    exit();
}

$item_id      = (int)$_POST['item_id'];
$claim_details = htmlspecialchars(trim($_POST['claim_details']));

if (!$item_id || !$claim_details) {
    header("Location: dashboard.php?error=incomplete");
    exit();
}
// Check item if it is still available
$stmt = $pdo->prepare("SELECT status, item_name FROM items WHERE item_id = ?");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item || $item['status'] !== 'available') {
    header("Location: dashboard.php?error=unavailable");
    exit();
}

// Check unclaimed by this user
$exists = $pdo->prepare("SELECT claim_id FROM claims WHERE item_id = ? AND user_id = ?");
$exists->execute([$item_id, $_SESSION['user_id']]);
if ($exists->fetch()) {
    header("Location: dashboard.php?error=already_claimed");
    exit();
}

try {
    $ins = $pdo->prepare("INSERT INTO claims (item_id, user_id, claim_details) VALUES (?, ?, ?)");
    $ins->execute([$item_id, $_SESSION['user_id'], $claim_details]);

   // Notify admin(s)
$admins = $pdo->query("SELECT id FROM users WHERE role='admin'")->fetchAll();
$notifMsg = "New claim submitted for: " . htmlspecialchars($item['item_name']);
$notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
foreach ($admins as $admin) {
    $notifStmt->execute([$admin['id'], $notifMsg]);
}

// Notify the finder directly
$finderStmt = $pdo->prepare("SELECT finder_id FROM items WHERE item_id = ?");
$finderStmt->execute([$item_id]);
$finder = $finderStmt->fetch();
if ($finder) {
    $finderMsg = "📋 Someone has submitted a claim for your item: '" . htmlspecialchars($item['item_name']) . "'. Go to My Reports to review it.";
    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")
        ->execute([$finder['finder_id'], $finderMsg]);
}

    header("Location: my_claims.php?success=1");
} catch (PDOException $e) {
    error_log($e->getMessage());
    header("Location: dashboard.php?error=claim_failed");
}
exit();