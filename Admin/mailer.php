<?php
// PHPMailer — loaded from /phpmailer/ folder in project root
require_once __DIR__ . '/../phpmailer/Exception.php';
require_once __DIR__ . '/../phpmailer/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ── SMTP credentials (always read from environment) ───────
// Set these in Admin/.env (local) or Admin/.env.production (live)
// Never hardcode credentials in source files
define('MAIL_HOST',      getenv('MAIL_HOST')      ?: 'smtp.gmail.com');
define('MAIL_PORT',      getenv('MAIL_PORT')      ?: 587);
define('MAIL_USERNAME',  getenv('MAIL_USERNAME')  ?: '');
define('MAIL_PASSWORD',  getenv('MAIL_PASSWORD')  ?: '');
define('MAIL_FROM',      getenv('MAIL_FROM')      ?: '');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Snack Zone');

// ── Base URL for verification links ──────────────────────
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/snackzone/Admin');

// ── Send OTP email ────────────────────────────────────────
function sendOtpEmail(string $toEmail, string $toName, string $otp): bool
{
    $subject = 'Your Snack Zone Verification Code';

    $html = '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body  { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
    .wrap { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; }
    .hdr  { background: #000; padding: 28px 32px; text-align: center; }
    .hdr h1 { color: #fff; font-size: 1.4rem; margin: 0; text-transform: uppercase; letter-spacing: -.01em; }
    .hdr span { color: #e63946; }
    .body { padding: 32px; }
    .body p { color: #555; font-size: .9rem; line-height: 1.6; margin-bottom: 16px; }
    .otp-box { background: #000; color: #fff; font-size: 2.4rem; font-weight: 900;
               letter-spacing: 0.35em; text-align: center; padding: 22px 0;
               margin: 24px 0; border-radius: 6px; }
    .foot { padding: 20px 32px; background: #f9f9f9; font-size: .75rem; color: #aaa; text-align: center; }
    .note { font-size: .78rem; color: #aaa; text-align: center; margin-top: 8px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="hdr"><h1>Snack<span>Zone</span></h1></div>
    <div class="body">
      <p>Hi <strong>' . htmlspecialchars($toName) . '</strong>,</p>
      <p>Use the code below to verify your Snack Zone account. It expires in <strong>10 minutes</strong>.</p>
      <div class="otp-box">' . $otp . '</div>
      <p class="note">Do not share this code with anyone.</p>
      <p>If you did not request this, ignore this email.</p>
    </div>
    <div class="foot">&copy; 2026 Snack Zone &mdash; Rawalpindi</div>
  </div>
</body>
</html>';

    $text = "Hi $toName,\n\nYour Snack Zone verification code is: $otp\n\nExpires in 10 minutes. Do not share.\n\n— Snack Zone";

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) MAIL_PORT;
        
        // Debug mode for development — logs to error_log only, never to browser
        if (getenv('APP_ENV') !== 'production') {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer: $str");
            };
        }
        
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $text;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('PHPMailer OTP error: ' . $e->getMessage());
        error_log('PHPMailer debug info: ' . $mail->ErrorInfo);
        return false;
    }
}
function sendVerificationEmail(string $toEmail, string $toName, string $token): bool
{
    $verifyUrl = SITE_URL . '/verify_email.php?token=' . urlencode($token);

    $subject = 'Verify Your Snack Zone Account';

    $html = '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body  { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
    .wrap { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; }
    .hdr  { background: #000; padding: 28px 32px; text-align: center; }
    .hdr h1 { color: #fff; font-size: 1.4rem; margin: 0; text-transform: uppercase; letter-spacing: -.01em; }
    .hdr span { color: #e63946; }
    .body { padding: 32px; }
    .body p { color: #555; font-size: .9rem; line-height: 1.6; margin-bottom: 16px; }
    .btn  { display: inline-block; background: #000; color: #fff !important; text-decoration: none;
            padding: 12px 32px; border-radius: 4px; font-weight: bold; font-size: .85rem;
            text-transform: uppercase; letter-spacing: .06em; }
    .foot { padding: 20px 32px; background: #f9f9f9; font-size: .75rem; color: #aaa; text-align: center; }
    .url  { font-size: .72rem; color: #bbb; word-break: break-all; margin-top: 12px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="hdr"><h1>Snack<span>Zone</span></h1></div>
    <div class="body">
      <p>Hi <strong>' . htmlspecialchars($toName) . '</strong>,</p>
      <p>Welcome to Snack Zone — Rawalpindi ka favourite fast food! Click the button below to verify your email and activate your account.</p>
      <p style="text-align:center; margin: 24px 0;">
        <a class="btn" href="' . $verifyUrl . '">Verify My Email</a>
      </p>
      <p>This link expires in <strong>1 hour</strong>. If you did not create an account, ignore this email.</p>
      <p class="url">Or copy: ' . $verifyUrl . '</p>
    </div>
    <div class="foot">&copy; 2026 Snack Zone &mdash; 6th Rd, Satellite Town, Rawalpindi</div>
  </div>
</body>
</html>';

    $text = "Hi $toName,\n\nVerify your Snack Zone account:\n$verifyUrl\n\nExpires in 1 hour.\n\n— Snack Zone";

    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) MAIL_PORT;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $text;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('PHPMailer error: ' . $e->getMessage());
        return false;
    }
}
