<?php
// includes/sidebar.php
if (!isset($user) || !is_array($user)) return;
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

$initials  = strtoupper(substr($user['username'] ?? 'U', 0, 1));
$nameParts = explode(' ', $user['username'] ?? '');
if (isset($nameParts[1])) $initials .= strtoupper(substr($nameParts[1], 0, 1));

$role = $user['role'] ?? 'student';

$pendingCount = 0;
if ($role === 'admin') {
    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM claims WHERE claim_status='pending'")->fetchColumn();
}

// Active peer sessions for current user (student badge)
$activeSessions = 0;
if ($role === 'student') {
    $sCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM peer_sessions
         WHERE (finder_id=? OR claimant_id=?) AND status='active'"
    );
    $sCheck->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $activeSessions = (int)$sCheck->fetchColumn();
}

$availableCount = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE status='available'")->fetchColumn();

$activePage = $activePage ?? 'dashboard';
$rootPath   = $rootPath   ?? '';

function navItem($href, $icon, $label, $active, $badge = null) {
    $cls       = $active ? 'nav-item active' : 'nav-item';
    $badgeHtml = $badge  ? "<span class='nav-badge'>{$badge}</span>" : '';
    return "
    <a href='{$href}' style='text-decoration:none;'>
      <div class='{$cls}'>
        <span class='nav-icon'>{$icon}</span>
        {$label}
        {$badgeHtml}
      </div>
    </a>";
}
?>

<aside class="sidebar">

  <div class="sidebar-logo">
    <div class="logo-badge">
      <div class="logo-icon">
        <img src="<?= $rootPath ?>assets/strath.png" alt="Strathmore University">
      </div>
      <div class="logo-text">
        Lost &amp; Found
        <span>Strathmore University</span>
      </div>
    </div>
  </div>

  <nav class="nav-section">
    <div class="nav-label">Main</div>
    <?= navItem($rootPath.'dashboard.php',   '◈', 'Dashboard',    $activePage==='dashboard') ?>
    <?= navItem($rootPath.'report_item.php', '＋', 'Report Item',  $activePage==='report') ?>
    <?= navItem($rootPath.'browse.php',      '◉', 'Browse Items', $activePage==='browse', $availableCount) ?>
    <?= navItem($rootPath.'my_reports.php',  '♦', 'My Reports',   $activePage==='my_reports') ?>
    <?= navItem($rootPath.'my_claims.php',   '📋','My Claims',    $activePage==='my_claims') ?>

    <?php if ($role === 'student' && $activeSessions > 0): ?>
    <?= navItem($rootPath.'my_claims.php', '💬', 'Active Chats', $activePage==='my_claims', $activeSessions) ?>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
      <div class="nav-label">Admin</div>
      <?= navItem($rootPath.'admin/panel.php',          '⊞', 'Admin Panel',    $activePage==='admin_panel') ?>
      <?= navItem($rootPath.'admin/claim_reviews.php',  '↺', 'Claim Reviews',  $activePage==='claim_reviews', $pendingCount) ?>
      <?= navItem($rootPath.'admin/manage_items.php',   '≡', 'Manage Items',   $activePage==='manage_items') ?>
      <?= navItem($rootPath.'admin/manage_users.php',   '👥','Users',          $activePage==='manage_users') ?>
      <?= navItem($rootPath.'admin/exchange_logs.php',  '🔄','Exchange Logs',  $activePage==='exchange_logs') ?>
    <?php endif; ?>

    <div class="nav-label">Account</div>
    <?= navItem($rootPath.'logout.php', '⏻', 'Logout', false) ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-mini">
      <div class="avatar-sm"><?= htmlspecialchars($initials) ?></div>
      <div class="user-info-sm">
        <?= htmlspecialchars($user['username'] ?? 'User') ?>
        <span><?= ucfirst($role) ?> · <?= htmlspecialchars($user['course_year'] ?? 'N/A') ?></span>
      </div>
    </div>
  </div>

</aside>