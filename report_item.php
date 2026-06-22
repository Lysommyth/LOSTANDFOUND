<?php
require 'includes/auth_check.php';
require 'config/db.php';

$stmt = $pdo->prepare("SELECT username, course_year, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name      = htmlspecialchars(trim($_POST['item_name']));
    $category       = $_POST['category'];
    $description    = htmlspecialchars(trim($_POST['description']));
    $location_found = htmlspecialchars(trim($_POST['location_found']));

    $image_path = null;
    if (!empty($_FILES['image']['name'])) {
        $allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
        if (in_array($_FILES['image']['type'], $allowedTypes) && $_FILES['image']['size'] < 5_000_000) {
            $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'uploads/' . uniqid('item_', true) . '.' . $ext;
            if (!is_dir('uploads')) mkdir('uploads', 0755, true);
            move_uploaded_file($_FILES['image']['tmp_name'], $filename);
            $image_path = $filename;
        } else {
            $error = 'Image must be JPG/PNG/GIF/WEBP and under 5 MB.';
        }
    }

    if (!$error) {
        try {
            $ins = $pdo->prepare(
                "INSERT INTO items (finder_id, item_name, category, description, location_found, image_path)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([$_SESSION['user_id'], $item_name, $category, $description, $location_found, $image_path]);

            // Notify admins of new report
            $admins   = $pdo->query("SELECT id FROM users WHERE role='admin'")->fetchAll();
            $notifMsg = "📦 New item reported: " . $item_name . " (found at $location_found)";
            $nStmt    = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?,?)");
            foreach ($admins as $admin) $nStmt->execute([$admin['id'], $notifMsg]);

            $success = 'Item reported successfully! It is now visible to other students.';
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Failed to save item. Please try again.';
        }
    }
}

$activePage = 'report';
$rootPath   = '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Report Item – SU Lost &amp; Found</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="includes/common.css">
  <style>
    .report-wrap { max-width: 620px; }
    .upload-zone {
      border: 2px dashed var(--gray-border); border-radius: 10px;
      padding: 28px; text-align: center; cursor: pointer;
      transition: border-color var(--trans), background var(--trans);
    }
    .upload-zone:hover, .upload-zone.drag { border-color: var(--gold); background: #fffbeb; }
    .upload-zone .icon { font-size: 34px; margin-bottom: 8px; }
    .upload-zone p { font-size: 13px; color: var(--text-muted); margin: 0; }
    #imgPreview { max-width:100%; border-radius:8px; margin-top:10px; display:none; }
  </style>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar.php'; ?>
  <main class="main">
    <header class="topbar">
      <span class="topbar-title">Report a Found Item</span>
      <div class="topbar-actions">
        <div class="avatar-btn"><?= strtoupper(substr($user['username'],0,1)) ?></div>
      </div>
    </header>

    <div class="content">
      <div class="report-wrap">
        <div class="page-header">
          <div class="page-header-left">
            <div class="page-title">Report a Found Item</div>
            <div class="page-sub">Fill in the details so the owner can identify and claim it.</div>
          </div>
        </div>

        <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-danger">❌ <?= $error ?></div><?php endif; ?>

        <div class="card">
          <div class="card-body">
            <form action="report_item.php" method="POST" enctype="multipart/form-data">

              <div class="form-group">
                <label class="form-label">Item Name *</label>
                <input type="text" name="item_name" class="form-control"
                       placeholder="Item name " required maxlength="150">
              </div>

              <div class="form-group">
                <label class="form-label">Category *</label>
                <select name="category" class="form-control" required>
                  <option value="" disabled selected>Select a category</option>
                  <option>Electronics</option><option>Books</option><option>Clothing</option>
                  <option>Keys</option><option>IDs/Wallets</option><option>Bags</option>
                  <option>Accessories</option><option>Other</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Location Found *</label>
                <select name="location_found" class="form-control" required>
                  <option value="" disabled selected>Select location found</option>
    
                  <!-- Academic & Main Blocks -->
                  <optgroup label="Academic & Main Blocks">
                  <option value="Central Building">Central Building</option>
                  <option value="Management Science Building (MSB)">Management Science Building (MSB)</option>
                  <option value="Student Center (STC)">Student Center (STC)</option>
                  <option value="Strathmore Business School (SBS)">Strathmore Business School (SBS)</option>
                  <option value="Phase 3 / New Academic Block">Phase 3 / New Academic Block</option>
                  </optgroup>

                  <!-- Specific Hubs & Common Areas -->
                  <optgroup label="Common Hubs & Facilities">
                  <option value="University Library">University Library</option>
                  <option value="STC Cafeteria">STC Cafeteria</option>
                  <option value="Main Auditorium">Main Auditorium</option>
                  <option value="Sports Complex / Sports Pavilion">Sports Complex / Sports Pavilion</option>
                  <option value="@iLabAfrica / @iBizAfrica">@iLabAfrica / @iBizAfrica</option>
                  <option value="Graduation Square">Graduation Square</option>
                  </optgroup>

                  <!-- Outdoor & General Areas -->
                  <optgroup label="Outdoor & General">
                  <option value="Main Parking Lot">Main Parking Lot</option>
                  <option value="Campus Pathways / Gardens">Campus Pathways / Gardens</option>
                  <option value="Main Gate / Security Desk">Main Gate / Security Desk</option>
                  <option value="Other / Not Listed">Other / Not Listed</option>
                  </optgroup>
  </select>
</div>

              <div class="form-group">
                <label class="form-label">Description *</label>
                <textarea name="description" class="form-control" rows="4"
                  placeholder="Describe the item: colour, brand, any distinguishing marks…" required></textarea>
              </div>

              <div class="form-group">
                <label class="form-label">Photo (optional)</label>
                <div class="upload-zone"
                     onclick="document.getElementById('imageInput').click()"
                     ondragover="event.preventDefault();this.classList.add('drag')"
                     ondragleave="this.classList.remove('drag')"
                     ondrop="handleDrop(event)">
                  <div class="icon">📸</div>
                  <p>Click to upload or drag &amp; drop<br>
                  <small style="font-size:11px;">JPG, PNG, GIF, WEBP · max 5 MB</small></p>
                  <img id="imgPreview" src="" alt="Preview">
                </div>
                <input type="file" id="imageInput" name="image" accept="image/*"
                       style="display:none" onchange="previewImage(this)">
              </div>

              <button type="submit" class="btn-gold w-100" style="justify-content:center;padding:12px;font-size:14px;">
                 Submit Report
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
function previewImage(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.getElementById('imgPreview');
      img.src = e.target.result;
      img.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
function handleDrop(e) {
  e.preventDefault();
  e.currentTarget.classList.remove('drag');
  if (e.dataTransfer.files.length) {
    document.getElementById('imageInput').files = e.dataTransfer.files;
    previewImage(document.getElementById('imageInput'));
  }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>