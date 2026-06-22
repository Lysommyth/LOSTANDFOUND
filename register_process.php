<?php
require 'config/db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}


$username     = trim($_POST['username'] ?? '');
$email        = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$password     = $_POST['password'] ?? '';
$course_year  = trim($_POST['course_year'] ?? '');


if (
    empty($username) ||
    empty($email) ||
    empty($password)
) {
    header("Location: index.php?error=invalid");
    exit();
}

// Must be Strathmore email
if (!str_ends_with($email, "@strathmore.edu")) {
    header("Location: index.php?error=invalid");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: index.php?error=invalid");
    exit();
}

try {

   
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);

    if ($check->fetch()) {
        header("Location: index.php?error=exists");
        exit();
    }

 
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

   
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, course_year, role)
        VALUES (?, ?, ?, ?, 'student')
    ");

    $stmt->execute([
        $username,
        $email,
        $hashedPassword,
        $course_year
    ]);

    header("Location: index.php?status=registered");
    exit();

} catch (PDOException $e) {
    error_log($e->getMessage());
    header("Location: index.php?error=server");
    exit();
}