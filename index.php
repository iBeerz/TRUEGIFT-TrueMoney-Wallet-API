<?php
declare(strict_types=1);

/**
 * ------------------------------------------------------------------
 *  TRUEGIFT · ระบบเติมเงินด้วยซองของขวัญ TrueWallet
 *  Single page (index.php) + PDO + Bootstrap 5 + SweetAlert2
 * ------------------------------------------------------------------
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Bangkok');

const APP_NAME              = 'TRUEGIFT';
const APP_TAGLINE           = 'ระบบเติมเงินอัตโนมัติ';
const VIDEO_URL             = 'https://www.tiktok.com/@gm1999x2u/video/7228389964076141830';
const FOOTER_URL            = 'https://www.tmweasyapi.com/';
const FOOTER_TEXT           = 'TMWeasy.com API Payment';
const API_TIMEOUT           = 30;
const API_STATUS_TIMEOUT    = 8;
const STATUS_CACHE_SECONDS  = 30;

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-store, max-age=0');
}

final class AppException extends RuntimeException
{
}

/* ------------------------------------------------------------------
 |  Helpers
 * ------------------------------------------------------------------ */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $payload): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
    }

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): void
{
    $expected = $_SESSION['csrf_token'] ?? '';

    if (!is_string($expected) || $expected === '' || !hash_equals($expected, $token)) {
        throw new AppException('เซสชันหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่');
    }
}

function flash(string $type, string $title, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type,
        'title'   => $title,
        'message' => $message,
    ];
}

function redirect_back(): void
{
    $ref1 = input_string($_POST, 'ref1');
    $url  = 'index.php';

    if ($ref1 !== '') {
        $url .= '?ref1=' . rawurlencode($ref1);
    }

    if (!headers_sent()) {
        header('Location: ' . $url, true, 303);
    }
    exit;
}

function money(float $amount): string
{
    return number_format($amount, 2, '.', ',');
}

function input_string(array $source, string $key): string
{
    $value = $source[$key] ?? '';

    return is_string($value) ? trim($value) : '';
}

function client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['HTTP_CLIENT_IP'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || trim($candidate) === '') {
            continue;
        }

        $ip = trim(explode(',', $candidate)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return '0.0.0.0';
}

function safe_identifier(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new AppException('การตั้งชื่อตารางหรือฟิลด์ใน config.php ไม่ถูกต้อง');
    }

    return '`' . $name . '`';
}

/* ------------------------------------------------------------------
 |  Database (PDO only)
 * ------------------------------------------------------------------ */

function db(array $config): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = $config['database'] ?? [];

    $host    = trim((string) ($db['host'] ?? ''));
    $name    = trim((string) ($db['name'] ?? ''));
    $user    = trim((string) ($db['user'] ?? ''));
    $pass    = (string) ($db['password'] ?? '');
    $port    = (int) ($db['port'] ?? 3306);
    $charset = (string) ($db['charset'] ?? 'utf8mb4');

    if ($host === '' || $name === '' || $user === '') {
        throw new AppException('ระบบยังไม่ได้ตั้งค่าฐานข้อมูล กรุณาติดต่อผู้ดูแลระบบ');
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $charset)) {
        $charset = 'utf8mb4';
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $host,
        $port > 0 ? $port : 3306,
        $name,
        $charset
    );

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ]);

    return $pdo;
}

/* ------------------------------------------------------------------
 |  API
 * ------------------------------------------------------------------ */

function api_credentials_ready(array $config): bool
{
    $api = $config['api'] ?? [];

    return trim((string) ($api['username'] ?? '')) !== ''
        && (string) ($api['password'] ?? '') !== ''
        && trim((string) ($api['tmemail'] ?? '')) !== '';
}

