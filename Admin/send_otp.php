<?php
require 'config.php';
setCORS();
$conn = getDB();

// ── SendPK.com credentials ────────────────────────────────
// Sign up at: https://sendpk.com
// Price: Rs. 3.80 – 4.00 per SMS (cheapest in Pakistan)
// After login → API Settings → copy your API key below
// Or set environment variable: SENDPK_API_KEY
define('SENDPK_API_KEY',   getenv('SENDPK_API_KEY')   ?: 'YOUR_SENDPK_API_KEY');
define('SENDPK_SENDER_ID', getenv('SENDPK_SENDER_ID') ?: 'SnackZone');  // your registered sender name

// ── OTP table ─────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS otp_codes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    phone      VARCHAR(20)  NOT NULL,
    code       VARCHAR(6)   NOT NULL,
    used       TINYINT(1)   DEFAULT 0,
    expires_at DATETIME     NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
)");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$phone = trim($_POST['phone'] ?? '');

if (!$phone) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required.']);
    exit;
}

// ── Rate limit: max 3 OTPs per phone per 10 minutes ──────
$rate = $conn->prepare(
    "SELECT COUNT(*) as cnt FROM otp_codes
     WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
);
$rate->bind_param('s', $phone);
$rate->execute();
$rateRow = $rate->get_result()->fetch_assoc();
$rate->close();

if ($rateRow['cnt'] >= 3) {
    echo json_encode(['success' => false, 'message' => 'Too many OTP requests. Please wait 10 minutes.']);
    exit;
}

// ── Generate 6-digit OTP ─────────────────────────────────
$code       = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
$expires_at = date('Y-m-d H:i:s', time() + 600); // 10 minutes

// ── Save OTP to DB ────────────────────────────────────────
$ins = $conn->prepare("INSERT INTO otp_codes (phone, code, expires_at) VALUES (?, ?, ?)");
$ins->bind_param('sss', $phone, $code, $expires_at);
$ins->execute();
$ins->close();

// ── Format phone for Pakistan ─────────────────────────────
// SendPK accepts: 03001234567 or 923001234567
// We strip spaces/dashes and keep as-is (03xx format works)
$formatted = preg_replace('/[\s\-\+]/', '', $phone);
// If starts with 92, keep it. If starts with 0, keep it.
// SendPK handles both formats.

// ── Send SMS via SendPK API ───────────────────────────────
$message = "Your Snack Zone OTP is: $code\nValid for 10 minutes. Do not share.";

$apiUrl = 'https://sendpk.com/api/sms.php?' . http_build_query([
    'username' => SENDPK_API_KEY,   // SendPK uses API key as username
    'password' => '',               // leave empty if using API key auth
    'api_key'  => SENDPK_API_KEY,
    'sender'   => SENDPK_SENDER_ID,
    'mobile'   => $formatted,
    'message'  => $message,
    'type'     => 'otp',            // OTP route — fastest delivery
    'format'   => 'json',
]);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response = curl_exec($ch);
$curlErr  = curl_error($ch);
curl_close($ch);

// ── DEV MODE: if API key not configured yet ───────────────
if (SENDPK_API_KEY === 'YOUR_SENDPK_API_KEY') {
    // Return OTP in response for local testing (remove in production)
    echo json_encode([
        'success'  => true,
        'dev_mode' => true,
        'dev_code' => $code,
        'message'  => "[DEV] SendPK not configured yet. OTP: $code",
    ]);
    $conn->close();
    exit;
}

if ($curlErr) {
    echo json_encode(['success' => false, 'message' => 'Network error: ' . $curlErr]);
    $conn->close();
    exit;
}

// ── Parse SendPK response ─────────────────────────────────
$decoded = json_decode($response, true);

// SendPK returns status code in response
// Status 200 or "1200" = success in their API
$status = $decoded['status'] ?? $decoded['code'] ?? '';

if ($status == 200 || $status == '1200' || (isset($decoded['message_id']))) {
    echo json_encode(['success' => true, 'message' => "OTP sent to $phone"]);
} else {
    $errMsg = $decoded['message'] ?? $decoded['error'] ?? $response;
    echo json_encode(['success' => false, 'message' => "Could not send SMS: $errMsg"]);
}

$conn->close();
