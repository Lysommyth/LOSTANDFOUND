<?php
require 'config/db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}


$email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';

// Basic validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
    header("Location: index.php?error=invalid");
    exit();
}

try {

    
    $stmt = $pdo->prepare("
        SELECT id, username, email, password, role, course_year
        FROM users
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

 
    if (!$user || !password_verify($password, $user['password'])) {
        header("Location: index.php?error=invalid");
        exit();
    }

   
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role']    = $user['role'];
    
    if ($user['role'] === 'admin') {
        header("Location: admin/panel.php");
    } else {
        header("Location: dashboard.php");
    }

    exit();

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("System error. Try again later.");
}