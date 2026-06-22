<?php
require '../includes/auth_check.php';
require '../includes/admin_check.php';
require '../config/db.php';

$stmt = $pdo->prepare("SELECT username, course_year, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_role'])) {
    $uid     = (int)$_POST['user_id'];
    $newRole = $_POST['new_role'];
    if ($uid !== $_SESSION['user_id'] && in_array($newRole, ['student','admin'])) {
        $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$newRole, $uid]);
        $success = "User role updated.";
    } else {
        $error = "Cannot change your own role.";
    }
}

if (isset($_GET['toggle_verify'])) {
    $uid = (int)$_GET['toggle_verify'];
    $cur = $pdo->prepare("SELECT is_verified FROM users WHERE id=?");
    $cur->execute([$uid]);
    $pdo->prepare("UPDATE users SET is_verified=? WHERE id=?")->execute([1-(int)$cur->fetchColumn(), $uid]);
    $success = "Verification status toggled.";
}

if (isset($_GET['delete_user'])) {
    $uid = (int)$_GET['delete_user'];
    if ($uid !== $_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
        $success = "User deleted.";
    } else {
        $error = "Cannot delete your own account.";
    }
}

$users = $pdo->query(
    "SELECT id, username, email, course_year, role, is_verified, created_at FROM users ORDER BY created_at DESC"
)->fetchAll();

$activePage = 'manage_users';
$rootPath   = '../';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Manage Users – SU Lost &amp; Found</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../includes/common.css">
</head>
<body>
<div class="app">
  <?php include '../includes/sidebar.php';?>
  <main class="main">
    <header class="topbar">
      <span class="topbar-title">Manage Users</span>
      <div class="topbar-actions">
        <span class="badge badge-admin" style="font-size:12px;padding:5px 12px;">Admin</span>
        <div class="avatar-btn"><?= strtoupper(substr($user['username'],0,1)) ?></div>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div class="page-header-left">
          <div class="page-title">Manage Users</div>
          <div class="page-sub">View all registered students, adjust roles, and verify accounts.</div>
        </div>
      </div>

      <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>
      <?php if ($error):   ?><div class="alert alert-danger">❌ <?= $error ?></div><?php endif; ?>

      <div class="card">
        <div class="table-wrap">
          <table class="su-table">
            <thead>
              <tr><th>#</th><th>Name</th><th>Email</th><th>Course</th><th>Role</th><th>Verified</th><th>Joined</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
              <tr>
                <td style="color:var(--text-muted);"><?= $u['id'] ?></td>
                <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                <td style="font-size:12px;"><?= htmlspecialchars($u['email']) ?></td>
                <td style="font-size:12px;"><?= htmlspecialchars($u['course_year'] ?? '—') ?></td>
                <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                <td>
                  <a href="?toggle_verify=<?= $u['id'] ?>"
                     style="font-size:12px;font-weight:600;color:<?= $u['is_verified']?'#059669':'#D97706' ?>;text-decoration:none;">
                    <?= $u['is_verified'] ? '✅ Verified' : '⚠️ Unverified' ?>
                  </a>
                </td>
                <td style="font-size:12px;"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                <td>
                  <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                  <form method="POST" class="d-inline-flex gap-1 align-items-center">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <select name="new_role" class="form-control" style="font-size:11px;padding:3px 6px;width:auto;">
                      <option value="student" <?= $u['role']==='student'?'selected':'' ?>>Student</option>
                      <option value="admin"   <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                    </select>
                    <button type="submit" name="toggle_role" class="btn-navy" style="font-size:11px;padding:4px 8px;">Set</button>
                  </form>
                  <a href="?delete_user=<?= $u['id'] ?>"
                     onclick="return confirm('Delete this user and all their data?')"
                     class="btn-danger ms-1" style="font-size:11px;padding:4px 8px;">🗑</a>
                  <?php else: ?>
                  <span style="font-size:11px;color:var(--text-light);">You</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>