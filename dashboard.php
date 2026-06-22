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
body {
  margin: 0;
  padding: 0;
  background-color: #f8fafc;
}

.app {
  display: flex;
  min-height: 100vh;
  width: 100%;
}

.sidebar {
  width: 260px;
  background: #0b1f3a; /* Match the navy blue sidebar color */
  color: #fff;
  flex-shrink: 0;
}

.main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0; /* Prevents layout blowout */
}

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 24px;
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
}

.content {
  padding: 24px;
  flex: 1;
}


:root {
  --navy: #0b1f3a;
  --gold: #d97706;
  --gray-border: #e2e8f0;
  --gray-bg: #f8fafc;
  --text-main: #1e293b;
  --text-muted: #64748b;
  --radius: 8px;
  --radius-lg: 12px;
}
    .filter-bar {
      background: #fff; border: 1px solid var(--gray-border);
      border-radius: var(--radius); padding: 12px 16px;
      margin-bottom: 18px; display: flex; align-items: center;
      gap: 10px; flex-wrap: wrap;
    }
    .filter-group { display: flex; align-items: center; gap: 6px; }
    .filter-label { font-size: 11px; font-weight: 700; color: var(--text-muted); white-space: nowrap; }
    .filter-select {
      background: var(--gray-bg); border: 1px solid var(--gray-border);
      border-radius: 6px; padding: 5px 10px; font-size: 12px;
      color: var(--text-main); outline: none; cursor: pointer;
      font-family: inherit;
    }
    .filter-select:focus { border-color: var(--navy); }
    .sort-btn {
      margin-left: auto; background: var(--navy); color: #fff;
      border: none; border-radius: 6px; padding: 6px 14px;
      font-size: 12px; font-weight: 600; cursor: pointer;
    }
    /* Items grid */
    .items-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; }
    @media(max-width:900px){ .items-grid { grid-template-columns: repeat(2,1fr); } }
    @media(max-width:600px){ .items-grid { grid-template-columns: 1fr; } }
    .item-card {
      background: #fff; border: 1px solid var(--gray-border);
      border-radius: var(--radius-lg); overflow: hidden; cursor: pointer;
      transition: transform 160ms, box-shadow 160ms, border-color 160ms;
    }
    .item-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(11,45,94,0.1); border-color: var(--gold); }
    .card-img-area {
      height: 120px; display: flex; align-items: center; justify-content: center;
      font-size: 42px; background: var(--gray-bg); overflow: hidden;
    }
    .card-img-area img { width: 100%; height: 100%; object-fit: cover; }
    .card-body-area { padding: 12px 14px; }
    .card-item-title { font-size: 13px; font-weight: 700; color: var(--navy); margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tag { font-size: 10px; font-weight: 500; padding: 2px 7px; border-radius: 999px; display: inline-flex; align-items: center; gap: 3px; }
    .tag-loc  { background: #F1F5F9; color: #475569; }
    .tag-date { background: #F8FAFC; color: #94A3B8; border: 1px solid #E2E8F0; }
    .card-footer-area { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; }
    /* Search bar */
    .search-wrap { position: relative; }
    .search-wrap input { padding-left: 32px; }
    .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-light); pointer-events: none; }
    /* Notification panel */
    .notif-panel {
      display: none; position: absolute; top: 56px; right: 10px;
      width: 300px; background: #fff; border: 1px solid var(--gray-border);
      border-radius: var(--radius-lg); z-index: 300;
      box-shadow: 0 10px 30px rgba(11,45,94,0.14);
    }
    .notif-panel.open { display: block; }
    .notif-head { padding: 12px 14px; border-bottom: 1px solid var(--gray-border); font-size: 13px; font-weight: 700; display: flex; justify-content: space-between; }
    .notif-clear { font-size: 11px; color: var(--gold); cursor: pointer; font-weight: 400; }
    .notif-item { padding: 10px 14px; border-bottom: 1px solid var(--gray-border); display: flex; gap: 10px; }
    .notif-dot { width: 8px; height: 8px; background: var(--gold); border-radius: 50%; margin-top: 4px; flex-shrink: 0; }
    .notif-text { font-size: 12px; color: var(--text-main); line-height: 1.4; }
    .notif-time { font-size: 10px; color: var(--text-light); margin-top: 2px; }
    .notif-btn { position: relative; width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--gray-border); background: transparent; cursor: pointer; font-size: 16px; color: var(--text-muted); display: flex; align-items: center; justify-content: center; }
    .notif-btn .dot { position: absolute; top: 6px; right: 6px; width: 7px; height: 7px; background: #EF4444; border-radius: 50%; border: 1.5px solid #fff; }
    /* Section meta */
    .section-meta { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
    .section-title { font-size: 14px; font-weight: 700; color: var(--navy); }
    .section-count { font-size: 12px; color: var(--text-muted); }
    /* Modal image */
    .modal-img-area { height: 160px; display: flex; align-items: center; justify-content: center; font-size: 64px; background: var(--gray-bg); overflow: hidden; }
    .modal-img-area img { width: 100%; height: 100%; object-fit: cover; }
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