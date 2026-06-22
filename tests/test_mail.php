<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
$mailConfig = require '../config/mail_config.php';
require '../config/db.php';

$mail = new PHPMailer(true);

try {
    // 1. User Data (Simulated for testing)
    $email = $mailConfig['username']; 
    $token = bin2hex(random_bytes(16));
    $verifyLink = "http://localhost/verify.php?token=" . $token;

    // 2. SAVE TO DATABASE FIRST
    $stmt = $pdo->prepare("INSERT INTO users (email, verification_token) VALUES (?, ?) ON DUPLICATE KEY UPDATE verification_token = ?");
    $stmt->execute([$email, $token, $token]);

    // 3. CONFIGURE PHPMAILER
    $mail->isSMTP();
    $mail->Host       = $mailConfig['host'];
    $mail->SMTPAuth   = $mailConfig['auth'];
    $mail->Username   = $mailConfig['username'];
    $mail->Password   = $mailConfig['password'];
    $mail->SMTPSecure = $mailConfig['secure'];
    $mail->Port       = $mailConfig['port'];

    $mail->setFrom($mailConfig['username'], 'SU Lost and Found');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Verify Your Account';
    $mail->Body    = "Click here to verify: <a href='$verifyLink'>$verifyLink</a>";

    $mail->send();
    echo "SUCCESS: Token saved to DB and Email sent to $email";

} catch (Exception $e) {
    echo "ERROR: {$mail->ErrorInfo}";
} catch (PDOException $e) {
    echo "DATABASE ERROR: " . $e->getMessage();
}