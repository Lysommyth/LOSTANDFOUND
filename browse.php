<?php
require 'includes/auth_check.php';
require 'config/db.php';

$stmt = $pdo->prepare("SELECT username, course_year, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$cat    = $_GET['cat']    ?? '';
$status = $_GET['status'] ?? 'available';
$search = trim($_GET['q'] ?? '');

$where  = ['1=1']; $params = [];
if ($cat)    { $where[] = 'category = ?';                  $params[] = $cat; }
if ($status) { $where[] = 'status = ?';                    $params[] = $status; }
if ($search) { $where[] = '(item_name LIKE ? OR description LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$wSql  = implode(' AND ', $where);
$items = $pdo->prepare(
    "SELECT item_id, item_name, category, description, location_found,
            status, image_path, created_at
     FROM items WHERE $wSql ORDER BY created_at DESC"
);
$items->execute($params);
$allItems = $items->fetchAll();

$adminCategories = ['Electronics', 'IDs/Wallets', 'Keys'];

$activePage = 'browse';
$rootPath   = '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Browse Items – SU Lost &amp; Found</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="includes/common.css">
  <style>
    .items-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; }
    @media(max-width:900px){ .items-grid{grid-template-columns:repeat(2,1fr);} }
    @media(max-width:600px){ .items-grid{grid-template-columns:1fr;} }
    .item-card { background:#fff; border:1px solid var(--gray-border); border-radius:var(--radius-lg); overflow:hidden; transition:transform 160ms,box-shadow 160ms,border-color 160ms; cursor:pointer; }
    .item-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(11,45,94,.1); border-color:var(--gold); }
    .card-img-area { height:120px; display:flex; align-items:center; justify-content:center; font-size:42px; background:var(--gray-bg); overflow:hidden; }
    .card-img-area img { width:100%; height:100%; object-fit:cover; }
    .card-body-area { padding:12px 14px; }
    .card-item-title { font-size:13px; font-weight:700; color:var(--navy); margin-bottom:5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .tag { font-size:10px; font-weight:500; padding:2px 7px; border-radius:999px; }
    .tag-loc  { background:#F1F5F9; color:#475569; }
    .tag-date { background:#F8FAFC; color:#94A3B8; border:1px solid #E2E8F0; }
    .card-footer-area { display:flex; align-items:center; justify-content:space-between; margin-top:10px; gap:6px; }
    .flow-tag { font-size:10px; font-weight:600; padding:2px 7px; border-radius:999px; }
    .flow-peer  { background:#D1FAE5; color:#065F46; }
    .flow-admin { background:#DBEAFE; color:#1E40AF; }
  </style>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar.php'; ?>
  <main class="main">
    <header class="topbar">
      <span class="topbar-title">Browse Items</span>
      <div class="topbar-actions">
        <a href="report_item.php" class="btn-gold">＋ Report Item</a>
        <div class="avatar-btn"><?= strtoupper(substr($user['username'],0,1)) ?></div>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div class="page-header-left">
          <div class="page-title">Browse Found Items</div>
          <div class="page-sub">
            🤝 <strong>Peer</strong> items are handled directly with the finder.
            🔒 <strong>Admin</strong> items (Electronics, IDs, Keys) require staff verification.
          </div>
        </div>
      </div>

      <!-- FILTERS -->
      <form method="GET" class="card mb-4">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
          <div>
            <label class="form-label">Search</label>
            <input type="text" name="q" class="form-control" style="min-width:200px;"
                   placeholder="Item name or description…" value="<?= htmlspecialchars($search) ?>">
          </div>
          <div>
            <label class="form-label">Category</label>
            <select name="cat" class="form-control">
              <option value="">All Categories</option>
              <?php foreach(['Electronics','Books','Clothing','Keys','IDs/Wallets','Bags','Accessories','Other'] as $c): ?>
              <option value="<?= $c ?>" <?= $cat===$c?'selected':'' ?>><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <option value="">All</option>
              <option value="available" <?= $status==='available'?'selected':'' ?>>Available</option>
              <option value="claimed"   <?= $status==='claimed'?'selected':'' ?>>Claimed</option>
              <option value="returned"  <?= $status==='returned'?'selected':'' ?>>Returned</option>
            </select>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn-navy" style="padding:9px 18px;">🔍 Search</button>
            <a href="browse.php" class="btn-outline" style="padding:9px 14px;">Clear</a>
          </div>
        </div>
      </form>

      <!-- RESULTS -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="section-title">Results</span>
        <span style="font-size:12px;color:var(--text-muted);"><?= count($allItems) ?> item<?= count($allItems)!==1?'s':'' ?></span>
      </div>

      <?php if ($allItems): ?>
      <div class="items-grid">
        <?php foreach ($allItems as $it): ?>
        <?php
          $isPeer = !in_array($it['category'], $adminCategories);
          $icons  = ['Electronics'=>'💻','IDs/Wallets'=>'🪪','Bags'=>'🎒','Accessories'=>'⌚','Books'=>'📚','Clothing'=>'🧥','Keys'=>'🔑'];
          $icon   = $icons[$it['category']] ?? '📦';
        ?>
        <div class="item-card" onclick="openModal(<?= $it['item_id'] ?>)">
          <div class="card-img-area">
            <?php if ($it['image_path'] && file_exists($it['image_path'])): ?>
              <img src="<?= htmlspecialchars($it['image_path']) ?>" alt="<?= htmlspecialchars($it['item_name']) ?>">
            <?php else: ?>
              <?= $icon ?>
            <?php endif; ?>
          </div>
          <div class="card-body-area">
            <div class="card-item-title"><?= htmlspecialchars($it['item_name']) ?></div>
            <div class="d-flex flex-wrap gap-1 mb-1">
              <span class="tag tag-loc">📍 <?= htmlspecialchars($it['location_found']) ?></span>
              <span class="tag tag-date"><?= date('M j, Y', strtotime($it['created_at'])) ?></span>
            </div>
            <div class="d-flex gap-1 mb-2">
              <span class="badge badge-<?= $it['status'] ?>"><?= ucfirst($it['status']) ?></span>
              <?php if ($it['status'] === 'available'): ?>
              <span class="flow-tag <?= $isPeer ? 'flow-peer' : 'flow-admin' ?>">
                <?= $isPeer ? '🤝 Peer' : '🔒 Admin' ?>
              </span>
              <?php endif; ?>
            </div>
            <div class="card-footer-area">
              <a href="item_detail.php?id=<?= $it['item_id'] ?>"
                class="btn-outline" style="font-size:11px;padding:4px 10px;"
                onclick="event.stopPropagation();">
              View Details
            </a>
              <?php if ($it['status']==='available' && $user['role']!=='admin'): ?>
              <a href="claim_modal.php?item_id=<?= $it['item_id'] ?>"
                 class="btn-gold" style="font-size:11px;padding:4px 10px;"
                 onclick="event.stopPropagation();">
                <?= $isPeer ? '🤝 Claim' : '🔒 Claim' ?>
              </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <div class="icon">🔍</div>
        <h3>No items found</h3>
        <p>Try adjusting your search or filters.</p>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- DETAIL MODAL -->
<div class="modal-overlay" id="modalOverlay" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div style="height:160px;display:flex;align-items:center;justify-content:center;font-size:64px;background:var(--gray-bg);overflow:hidden;" id="modalImg"></div>
    <div class="modal-body">
      <div class="modal-header">
        <div>
          <div class="modal-title" id="modalTitle"></div>
          <div style="margin-top:4px;display:flex;gap:6px;" id="modalBadge"></div>
        </div>
        <button class="close-btn" onclick="closeModal()">✕</button>
      </div>
      <div class="modal-row"><span class="modal-key">Category</span><span class="modal-val" id="mCat"></span></div>
      <div class="modal-row"><span class="modal-key">Location</span><span class="modal-val" id="mLoc"></span></div>
      <div class="modal-row"><span class="modal-key">Date Found</span><span class="modal-val" id="mDate"></span></div>
      <div class="modal-row"><span class="modal-key">Description</span><span class="modal-val" id="mDesc"></span></div>
      <div class="modal-divider"></div>
      <div class="modal-actions">
        <a href="#" class="btn-gold" id="mClaimBtn" style="flex:1;justify-content:center;text-align:center;">Claim This Item</a>
        <button class="btn-outline" onclick="closeModal()" style="flex:1;text-align:center;">Close</button>
      </div>
    </div>
  </div>
</div>

<?php
$jsItems = [];
foreach ($allItems as $it) {
    $jsItems[] = [
        'id' => $it['item_id'], 'item_name' => $it['item_name'],
        'category' => $it['category'], 'location_found' => $it['location_found'],
        'description' => $it['description'], 'status' => $it['status'],
        'image_path' => $it['image_path'], 'created_at' => $it['created_at'],
    ];
}
?>
<script>
const ITEMS      = <?= json_encode($jsItems) ?>;
const IS_ADMIN   = <?= $user['role']==='admin'?'true':'false' ?>;
const ADMIN_CATS = ['Electronics', 'IDs/Wallets', 'Keys'];
const icons      = {Electronics:'💻','IDs/Wallets':'🪪',Bags:'🎒',Accessories:'⌚',Books:'📚',Clothing:'🧥',Keys:'🔑'};

function badgeClass(s){ return s==='available'?'badge-available':s==='claimed'?'badge-claimed':'badge-pending'; }

function openModal(id) {
  const it = ITEMS.find(i => i.id == id); if (!it) return;
  const mi = document.getElementById('modalImg');
  if (it.image_path) mi.innerHTML = `<img src="${it.image_path}" style="width:100%;height:100%;object-fit:cover;">`;
  else mi.textContent = icons[it.category] || '📦';

  const isPeer = !ADMIN_CATS.includes(it.category);
  const flowTag = it.status === 'available'
    ? (isPeer
        ? '<span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:999px;background:#D1FAE5;color:#065F46;">🤝 Peer</span>'
        : '<span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:999px;background:#DBEAFE;color:#1E40AF;">🔒 Admin</span>')
    : '';

  document.getElementById('modalTitle').textContent = it.item_name;
  document.getElementById('modalBadge').innerHTML   =
    `<span class="badge ${badgeClass(it.status)}">${it.status}</span> ${flowTag}`;
  document.getElementById('mCat').textContent  = it.category;
  document.getElementById('mLoc').textContent  = it.location_found;
  document.getElementById('mDate').textContent = new Date(it.created_at).toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'});
  document.getElementById('mDesc').textContent = it.description;

  const btn = document.getElementById('mClaimBtn');
  if (!IS_ADMIN && it.status === 'available') {
    btn.style.display = '';
    btn.href          = `claim_modal.php?item_id=${it.id}`;
    btn.textContent   = isPeer ? '🤝 Claim This Item' : '🔒 Claim This Item';
  } else {
    btn.style.display = 'none';
  }
  document.getElementById('modalOverlay').classList.add('open');
}
function closeModal(){ document.getElementById('modalOverlay').classList.remove('open'); }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