function api_credentials(array $config): array
{
    $api = $config['api'] ?? [];

    $username = trim((string) ($api['username'] ?? ''));
    $password = (string) ($api['password'] ?? '');
    $tmemail  = trim((string) ($api['tmemail'] ?? ''));

    if ($username === '' || $password === '' || $tmemail === '') {
        throw new AppException('ระบบยังไม่ได้ตั้งค่าข้อมูล API กรุณาติดต่อผู้ดูแลระบบ');
    }

    return [
        'username' => $username,
        'password' => $password,
        'tmemail'  => $tmemail,
    ];
}

function call_api(string $baseUrl, array $params, int $timeout = API_TIMEOUT): array
{
    if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
        throw new AppException('ยังไม่ได้ตั้งค่า url_api ใน config.php');
    }

    if (!function_exists('curl_init')) {
        throw new AppException('เซิร์ฟเวอร์ไม่รองรับ cURL กรุณาติดต่อผู้ดูแลระบบ');
    }

    $separator = str_contains($baseUrl, '?') ? '&' : '?';
    $url       = $baseUrl . $separator . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    $ch = curl_init($url);
    if ($ch === false) {
        throw new AppException('ไม่สามารถเริ่มการเชื่อมต่อ API ได้');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => APP_NAME . ' Topup (+PHP/' . PHP_VERSION . ')',
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
        ],
    ]);

    $body     = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        error_log('[api] curl error: ' . $curlErr);
        throw new AppException('ไม่สามารถเชื่อมต่อกับ API ได้ กรุณาลองใหม่ภายหลัง');
    }

    $data = json_decode((string) $body, true);

    if (!is_array($data)) {
        $preview = function_exists('mb_substr')
            ? mb_substr((string) $body, 0, 500)
            : substr((string) $body, 0, 500);
        error_log('[api] invalid response (HTTP ' . $httpCode . '): ' . $preview);
        throw new AppException('รูปแบบข้อมูลตอบกลับจาก API ไม่ถูกต้อง');
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('[api] HTTP ' . $httpCode . ' response: ' . (string) $body);
        throw new AppException('API ตอบกลับด้วยสถานะ HTTP ' . $httpCode);
    }

    return $data;
}

function api_status(array $config): array
{
    if (!api_credentials_ready($config)) {
        return [
            'ok'      => false,
            'state'   => 'unconfigured',
            'message' => 'ระบบยังไม่ได้ตั้งค่าข้อมูล API กรุณากรอก api.username, api.password และ api.tmemail ใน config.php',
        ];
    }

    try {
        $credentials = api_credentials($config);

        $response = call_api(
            (string) ($config['url_api'] ?? ''),
            $credentials + ['json' => 1],
            API_STATUS_TIMEOUT
        );

        $ready   = (($response['Status'] ?? '') === 'ready');
        $message = trim((string) ($response['Msg'] ?? ''));

        if ($message === '') {
            $message = $ready ? 'API พร้อมใช้งาน' : 'API ไม่พร้อมใช้งาน';
        }

        return [
            'ok'      => $ready,
            'state'   => $ready ? 'ready' : 'error',
            'message' => $message,
        ];
    } catch (Throwable $e) {
        if (!$e instanceof AppException) {
            error_log('[status] ' . $e->getMessage());
        }

        return [
            'ok'      => false,
            'state'   => 'error',
            'message' => $e instanceof AppException
                ? $e->getMessage()
                : 'ไม่สามารถเชื่อมต่อ API ได้',
        ];
    }
}

/* ------------------------------------------------------------------
 |  Gift voucher + topup
 * ------------------------------------------------------------------ */

