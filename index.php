<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SU Lost &amp; Found – Sign In</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #0B2D5E;
      min-height: 100vh;
      display: flex;
      align-items: center;
      font-family: 'Inter', system-ui, sans-serif;
    }
    .auth-card {
      border-radius: 14px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.35);
      border: none;
    }
    .auth-header {
      background: #0B2D5E;
      border-radius: 14px 14px 0 0;
      padding: 28px 24px 20px;
      text-align: center;
    }
    .auth-header img   { height: 52px; margin-bottom: 10px; }
    .auth-header h5    { color: #fff; font-weight: 700; margin: 0; font-size: 18px; }
    .auth-header small { color: rgba(255,255,255,0.55); font-size: 12px; }
    .auth-body         { padding: 24px; }
    .form-label        { font-size: 13px; font-weight: 600; color: #0B2D5E; }
    .form-control:focus {
      border-color: #0B2D5E;
      box-shadow: 0 0 0 3px rgba(11,45,94,0.12);
    }
    .btn-su {
      background: #0B2D5E;
      color: white;
      font-weight: 700;
      border: none;
      padding: 10px;
    }
    .btn-su:hover { background: #071d3e; color: white; }
    .divider { height: 1px; background: #e9ecef; margin: 16px 0; }
    .toggle-link { font-size: 13px; color: #0B2D5E; font-weight: 600; text-decoration: none; }
    .toggle-link:hover { color: #C9A227; }
  </style>
</head>
<body>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-5 col-sm-10">

      <div class="card auth-card">
        <!-- Header -->
        <div class="auth-header">
          <img src="assets/strath.png" alt="Strathmore University">
          <h5 id="formTitle">Lost &amp; Found Portal</h5>
          <small id="formSubtitle">Sign in to your student account</small>
        </div>

        <!-- Alerts -->
        <div class="auth-body">
          <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger py-2 mb-3" style="font-size:13px;">
              <?php
                $err = $_GET['error'];
                if ($err === 'invalid')     echo " Invalid email or password.";
                elseif ($err === 'exists')  echo " That email is already registered.";
                elseif ($err === 'unverified') echo " Please verify your email first.";
                else echo " Something went wrong. Try again.";
              ?>
            </div>
          <?php endif; ?>
          <?php if (isset($_GET['status']) && $_GET['status'] === 'registered'): ?>
            <div class="alert alert-success py-2 mb-3" style="font-size:13px;">
               Account created! Proced with signing in.
            </div>
          <?php endif; ?>

          <!-- Form -->
          <form id="authForm" action="access_logic.php" method="POST">
            <input type="hidden" name="action" id="authAction" value="login">

            <div class="mb-3 d-none" id="nameField">
              <label class="form-label">Full Name</label>
              <input type="text" name="username" class="form-control" placeholder="enter name ">
            </div>

            <div class="mb-3">
              <label class="form-label">Strathmore Email</label>
              <input type="email" name="email" class="form-control" placeholder="@strathmore.edu" required>
            </div>

            <div class="mb-3 d-none" id="courseField">
              <label class="form-label">Course &amp; Year</label>
              <input type="text" name="course_year" class="form-control" placeholder="enter course and year">
            </div>

            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-su w-100 mt-1" id="submitBtn">Sign In</button>
          </form>

          <div class="divider"></div>
          <div class="text-center">
            <a href="#" class="toggle-link" id="toggleBtn" onclick="toggleAuth(); return false;">
              Don't have an account? <u>Register here</u>
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
function toggleAuth() {
  const isLogin = document.getElementById('authAction').value === 'login';
  document.getElementById('authAction').value  = isLogin ? 'register' : 'login';
  document.getElementById('authForm').action   = isLogin ? 'register_process.php' : 'access_logic.php';
  document.getElementById('nameField').classList.toggle('d-none', !isLogin);
  document.getElementById('courseField').classList.toggle('d-none', !isLogin);
  document.getElementById('submitBtn').textContent  = isLogin ? 'Create Account' : 'Sign In';
  document.getElementById('formTitle').textContent  = isLogin ? 'Create Account' : 'Lost & Found Portal';
  document.getElementById('formSubtitle').textContent = isLogin ? 'Register your student account' : 'Sign in to your student account';
  document.getElementById('toggleBtn').innerHTML    = isLogin
    ? 'Already have an account? <u>Sign in</u>'
    : "Don't have an account? <u>Register here</u>";
}
</script>
</body>
</html>