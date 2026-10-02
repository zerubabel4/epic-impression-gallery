<?php
// Shared helpers: config, database, security, Cloudflare R2 signing.
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

function base() { return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/'); }

if (!file_exists(__DIR__ . '/config.php')) { header('Location: ' . base() . '/setup.php'); exit; }
$CFG = require __DIR__ . '/config.php';

function db() {
    static $pdo; global $CFG;
    if (!$pdo) {
        $pdo = new PDO("mysql:host={$CFG['db_host']};dbname={$CFG['db_name']};charset=utf8mb4",
            $CFG['db_user'], $CFG['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    }
    return $pdo;
}
function q($sql, $args = []) { $s = db()->prepare($sql); $s->execute($args); return $s; }
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }
function is_admin() { return !empty($_SESSION['admin']); }
function csrf() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function check_csrf() {
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!hash_equals(csrf(), (string)$t)) { http_response_code(403); exit('Invalid form token. Reload the page and try again.'); }
}
function slugify($s) {
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
    return $s !== '' ? $s : 'gallery';
}
function redirect($to) { header('Location: ' . base() . '/' . $to); exit; }

// Presigned URL for a private R2 object (AWS Signature V4, query string form).
function r2_url($method, $key, $expires = 3600) {
    global $CFG;
    $host = $CFG['r2_account'] . '.r2.cloudflarestorage.com';
    $path = '/' . $CFG['r2_bucket'] . '/' . str_replace('%2F', '/', rawurlencode($key));
    $time = gmdate('Ymd\THis\Z'); $day = substr($time, 0, 8);
    $scope = "$day/auto/s3/aws4_request";
    $query = [
        'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential' => $CFG['r2_key'] . '/' . $scope,
        'X-Amz-Date' => $time,
        'X-Amz-Expires' => $expires,
        'X-Amz-SignedHeaders' => 'host',
    ];
    ksort($query);
    $qs = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $canonical = "$method\n$path\n$qs\nhost:$host\n\nhost\nUNSIGNED-PAYLOAD";
    $toSign = "AWS4-HMAC-SHA256\n$time\n$scope\n" . hash('sha256', $canonical);
    $k = hash_hmac('sha256', $day, 'AWS4' . $CFG['r2_secret'], true);
    $k = hash_hmac('sha256', 'auto', $k, true);
    $k = hash_hmac('sha256', 's3', $k, true);
    $k = hash_hmac('sha256', 'aws4_request', $k, true);
    return "https://$host$path?$qs&X-Amz-Signature=" . hash_hmac('sha256', $toSign, $k);
}
function r2_delete($key) {
    if (!$key) return;
    $ch = curl_init(r2_url('DELETE', $key, 300));
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    curl_exec($ch); curl_close($ch);
}
function delete_photo_files($p) { r2_delete($p['key_orig']); r2_delete($p['key_web']); r2_delete($p['key_thumb']); }