function normalize_gift_url(string $url): string
{
    $url = trim($url);

    if ($url === '') {
        throw new AppException('กรุณากรอกลิงก์ซองของขวัญ');
    }

    if (strlen($url) > 500) {
        throw new AppException('ลิงก์ซองของขวัญยาวเกินไป');
    }

    $parts = parse_url($url);

    if (
        !is_array($parts)
        || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || empty($parts['host'])
    ) {
        throw new AppException('ลิงก์ซองของขวัญไม่ถูกต้อง ต้องขึ้นต้นด้วย https://gift.truemoney.com/');
    }

    $host = strtolower((string) $parts['host']);

    if ($host !== 'gift.truemoney.com') {
        throw new AppException('ลิงก์ต้องเป็นของ gift.truemoney.com เท่านั้น');
    }

    parse_str((string) ($parts['query'] ?? ''), $query);
    $voucher = $query['v'] ?? '';

    if (!is_string($voucher) || !preg_match('/^[A-Za-z0-9_-]{4,200}$/', $voucher)) {
        throw new AppException('ลิงก์ซองของขวัญไม่ถูกต้อง ไม่พบรหัสซอง (v)');
    }

    return 'https://gift.truemoney.com/campaign/?v=' . $voucher;
}

function process_topup(array $config, array $input): array
{
    $username = input_string($input, 'ref1');
    $giftUrl  = input_string($input, 'gifturl');

    if ($username === '') {
        throw new AppException('กรุณากรอก username หรือ userID ที่ต้องการเติมเครดิต');
    }

    $length = function_exists('mb_strlen') ? mb_strlen($username, 'UTF-8') : strlen($username);

    if ($length > 100 || !preg_match('/^[\p{L}\p{N}._@-]+$/u', $username)) {
        throw new AppException('ข้อมูลผู้ใช้ไม่ถูกต้อง (ใช้ได้เฉพาะตัวอักษร ตัวเลข จุด ขีดล่าง @ และ -)');
    }

    $giftUrl     = normalize_gift_url($giftUrl);
    $credentials = api_credentials($config);

    try {
        $pdo = db($config);
    } catch (AppException $e) {
        throw $e;
    } catch (Throwable $e) {
        error_log('[db] ' . $e->getMessage());
        throw new AppException('เชื่อมต่อฐานข้อมูลไม่สำเร็จ กรุณาตรวจสอบการตั้งค่าฐานข้อมูล');
    }

    $table      = safe_identifier((string) ($config['table']['users'] ?? 'users'));
    $userField  = safe_identifier((string) ($config['table']['user_field'] ?? 'username'));
    $pointField = safe_identifier((string) ($config['table']['point_field'] ?? 'points'));

    $check = $pdo->prepare(
        "SELECT {$pointField} FROM {$table} WHERE {$userField} = :username LIMIT 1"
    );
    $check->execute([':username' => $username]);

    if ($check->fetch() === false) {
        throw new AppException('ไม่พบผู้ใช้ที่ระบุในระบบ กรุณาตรวจสอบอีกครั้ง');
    }

    $response = call_api((string) ($config['url_api'] ?? ''), [
        'username'      => $credentials['username'],
        'password'      => $credentials['password'],
        'tmemail'       => $credentials['tmemail'],
        'transactionid' => $giftUrl,
        'clientip'      => client_ip(),
        'ref1'          => $username,
        'action'        => 'yes',
        'json'          => 1,
    ]);

    if (($response['Status'] ?? '') !== 'check_success') {
        $message = trim((string) ($response['Msg'] ?? ''));

        throw new AppException($message !== '' ? $message : 'ไม่สามารถแลกซองของขวัญได้ กรุณาตรวจสอบลิงก์อีกครั้ง');
    }

    $rawAmount = $response['Amount'] ?? null;
    $rawAmount = is_scalar($rawAmount) ? str_replace(',', '', (string) $rawAmount) : '';

    if ($rawAmount === '' || !is_numeric($rawAmount)) {
        throw new AppException('ไม่พบยอดเงินจากซองของขวัญ');
    }

    $amount = round((float) $rawAmount, 2);

    if (!is_finite($amount) || $amount <= 0 || $amount > 1000000) {
        throw new AppException('ยอดเงินจากซองของขวัญไม่ถูกต้อง');
    }

    $update = $pdo->prepare(
        "UPDATE {$table} SET {$pointField} = {$pointField} + :amount WHERE {$userField} = :username"
    );
    $update->bindValue(':amount', number_format($amount, 2, '.', ''), PDO::PARAM_STR);
    $update->bindValue(':username', $username, PDO::PARAM_STR);
    $update->execute();

    $balanceStmt = $pdo->prepare(
        "SELECT {$pointField} FROM {$table} WHERE {$userField} = :username LIMIT 1"
    );
    $balanceStmt->execute([':username' => $username]);
    $balance = (float) ($balanceStmt->fetchColumn() ?: 0);

    error_log(sprintf('[topup] success user=%s amount=%s', $username, money($amount)));

    return [
        'username'     => $username,
        'amount_text'  => money($amount),
        'balance_text' => money($balance),
    ];
}

