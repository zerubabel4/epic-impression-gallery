<?php
// One-time installer: creates the database tables and writes config.php.
$cfgFile = __DIR__ . '/config.php';
if (file_exists($cfgFile)) exit('Already installed. Delete config.php in the file manager to run setup again.');
$err = '';
$f = array_map('trim', $_POST);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (strlen($f['admin_pass']) < 10) throw new Exception('Choose an admin password of at least 10 characters.');
        foreach (['db_name', 'db_user', 'r2_account', 'r2_bucket', 'r2_key', 'r2_secret', 'site_name'] as $k)
            if ($f[$k] === '') throw new Exception('Please fill in every field.');
        $pdo = new PDO("mysql:host={$f['db_host']};dbname={$f['db_name']};charset=utf8mb4", $f['db_user'], $f['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $tables = [
            "CREATE TABLE IF NOT EXISTS folders (
                id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(190) NOT NULL, share_token CHAR(24) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS collections (
                id INT AUTO_INCREMENT PRIMARY KEY, folder_id INT NULL, name VARCHAR(190) NOT NULL,
                slug VARCHAR(190) NOT NULL UNIQUE, event_date DATE NULL, status VARCHAR(12) NOT NULL DEFAULT 'draft',
                password VARCHAR(190) NOT NULL DEFAULT '', hide_home TINYINT NOT NULL DEFAULT 0, expires_at DATE NULL,
                cover_photo_id INT NULL, cover_style VARCHAR(12) NOT NULL DEFAULT 'full',
                grid_style VARCHAR(12) NOT NULL DEFAULT 'masonry', grid_size VARCHAR(12) NOT NULL DEFAULT 'medium',
                grid_gap VARCHAR(12) NOT NULL DEFAULT 'small', font VARCHAR(12) NOT NULL DEFAULT 'serif',
                color_bg CHAR(7) NOT NULL DEFAULT '#ffffff', color_text CHAR(7) NOT NULL DEFAULT '#222222',
                color_accent CHAR(7) NOT NULL DEFAULT '#8a6d4b', show_filenames TINYINT NOT NULL DEFAULT 0,
                sort_mode VARCHAR(12) NOT NULL DEFAULT 'manual', tags VARCHAR(500) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS sets (
                id INT AUTO_INCREMENT PRIMARY KEY, collection_id INT NOT NULL, name VARCHAR(190) NOT NULL,
                position INT NOT NULL DEFAULT 0, KEY (collection_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS photos (
                id INT AUTO_INCREMENT PRIMARY KEY, collection_id INT NOT NULL, set_id INT NOT NULL,
                filename VARCHAR(255) NOT NULL, key_orig VARCHAR(500) NOT NULL, key_web VARCHAR(500) NOT NULL,
                key_thumb VARCHAR(500) NOT NULL, width INT NOT NULL, height INT NOT NULL, size BIGINT NOT NULL,
                taken_at DATETIME NULL, position INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY (collection_id, set_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
        foreach ($tables as $sql) $pdo->exec($sql);
        $cfg = [
            'site_name' => $f['site_name'],
            'db_host' => $f['db_host'], 'db_name' => $f['db_name'], 'db_user' => $f['db_user'], 'db_pass' => $f['db_pass'],
            'r2_account' => $f['r2_account'], 'r2_bucket' => $f['r2_bucket'], 'r2_key' => $f['r2_key'], 'r2_secret' => $f['r2_secret'],
            'admin_hash' => password_hash($f['admin_pass'], PASSWORD_DEFAULT),
        ];
        if (file_put_contents($cfgFile, "<?php\nreturn " . var_export($cfg, true) . ";\n") === false)
            throw new Exception('Could not write config.php. Check the folder permissions.');
        header('Location: admin.php'); exit;
    } catch (Exception $ex) { $err = $ex->getMessage(); }
}
function v($k, $d = '') { global $f; return htmlspecialchars($f[$k] ?? $d, ENT_QUOTES); }
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Setup</title><link rel="stylesheet" href="admin.css"></head><body class="admin"><main class="narrow">
<h1>Gallery setup</h1>
<?php if ($err) echo '<p class="err">' . htmlspecialchars($err) . '</p>'; ?>
<form method="post" class="stack">
<h2>Site</h2>
<label>Site name <input name="site_name" value="<?= v('site_name') ?>" placeholder="Your studio name"></label>
<label>Admin password (10+ characters) <input type="password" name="admin_pass"></label>
<h2>Database (Hostinger: Databases &rarr; MySQL)</h2>
<label>Host <input name="db_host" value="<?= v('db_host', 'localhost') ?>"></label>
<label>Database name <input name="db_name" value="<?= v('db_name') ?>"></label>
<label>Database user <input name="db_user" value="<?= v('db_user') ?>"></label>
<label>Database password <input type="password" name="db_pass"></label>
<h2>Cloudflare R2 storage</h2>
<label>Account ID <input name="r2_account" value="<?= v('r2_account') ?>"></label>
<label>Bucket name <input name="r2_bucket" value="<?= v('r2_bucket') ?>"></label>
<label>Access Key ID <input name="r2_key" value="<?= v('r2_key') ?>"></label>
<label>Secret Access Key <input type="password" name="r2_secret"></label>
<button>Install</button>
</form></main></body></html>
