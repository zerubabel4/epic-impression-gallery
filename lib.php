<?php
// Shared helpers: config, database, security, Cloudflare R2 signing, design options.
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

function base() { return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/'); }

if (!file_exists(__DIR__ . '/config.php')) { header('Location: ' . base() . '/setup.php'); exit; }
$CFG = require __DIR__ . '/config.php';

function db() {
    static $pdo; global $CFG;
    if (!$pdo) {
        $dsn = $CFG['db_dsn'] ?? "mysql:host={$CFG['db_host']};dbname={$CFG['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $CFG['db_user'] ?? null, $CFG['db_pass'] ?? null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        if (strpos($dsn, 'sqlite:') === 0) // local testing only
            $pdo->sqliteCreateFunction('FIND_IN_SET', function ($n, $list) { return in_array($n, explode(',', (string)$list)) ? 1 : 0; });
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
    if (!empty($CFG['local_files'])) return base() . '/' . $CFG['local_files'] . '/' . $key; // local testing only
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
    global $CFG;
    if (!$key || !empty($CFG['local_files'])) return;
    $ch = curl_init(r2_url('DELETE', $key, 300));
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    curl_exec($ch); curl_close($ch);
}
function delete_photo_files($p) { r2_delete($p['key_orig']); r2_delete($p['key_web']); r2_delete($p['key_thumb']); }

// ---------- Design options shared by the admin pickers and the client gallery ----------
const FONT_LINK = 'https://fonts.bunny.net/css?family=inter:400,500,600|cormorant-garamond:400,500|jost:300,400|montserrat:700,800&display=swap';

function covers() {
    return ['center' => 'Center', 'left' => 'Left', 'novel' => 'Novel', 'vintage' => 'Vintage', 'frame' => 'Frame',
            'stripe' => 'Stripe', 'divider' => 'Divider', 'journal' => 'Journal', 'none' => 'No cover'];
}
// name => [background, soft background, accent, text]
function palettes() {
    return [
        'light' => ['#ffffff', '#f5f5f5', '#333333', '#222222'], 'gold' => ['#fffefb', '#f8f4ec', '#9c8a62', '#2b2925'],
        'rose' => ['#fbf8f8', '#f3eeee', '#9b7b77', '#2b2626'], 'terracotta' => ['#faf7f5', '#ece6e2', '#9d7860', '#2b2724'],
        'sand' => ['#f5f2ef', '#e6e1dd', '#9b8a7c', '#2a2826'], 'olive' => ['#f7f7f3', '#ecebe7', '#9a9c7e', '#282a24'],
        'agave' => ['#f5f5f5', '#eaede9', '#86998f', '#242827'], 'sea' => ['#f9f9f9', '#e9e9eb', '#8f8e9c', '#25252a'],
        'dark' => ['#1c1c1c', '#2a2a2a', '#cfcfcf', '#f1f1f1'],
    ];
}
// name => [label, description, heading font, weight, transform, letter spacing, body font]
function typefaces() {
    $inter = 'Inter, "Helvetica Neue", Arial, sans-serif';
    $corm = '"Cormorant Garamond", Georgia, serif';
    $jost = 'Jost, "Century Gothic", "Helvetica Neue", sans-serif';
    $mont = 'Montserrat, "Helvetica Neue", Arial, sans-serif';
    return [
        'sans' => ['Sans', 'A neutral font', $inter, 600, 'uppercase', '.08em', $inter],
        'serif' => ['Serif', 'A classic font', $corm, 500, 'none', '.03em', $corm],
        'modern' => ['Modern', 'A sophisticated font', $jost, 300, 'none', '.08em', $inter],
        'timeless' => ['Timeless', 'A light and airy font', $jost, 300, 'none', '.02em', $jost],
        'bold' => ['Bold', 'A punchy font', $mont, 800, 'uppercase', '.04em', $inter],
        'subtle' => ['Subtle', 'A minimal font', $jost, 300, 'uppercase', '.2em', $corm],
    ];
}
function type_vars($name) {
    $t = typefaces()[$name] ?? typefaces()['sans'];
    return "--hfont:$t[2];--hweight:$t[3];--htrans:$t[4];--hspace:$t[5];--bfont:$t[6]";
}
function palette_vars($name) {
    $p = palettes()[$name] ?? palettes()['light'];
    return "--bg:$p[0];--soft:$p[1];--accent:$p[2];--text:$p[3]";
}
function photo_order($mode) {
    return ['manual' => 'position, id', 'name_asc' => 'filename', 'name_desc' => 'filename DESC',
            'date_asc' => 'taken_at, id', 'date_desc' => 'taken_at DESC, id DESC'][$mode] ?? 'position, id';
}

// ---------- Database upgrades: applied automatically, once, after an update ----------
function migrate() {
    try { $v = (int)db()->query("SELECT v FROM meta WHERE k='schema'")->fetchColumn(); }
    catch (Exception $ex) {
        db()->exec('CREATE TABLE IF NOT EXISTS meta (k VARCHAR(50) PRIMARY KEY, v VARCHAR(190) NOT NULL)');
        $v = 0;
    }
    if (!$v) { q("INSERT INTO meta (k, v) VALUES ('schema', '1')"); $v = 1; }
    $steps = [
        2 => ["ALTER TABLE collections ADD palette VARCHAR(12) NOT NULL DEFAULT 'light'",
              "UPDATE collections SET cover_style='center' WHERE cover_style IN ('full','banner')",
              "UPDATE collections SET cover_style='novel' WHERE cover_style='split'",
              "UPDATE collections SET font='serif' WHERE font='classic'",
              "UPDATE collections SET font='sans' WHERE font='mono'",
              "UPDATE collections SET grid_style='masonry' WHERE grid_style='square'",
              "UPDATE collections SET grid_size='medium' WHERE grid_size='small'"],
    ];
    foreach ($steps as $n => $sqls) {
        if ($v >= $n) continue;
        foreach ($sqls as $sql) { try { db()->exec($sql); } catch (Exception $ex) { /* already applied */ } }
        q("UPDATE meta SET v=? WHERE k='schema'", [$n]);
    }
}
migrate();
