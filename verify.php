<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config/db.php';

$message = "";
// Grab the email from the URL (GET) or from the hidden form field (POST)
$email = isset($_REQUEST['email']) ? $_REQUEST['email'] : '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = $_POST['code'];

    try {
        // 1. Check if the 6-digit code matches the token in HeidiSQL
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND verification_token = ?");
        $stmt->execute([$email, $code]);
        
        if ($stmt->fetch()) {
            // 2. Update the user status
            $update = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE email = ?");
            $update->execute([$email]);
            $message = "<div class='alert alert-success'>Verification successful! <a href='index.php' class='alert-link'>Login here</a></div>";
        } else {
            $message = "<div class='alert alert-danger'>Invalid Code. Please check your email again.</div>";
        }
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Database Error: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Account - SU Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #003366 0%, #0056b3 100%); height: 100vh; display: flex; align-items: center; }
        .verify-card { border-radius: 15px; background: white; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .btn-su { background: #003366; color: white; border: none; }
        .btn-su:hover { background: #002244; color: white; }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="verify-card text-center">
                <h2 class="fw-bold mb-3" style="color: #003366;">Verify Identity</h2>
                <p class="text-muted mb-4">Enter the 6-digit authentication code sent to:<br><strong><?php echo htmlspecialchars($email); ?></strong></p>

                <?php if ($message): ?>
                    <?php echo $message; ?>
                <?php endif; ?>

                <form action="verify.php" method="POST">
                    <!-- Keep the email hidden so we know which user to update -->
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    
                    <div class="mb-4">
                        <input type="text" name="code" class="form-control form-control-lg text-center fw-bold" 
                               placeholder="000000" maxlength="6" pattern="\d{6}" required autofocus 
                               style="letter-spacing: 10px; font-size: 2rem;">
                        <div class="form-text mt-2">Check your Strathmore student email inbox.</div>
                    </div>

                    <button type="submit" class="btn btn-su btn-lg w-100">Verify & Activate</button>
                </form>
                
                <div class="mt-4">
                    <a href="index.php" class="text-decoration-none small text-muted">Back to Sign In</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>