function handle_topup(array $config): void
{
    $isAjax = isset($_POST['ajax']);

    try {
        verify_csrf(input_string($_POST, 'csrf'));

        $result  = process_topup($config, $_POST);
        $message = sprintf(
            'ได้รับ %s บาท เข้าบัญชี %s แล้ว (เครดิตคงเหลือ %s)',
            $result['amount_text'],
            $result['username'],
            $result['balance_text']
        );

        if ($isAjax) {
            json_response([
                'ok'      => true,
                'message' => $message,
            ] + $result);
        }

        flash('success', 'เติมเงินสำเร็จ', $message);
    } catch (Throwable $e) {
        error_log('[topup] ' . $e->getMessage());

        $message = $e instanceof AppException
            ? $e->getMessage()
            : 'เกิดข้อผิดพลาดของระบบ กรุณาลองใหม่ภายหลัง';

        if ($isAjax) {
            json_response([
                'ok'      => false,
                'message' => $message,
            ]);
        }

        flash('error', 'เติมเงินไม่สำเร็จ', $message);
    }

    redirect_back();
}

/* ------------------------------------------------------------------
 |  Boot
 * ------------------------------------------------------------------ */

$configFile = __DIR__ . '/config.php';
$config     = is_file($configFile) ? require $configFile : [];

if (!is_array($config)) {
    $config = [];
}

$action = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : '';

if ($action === 'status') {
    $cached = $_SESSION['api_status'] ?? null;

    if (
        is_array($cached)
        && isset($cached['time'], $cached['data'])
        && (time() - (int) $cached['time']) < STATUS_CACHE_SECONDS
    ) {
        json_response((array) $cached['data']);
    }

    $data = api_status($config);
    $_SESSION['api_status'] = [
        'time' => time(),
        'data' => $data,
    ];

    json_response($data);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'topup') {
    handle_topup($config);
}

$dbError = null;

try {
    db($config);
} catch (AppException $e) {
    $dbError = 'ระบบยังไม่ได้ตั้งค่าฐานข้อมูล กรุณากรอก database.host, database.name และ database.user ใน config.php';
} catch (Throwable $e) {
    error_log('[db] ' . $e->getMessage());
    $dbError = 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ กรุณาตรวจสอบค่า database ใน config.php และสถานะ MySQL/MariaDB';
}

if ($dbError !== null && !headers_sent()) {
    http_response_code(503);
}

