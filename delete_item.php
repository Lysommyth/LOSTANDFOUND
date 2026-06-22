<?php
require 'includes/auth_check.php';
require 'config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: my_reports.php"); exit(); }

// Only the owner can delete, and only if still available
$stmt = $pdo->prepare("SELECT finder_id, status, image_path FROM items WHERE item_id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item || ($item['finder_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') || $item['status'] !== 'available') {
    header("Location: my_reports.php?error=denied");
    exit();
}

// Delete image file if exists
if ($item['image_path'] && file_exists($item['image_path'])) {
    unlink($item['image_path']);
}

$del = $pdo->prepare("DELETE FROM items WHERE item_id = ?");
$del->execute([$id]);

header("Location: my_reports.php?deleted=1");
exit();
