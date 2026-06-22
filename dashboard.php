<?php
require __DIR__ . '/includes/auth_check.php';
require __DIR__ . '/config/db.php';

$stmt = $pdo->prepare("SELECT username, course_year, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) { session_destroy(); header("Location: index.php"); exit(); }

// Stats
$totalItemsAll = (int)$pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
$unclaimed     = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE status='available'")->fetchColumn();
$claimed       = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE status='claimed'")->fetchColumn();
$pending       = (int)$pdo->query("SELECT COUNT(*) FROM claims WHERE claim_status='pending'")->fetchColumn();

// Items grid
if ($user['role'] === 'admin') {
    $dbItems = $pdo->query("SELECT item_id AS id, item_name, category, description, location_found, status, image_path, created_at FROM items ORDER BY created_at DESC")->fetchAll();
} else {
    $dbItems = $pdo->query("SELECT item_id AS id, item_name, category, description, location_found, status, image_path, created_at FROM items WHERE status='available' ORDER BY created_at DESC")->fetchAll();
}

// Unread notifications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$stmt->execute([$_SESSION['user_id']]);
$unreadCount = (int)$stmt->fetchColumn();

// Fetch notifications for panel
$notifs = $pdo->prepare("SELECT message, created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$notifs->execute([$_SESSION['user_id']]);
$userNotifs = $notifs->fetchAll();

$activePage = 'dashboard';
$rootPath   = '';

$nameParts = explode(' ', $user['username']);
$initials  = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard – SU Lost &amp; Found</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="includes/common.css">
  <style>
    /* Add this right at the beginning of your <style> tag */

  </style>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar.php';?>

  <main class="main">
    <!-- TOPBAR -->
    <header class="topbar" style="position:relative;">
      <div class="search-wrap" style="flex:1;max-width:360px;">
        <span class="search-icon">🔍</span>
        <input type="text" class="form-control" style="font-size:13px;" placeholder="Search items…" id="searchInput" oninput="filterItems()">
      </div>

      <div class="topbar-actions">
        <!-- Notifications -->
        <button class="notif-btn" onclick="toggleNotif()" title="Notifications">
          🔔
          <?php if ($unreadCount > 0): ?><span class="dot"></span><?php endif; ?>
        </button>

        <div class="avatar-btn"><?= $initials ?></div>
        <a href="report_item.php" class="btn-gold">＋ Report Item</a>
      </div>

      <!-- Notification dropdown -->
      <div class="notif-panel" id="notifPanel">
        <div class="notif-head">
          Notifications
          <span class="notif-clear" onclick="markRead()">Mark all read</span>
        </div>
        <?php if ($userNotifs): ?>
          <?php foreach ($userNotifs as $n): ?>
          <div class="notif-item">
            <div class="notif-dot"></div>
            <div>
              <div class="notif-text"><?= htmlspecialchars($n['message']) ?></div>
              <div class="notif-time"><?= date('M j, g:i a', strtotime($n['created_at'])) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="notif-item"><div class="notif-text" style="color:var(--text-muted);">No notifications yet.</div></div>
        <?php endif; ?>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div class="page-header-left">
          <div class="page-title">Dashboard</div>
          <div class="page-sub">
            <?= $unclaimed ?> item<?= $unclaimed !== 1 ? 's' : '' ?> currently available on campus.
          </div>
        </div>
      </div>

      <!-- STATS -->
      <div class="stats-row">
        <div class="stat-card accent">
          <div class="stat-label">Total Items</div>
          <div class="stat-value"><?= $totalItemsAll ?></div>
          <div class="stat-meta">This semester</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Unclaimed</div>
          <div class="stat-value" style="color:#D97706;"><?= $unclaimed ?></div>
          <div class="stat-meta">Awaiting owners</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Claimed</div>
          <div class="stat-value" style="color:#059669;"><?= $claimed ?></div>
          <div class="stat-meta">Successfully returned</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Pending Review</div>
          <div class="stat-value" style="color:#2563EB;"><?= $pending ?></div>
          <div class="stat-meta">Awaiting admin</div>
        </div>
      </div>

      <!-- FILTERS -->
      <div class="filter-bar">
        <div class="filter-group">
          <span class="filter-label">Category</span>
          <select class="filter-select" id="catFilter" onchange="filterItems()">
            <option value="">All</option>
            <option>Electronics</option><option>ID/Cards</option><option>Accessories</option>
            <option>Clothing</option><option>Bags</option><option>Books</option><option>Keys</option>
          </select>
        </div>
        <div class="filter-group">
          <span class="filter-label">Status</span>
          <select class="filter-select" id="statusFilter" onchange="filterItems()">
            <option value="">All</option>
            <option value="available">Available</option>
            <option value="claimed">Claimed</option>
          </select>
        </div>
        <button class="sort-btn" onclick="toggleSort()">↕ <span id="sortLabel">Newest</span></button>
      </div>

      <!-- GRID -->
      <div class="section-meta">
        <div class="section-title">Found Items</div>
        <div class="section-count" id="itemCount"></div>
      </div>
      <div class="items-grid" id="itemsGrid"></div>
    </div>
  </main>
</div>

<!-- ITEM DETAIL MODAL -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
  <div class="modal">
    <div class="modal-img-area" id="modalImg">📦</div>
    <div class="modal-body">
      <div class="modal-header">
        <div>
          <div class="modal-title" id="modalTitle"></div>
          <div style="margin-top:4px;" id="modalBadge"></div>
        </div>
        <button class="close-btn" onclick="closeModalDirect()">✕</button>
      </div>
      <div class="modal-row"><span class="modal-key">Category</span><span class="modal-val" id="modalCat"></span></div>
      <div class="modal-row"><span class="modal-key">Location</span><span class="modal-val" id="modalLoc"></span></div>
      <div class="modal-row"><span class="modal-key">Date Found</span><span class="modal-val" id="modalDate"></span></div>
      <div class="modal-row"><span class="modal-key">Item ID</span><span class="modal-val" id="modalId"></span></div>
      <div class="modal-row"><span class="modal-key">Description</span><span class="modal-val" id="modalDesc"></span></div>
      <div class="modal-divider"></div>
      <div class="modal-actions">
        <button class="btn-gold" id="modalClaimBtn">Submit Claim ↗</button>
        <a href="#" class="btn-outline" onclick="closeModalDirect()">Close</a>
      </div>
    </div>
  </div>
</div>

<!-- CLAIM FORM MODAL -->
<div class="modal-overlay" id="claimOverlay" onclick="if(event.target===this)closeClaimModal()">
  <div class="modal">
    <div class="modal-body">
      <div class="modal-header">
        <div class="modal-title">Submit a Claim</div>
        <button class="close-btn" onclick="closeClaimModal()">✕</button>
      </div>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;" id="claimItemName"></p>
      <form action="claim_submit.php" method="POST">
        <input type="hidden" name="item_id" id="claimItemId">
        <div class="form-group">
          <label class="form-label">Why is this yours? *</label>
          <textarea name="claim_details" class="form-control" rows="4"
            placeholder="Describe something only the owner would know — colour, markings, what's inside, etc." required></textarea>
        </div>
        <div class="modal-actions" style="margin-top:4px;">
          <button type="submit" class="btn-gold" style="flex:1;justify-content:center;">Submit Claim</button>
          <button type="button" class="btn-outline" onclick="closeClaimModal()" style="flex:1;text-align:center;">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
//  Change those lines to this:
const ITEMS    = <?= json_encode(array_values($dbItems ?? [])) ?>;
const IS_ADMIN = <?= (isset($user['role']) && $user['role'] === 'admin') ? 'true' : 'false' ?>;
let sortAsc = false;
let current = [...ITEMS];

function emoji(cat) {
  const m = {
    Electronics:  'images/electronics.jpeg',
    'ID/Cards':   'images/ids.jpeg',
    'IDs/Wallets':'images/ids.jpeg',
    Bags:         'images/bags.jpeg',
    Accessories:  'images/accessories.jpeg',
    Books:        'images/books.jpeg',
    Clothing:     'images/clothing.jpeg',
    Keys:         'images/keys.jpeg'
  };
  return m[cat] || 'images/other.jpeg';
}
function badgeClass(s) {
  if (s === 'available') return 'badge-available';
  if (s === 'claimed')   return 'badge-claimed';
  return 'badge-pending';
}
function fmtDate(d) {
  return new Date(d).toLocaleDateString(undefined, { month:'short', day:'numeric', year:'numeric' });
}
function imgHtml(it) {
  const src = it.image_path ? it.image_path : emoji(it.category);
  return `<img src="${src}" alt="${it.item_name}">`;
}

function renderCard(it) {
  const adminCats = ['Electronics', 'IDs/Wallets', 'Keys'];
  const isPeer    = !adminCats.includes(it.category);
  const flowLabel = isPeer
    ? '<span style="font-size:10px;font-weight:600;padding:2px 7px;border-radius:999px;background:#D1FAE5;color:#065F46;">🤝 Peer</span>'
    : '<span style="font-size:10px;font-weight:600;padding:2px 7px;border-radius:999px;background:#DBEAFE;color:#1E40AF;">🔒 Admin</span>';
 
  const claimBtn = (!IS_ADMIN && it.status === 'available')
    ? `<a href="claim_modal.php?item_id=${it.id}" class="claim-btn">Claim</a>`
    : '';
 
  return `
  <div class="item-card" onclick="window.location.href='item_detail.php?id=${it.id}'">
    <div class="card-img-area">${imgHtml(it)}</div>
    <div class="card-body-area">
      <div class="card-item-title">${it.item_name}</div>
      <div class="d-flex flex-wrap gap-1 mb-1">
        <span class="tag tag-loc"> ${it.location_found}</span>
        <span class="tag tag-date">${fmtDate(it.created_at)}</span>
      </div>
      <div class="d-flex gap-1 mb-1">
        <span class="badge ${badgeClass(it.status)}">${it.status.charAt(0).toUpperCase()+it.status.slice(1)}</span>
        ${it.status === 'available' ? flowLabel : ''}
      </div>
      <div class="card-footer-area">
        <a href="item_detail.php?id=${it.id}" class="view-btn">View Details</a>
        ${claimBtn}
      </div>
    </div>
  </div>`;
}
function filterItems() {
  const q   = document.getElementById('searchInput').value.toLowerCase();
  const cat = document.getElementById('catFilter').value;
  const st  = document.getElementById('statusFilter').value;
  current = ITEMS.filter(it =>
    (!q  || it.item_name.toLowerCase().includes(q) || it.location_found.toLowerCase().includes(q) || it.description.toLowerCase().includes(q)) &&
    (!cat || it.category === cat) &&
    (!st  || it.status === st)
  );
  renderGrid();
}

function renderGrid() {
  const g = document.getElementById('itemsGrid');
  g.innerHTML = current.length
    ? current.map(renderCard).join('')
    : '<div style="grid-column:1/-1;text-align:center;padding:48px;color:var(--text-muted);">No items match your search.</div>';
  document.getElementById('itemCount').textContent = `Showing ${current.length} item${current.length !== 1 ? 's' : ''}`;
}

function toggleSort() {
  sortAsc = !sortAsc;
  current.reverse();
  document.getElementById('sortLabel').textContent = sortAsc ? 'Oldest' : 'Newest';
  renderGrid();
}

function openModal(id) {
  const it = ITEMS.find(i => i.id == id);
  if (!it) return;
 
  const mi = document.getElementById('modalImg');
  const src = it.image_path ? it.image_path : emoji(it.category);
  mi.innerHTML = `<img src="${src}" alt="${it.item_name}" style="width:100%;height:100%;object-fit:cover;">`;
 
  document.getElementById('modalTitle').textContent = it.item_name;
  document.getElementById('modalBadge').innerHTML   = `<span class="badge ${badgeClass(it.status)}">${it.status}</span>`;
  document.getElementById('modalCat').textContent   = it.category;
  document.getElementById('modalLoc').textContent   = it.location_found;
  document.getElementById('modalDate').textContent  = fmtDate(it.created_at);
  document.getElementById('modalId').textContent    = '#LF-' + String(it.id).padStart(4, '0');
  document.getElementById('modalDesc').textContent  = it.description;
 
  const claimBtn = document.getElementById('modalClaimBtn');
  if (!IS_ADMIN && it.status === 'available') {
    claimBtn.style.display = '';
    // Navigate to dedicated claim page instead of inline form
    claimBtn.onclick = () => { window.location.href = `claim_modal.php?item_id=${it.id}`; };
  } else {
    claimBtn.style.display = 'none';
  }
 
  document.getElementById('modalOverlay').classList.add('open');
}

function closeModal(e) { if (e.target === document.getElementById('modalOverlay')) closeModalDirect(); }
function closeModalDirect() { document.getElementById('modalOverlay').classList.remove('open'); }

function openClaimModal(id, name) {
  document.getElementById('claimItemId').value      = id;
  document.getElementById('claimItemName').textContent = `Claiming: ${name}`;
  document.getElementById('claimOverlay').classList.add('open');
}
function closeClaimModal() { document.getElementById('claimOverlay').classList.remove('open'); }

let notifOpen = false;
function toggleNotif() {
  notifOpen = !notifOpen;
  document.getElementById('notifPanel').classList.toggle('open', notifOpen);
}
function markRead() {
  fetch('mark_notifications_read.php', { method: 'POST' });
  document.querySelectorAll('.notif-dot').forEach(d => d.style.background = 'transparent');
  document.querySelector('.notif-btn .dot') && (document.querySelector('.notif-btn .dot').style.display = 'none');
  notifOpen = false;
  document.getElementById('notifPanel').classList.remove('open');
}

// Close notif panel on outside click
document.addEventListener('click', e => {
  const panel = document.getElementById('notifPanel');
  if (notifOpen && !panel.contains(e.target) && !e.target.closest('.notif-btn')) {
    notifOpen = false; panel.classList.remove('open');
  }
});

renderGrid();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>