$flash = null;
if (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$prefill = input_string($_GET, 'ref1');
$videoPath = __DIR__ . '/turewallet-gift.mp4';
$videoVersion = is_file($videoPath) ? (int) filemtime($videoPath) : 1;

// เวอร์ชันไฟล์ static ใช้ต่อท้าย URL เพื่อกันเบราว์เซอร์ใช้ไฟล์เก่าจาก cache
$cssPath    = __DIR__ . '/custom.css';
$jsPath     = __DIR__ . '/custom.js';
$cssVersion = is_file($cssPath) ? (int) filemtime($cssPath) : 1;
$jsVersion  = is_file($jsPath) ? (int) filemtime($jsPath) : 1;

$appData = [
    'statusUrl' => 'index.php?action=status',
    'flash'     => $flash,
    'dbError'   => $dbError,
];

?>
<!DOCTYPE html>
<html lang="th" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#070a12">
    <title>เติมเงินด้วยซองของขวัญ · <?= e(APP_NAME) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <link href="custom.css?v=<?= $cssVersion ?>" rel="stylesheet">
</head>
<body>
<div class="bg-grid" aria-hidden="true"></div>

<main class="shell">
    <section class="panel" aria-label="ระบบเติมเงินด้วยซองของขวัญ">
        <div class="panel-inner">
            <div class="row g-0">
                <div class="col-lg-5">
                    <div class="intro h-100 d-flex flex-column">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="brand">
                                <span class="brand-mark" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
                                <div>
                                    <div class="brand-name"><?= e(APP_NAME) ?></div>
                                    <div class="brand-sub"><?= e(APP_TAGLINE) ?></div>
                                </div>
                            </div>

                            <div class="status-pill" id="apiStatus" data-state="loading" role="button" tabindex="0" aria-live="polite" title="คลิกเพื่อดูรายละเอียดสถานะ">
                                <span class="status-dot" aria-hidden="true"></span>
                                <span id="apiStatusText">กำลังตรวจสอบ API…</span>
                            </div>
                        </div>

                        <h1>เติมเครดิตด้วย<br><span class="text-gradient">ซองของขวัญ</span></h1>
                        <p class="intro-lead">
                            วางลิงก์ซองของขวัญ TrueMoney Wallet ระบบจะตรวจสอบและเติมเครดิตให้ทันที
                            โดยไม่ต้องรอแอดมิน
                        </p>

                        <div class="feature">
                            <span class="feature-icon" aria-hidden="true"><i class="bi bi-lightning-charge-fill"></i></span>
                            <div>
                                <strong>ตรวจสอบอัตโนมัติ</strong>
                                <span>แลกลิงก์และตรวจยอดได้ทันที ใช้งานได้ตลอด 24 ชั่วโมง</span>
                            </div>
                        </div>

                        <div class="feature">
                            <span class="feature-icon" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                            <div>
                                <strong>ปลอดภัยด้วย PDO</strong>
                                <span>ป้องกัน SQL Injection และตรวจสอบทุกคำขอด้วย CSRF Token</span>
                            </div>
                        </div>

                        <div class="feature">
                            <span class="feature-icon" aria-hidden="true"><i class="bi bi-gift-fill"></i></span>
                            <div>
                                <strong>รับเฉพาะซองของขวัญ</strong>
                                <span>รองรับลิงก์จาก gift.truemoney.com เท่านั้น</span>
                            </div>
                        </div>

                        <ol class="steps">
                            <li><span class="n">1</span> คัดลอกลิงก์ซองของขวัญจากแอป TrueMoney Wallet</li>
                            <li><span class="n">2</span> วางลิงก์และกดตรวจสอบ</li>
                            <li><span class="n">3</span> รับเครดิตเข้าระบบทันที</li>
                        </ol>

                        <button type="button"
                                class="btn-video"
                                data-bs-toggle="modal"
                                data-bs-target="#videoModal">
                            <span class="play-dot" aria-hidden="true"><i class="bi bi-play-fill"></i></span>
                            <span>ดูวิดีโอวิธีสร้างซองของขวัญ</span>
                            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="form-side">
                        <div class="form-head">
                            <span class="eyebrow"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i> เติมเงินอัตโนมัติ</span>
                            <h2>กรอกข้อมูลเพื่อเติมเครดิต</h2>
                            <p>ใช้ได้กับลิงก์ซองของขวัญ TrueMoney Wallet เท่านั้น</p>
                        </div>

                        <noscript>
                            <div class="noscript-box">ระบบต้องเปิดใช้งาน JavaScript เพื่อยืนยันและแจ้งเตือนผลลัพธ์</div>
                        </noscript>

                        <?php if ($dbError !== null): ?>
                            <div class="db-alert" role="alert">
                                <i class="bi bi-database-x" aria-hidden="true"></i>
                                <div>
                                    <strong>ฐานข้อมูลไม่พร้อมใช้งาน</strong>
                                    <span><?= e($dbError) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form id="topupForm" method="post" action="index.php" novalidate autocomplete="off">
                            <input type="hidden" name="form_action" value="topup">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                            <div class="mb-3">
                                <label class="form-label" for="ref1">ชื่อผู้ใช้ / UserID (Ref1)</label>
                                <div class="field">
                                    <i class="bi bi-person field-icon" aria-hidden="true"></i>
                                    <input type="text"
                                           class="form-control"
                                           id="ref1"
                                           name="ref1"
                                           value="<?= e($prefill) ?>"
                                           placeholder="username หรือ userID ที่ต้องการเติมเครดิต"
                                           maxlength="100"
                                           spellcheck="false"
                                           <?= $dbError !== null ? 'disabled' : '' ?>
                                           required>
                                </div>
                                <div class="form-hint">ใช้ค่าเดียวกับฟิลด์ <code>user_field</code> ที่ตั้งไว้ (username หรือ userID)</div>
                            </div>

                            <div class="mb-0">
                                <label class="form-label" for="gifturl">ลิงก์ซองของขวัญ</label>
                                <div class="field has-paste">
                                    <i class="bi bi-link-45deg field-icon" aria-hidden="true"></i>
                                    <input type="url"
                                           class="form-control"
                                           id="gifturl"
                                           name="gifturl"
                                           placeholder="https://gift.truemoney.com/campaign/?v=..."
                                           maxlength="500"
                                           spellcheck="false"
                                           <?= $dbError !== null ? 'disabled' : '' ?>
                                           required>
                                    <button type="button" class="paste-btn" id="pasteBtn" <?= $dbError !== null ? 'disabled' : '' ?>>วาง</button>
                                </div>
                                <div class="form-hint">คัดลอกจากแอป TrueMoney Wallet → ซองของขวัญ → แชร์ → คัดลอกลิงก์</div>
                            </div>

                            <button type="submit" class="btn-topup" id="submitBtn" <?= $dbError !== null ? 'disabled' : '' ?>>
                                <span class="spinner-border spinner-border-sm d-none" id="submitSpinner" aria-hidden="true"></span>
                                <i class="bi bi-shield-check" id="submitIcon" aria-hidden="true"></i>
                                <span id="submitText">ตรวจสอบและเติมเงิน</span>
                            </button>

                            <p class="fineprint">
                                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                <span>ระบบตรวจสอบยอดจากลิงก์โดยตรง และไม่บันทึกลิงก์ซองของขวัญของคุณ</span>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="page-footer">
        <span>© <?= date('Y') ?> <?= e(APP_NAME) ?> · <?= e(APP_TAGLINE) ?></span>
        <a href="<?= e(FOOTER_URL) ?>" target="_blank" rel="noopener noreferrer">
            <?= e(FOOTER_TEXT) ?>
            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
        </a>
    </footer>
</main>

<!-- Video tutorial modal -->
<div class="modal fade" id="videoModal" tabindex="-1" aria-labelledby="videoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content video-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="videoModalLabel">
                    <i class="bi bi-play-btn me-1" aria-hidden="true"></i>
                    วิธีสร้างซองของขวัญ TrueMoney Wallet
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body p-0">
                <div class="ratio ratio-16x9">
                    <video id="tutorialVideo" controls playsinline preload="metadata">
                        <source src="turewallet-gift.mp4?v=<?= $videoVersion ?>" type="video/mp4">
                        เบราว์เซอร์ของคุณไม่รองรับการเล่นวิดีโอ
                    </video>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="text-secondary small">วิดีโอแนะนำการสร้างซองของขวัญ</span>
                <a href="<?= e(VIDEO_URL) ?>"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="btn btn-sm btn-outline-light">
                    ดูบน TikTok
                    <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
    window.APP = <?= json_encode(
        $appData,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_INVALID_UTF8_SUBSTITUTE
    ) ?>;
</script>
<script src="custom.js?v=<?= $jsVersion ?>"></script>
</body>
</html>