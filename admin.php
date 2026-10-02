<?php
// Photographer's dashboard: login, collections list, and the collection workspace
// (Photos, Design, Settings, Activity).
require __DIR__ . '/lib.php';
$B = base();

if (isset($_GET['logout'])) { session_destroy(); redirect('admin.php'); }

function icon($n) {
    $p = [
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="M21 16l-5-5-9 9"/>',
        'brush' => '<path d="M20 4L9.5 14.5M4 20c0-2.5 1.2-4.5 3.2-4.5s2.8 1.2 2.8 2.6c0 2-2.5 2.9-6 1.9z"/>',
        'gear' => '<circle cx="12" cy="12" r="3.2"/><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M5.6 18.4l1.8-1.8M16.6 7.4l1.8-1.8"/>',
        'activity' => '<path d="M4 11a9 9 0 019 9M4 5a15 15 0 0115 15"/><circle cx="5" cy="19" r="1"/>',
        'grid' => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>',
        'type' => '<path d="M5 7V5h14v2M12 5v14M9 19h6"/>',
        'palette' => '<path d="M12 3a9 9 0 100 18c1.5 0 2-1 2-2s-.5-1.5 0-2.3 1.3-.7 2.5-.7H18a3 3 0 003-3c0-5-4-10-9-10z"/><circle cx="8" cy="11" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="16" cy="10" r="1"/>',
        'back' => '<path d="M15 5l-7 7 7 7"/>',
        'plus' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'lock' => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>',
        'wrench' => '<path d="M14.5 6.5a4 4 0 005 5L10 21l-3-3 9.5-9.5a4 4 0 01-2-2z"/>',
        'window' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 9h18"/>',
        'download' => '<path d="M12 4v11M7.5 11l4.5 4.5 4.5-4.5M5 20h14"/>',
        'heart' => '<path d="M12 20s-7-4.4-7-9.5A4 4 0 0112 8a4 4 0 017 2.5c0 5.1-7 9.5-7 9.5z"/>',
        'pencil' => '<path d="M4 20l1-4L16 5l3 3L8 19l-4 1z"/>',
        'trash' => '<path d="M5 7h14M10 7V5h4v2M7 7l1 13h8l1-13"/>',
        'eye' => '<path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="2.5"/>',
        'folder' => '<path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>',
        'link' => '<path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1"/>',
        'copy' => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 00-2-2H6a2 2 0 00-2 2v8a2 2 0 002 2h2"/>',
        'move' => '<path d="M4 12h12M12 7l5 5-5 5M20 5v14"/>',
        'wand' => '<path d="M5 19L16 8M14 6l4 4M18 3v3M16.5 4.5h3M8 4v2M7 5h2M19 14v2M18 15h2"/>',
        'target' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="2.5"/>',
        'desktop' => '<rect x="3" y="5" width="18" height="11" rx="1.5"/><path d="M8 20h8M12 16v4"/>',
        'phone' => '<rect x="7.5" y="3" width="9" height="18" rx="2"/><path d="M11 18h2"/>',
    ][$n];
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
function page_top($title, $class = '') { global $B; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title><?= e($title) ?></title>
<link rel="preconnect" href="https://fonts.bunny.net"><link rel="stylesheet" href="<?= e(FONT_LINK) ?>">
<link rel="stylesheet" href="<?= $B ?>/admin.css?v=5"></head><body class="admin <?= e($class) ?>"><div id="app">
<?php }
function page_end() { global $B; echo '</div><script src="' . $B . '/admin.js?v=6"></script></body></html>'; }
function sel($name, $opts, $cur, $attr = '') {
    $h = "<select name=\"$name\" $attr>";
    foreach ($opts as $v => $label) $h .= '<option value="' . e($v) . '"' . ((string)$v === (string)$cur ? ' selected' : '') . '>' . e($label) . '</option>';
    return $h . '</select>';
}
function hidden($do, $cid = 0) {
    return '<input type="hidden" name="csrf" value="' . csrf() . '"><input type="hidden" name="do" value="' . e($do) . '">' . ($cid ? '<input type="hidden" name="cid" value="' . (int)$cid . '">' : '');
}
function unique_slug($name, $except = 0) {
    $base = slugify($name); $slug = $base; $i = 2;
    while (q('SELECT id FROM collections WHERE slug=? AND id<>?', [$slug, $except])->fetch()) $slug = $base . '-' . $i++;
    return $slug;
}
function nice_date($d) { return $d ? date('M j, Y', strtotime($d)) : ''; }

// ---------- Login ----------
if (!is_admin()) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        if (password_verify($_POST['password'] ?? '', $CFG['admin_hash'])) {
            session_regenerate_id(true); $_SESSION['admin'] = 1; redirect('admin.php');
        }
        sleep(2); $err = 'Wrong password.';
    }
    page_top('Log in', 'login'); ?>
    <main class="narrow"><p class="brand"><?= e($CFG['site_name']) ?></p><h1>Log in</h1><?php if ($err) echo '<p class="err">' . e($err) . '</p>'; ?>
    <form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrf() ?>">
    <label>Password <input type="password" name="password" autofocus></label><button class="btn primary">Log in</button></form></main>
    <?php page_end(); exit;
}

// ---------- Actions ----------
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $cid = (int)($_POST['cid'] ?? 0);
    switch ($_POST['do'] ?? '') {
        case 'new_collection':
            $name = trim($_POST['name']);
            if ($name === '') redirect('admin.php');
            q('INSERT INTO collections (name, slug, folder_id, event_date, cover_style, font) VALUES (?,?,?,?,?,?)',
              [$name, unique_slug($name), ((int)$_POST['folder_id']) ?: null, $_POST['event_date'] ?: null, 'center', 'sans']);
            $cid = db()->lastInsertId();
            q("INSERT INTO sets (collection_id, name, position) VALUES (?, 'Highlights', 1)", [$cid]);
            redirect("admin.php?c=$cid");
        case 'new_folder':
            if (trim($_POST['name']) !== '') q('INSERT INTO folders (name, share_token) VALUES (?,?)', [trim($_POST['name']), bin2hex(random_bytes(12))]);
            redirect('admin.php');
        case 'delete_folder':
            q('UPDATE collections SET folder_id=NULL WHERE folder_id=?', [(int)$_POST['fid']]);
            q('DELETE FROM folders WHERE id=?', [(int)$_POST['fid']]);
            redirect('admin.php');
        case 'save_general':
            $tags = implode(',', array_unique(array_filter(array_map(function ($t) { return trim(strtolower($t)); }, explode(',', $_POST['tags'])))));
            q('UPDATE collections SET name=?, slug=?, event_date=?, folder_id=?, tags=?, expires_at=?, show_filenames=? WHERE id=?',
              [trim($_POST['name']) ?: 'Untitled', unique_slug($_POST['slug'] ?: $_POST['name'], $cid), $_POST['event_date'] ?: null,
               ((int)$_POST['folder_id']) ?: null, $tags, $_POST['expires_at'] ?: null, isset($_POST['show_filenames']) ? 1 : 0, $cid]);
            redirect("admin.php?c=$cid&tab=settings&saved=1");
        case 'save_privacy':
            q('UPDATE collections SET password=?, hide_home=? WHERE id=?', [trim($_POST['password']), isset($_POST['show_home']) ? 0 : 1, $cid]);
            redirect("admin.php?c=$cid&tab=settings&sub=privacy&saved=1");
        case 'save_download':
            q('UPDATE collections SET allow_download=? WHERE id=?', [isset($_POST['on']) ? 1 : 0, $cid]);
            redirect("admin.php?c=$cid&tab=settings&sub=download&saved=1");
        case 'save_favorite':
            q('UPDATE collections SET allow_favorite=? WHERE id=?', [isset($_POST['on']) ? 1 : 0, $cid]);
            redirect("admin.php?c=$cid&tab=settings&sub=favorite&saved=1");
        case 'move_folder':
            q('UPDATE collections SET folder_id=? WHERE id=?', [((int)$_POST['folder_id']) ?: null, $cid]);
            redirect("admin.php?c=$cid");
        case 'duplicate': // copies settings, sets and photos; the photo files themselves are shared, not copied
            $src = q('SELECT * FROM collections WHERE id=?', [$cid])->fetch();
            if (!$src) redirect('admin.php');
            $keep = ['folder_id', 'event_date', 'password', 'hide_home', 'expires_at', 'cover_style', 'grid_style', 'grid_size', 'grid_gap', 'font', 'palette',
                     'show_filenames', 'sort_mode', 'tags', 'allow_download', 'allow_favorite', 'focal_x', 'focal_y'];
            $vals = ['Copy of ' . $src['name'], unique_slug('Copy of ' . $src['name'])];
            foreach ($keep as $k) $vals[] = $src[$k];
            q('INSERT INTO collections (name, slug, ' . implode(', ', $keep) . ') VALUES (?,?' . str_repeat(',?', count($keep)) . ')', $vals);
            $new = (int)db()->lastInsertId();
            foreach (q('SELECT * FROM sets WHERE collection_id=? ORDER BY position, id', [$cid])->fetchAll() as $set) {
                q('INSERT INTO sets (collection_id, name, position) VALUES (?,?,?)', [$new, $set['name'], $set['position']]);
                $sid = (int)db()->lastInsertId();
                foreach (q('SELECT * FROM photos WHERE set_id=?', [$set['id']])->fetchAll() as $ph) {
                    q('INSERT INTO photos (collection_id,set_id,filename,key_orig,key_web,key_thumb,width,height,size,taken_at,position) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                      [$new, $sid, $ph['filename'], $ph['key_orig'], $ph['key_web'], $ph['key_thumb'], $ph['width'], $ph['height'], $ph['size'], $ph['taken_at'], $ph['position']]);
                    if ($ph['id'] == $src['cover_photo_id']) q('UPDATE collections SET cover_photo_id=? WHERE id=?', [db()->lastInsertId(), $new]);
                }
            }
            redirect("admin.php?c=$new");
        case 'save_preset':
            $src = q('SELECT cover_style, font, palette, grid_style, grid_size, grid_gap FROM collections WHERE id=?', [$cid])->fetch();
            if ($src && trim($_POST['name']) !== '') q('INSERT INTO presets (name, data) VALUES (?,?)', [substr(trim($_POST['name']), 0, 120), json_encode($src)]);
            redirect("admin.php?c=$cid&tab=design");
        case 'apply_preset':
            $d = json_decode((string)q('SELECT data FROM presets WHERE id=?', [(int)$_POST['pid']])->fetchColumn(), true);
            if ($d) q('UPDATE collections SET cover_style=?, font=?, palette=?, grid_style=?, grid_size=?, grid_gap=? WHERE id=?',
                      [$d['cover_style'], $d['font'], $d['palette'], $d['grid_style'], $d['grid_size'], $d['grid_gap'], $cid]);
            redirect("admin.php?c=$cid&tab=design");
        case 'delete_preset':
            q('DELETE FROM presets WHERE id=?', [(int)$_POST['pid']]);
            redirect("admin.php?c=$cid&tab=design");
        case 'delete_collection':
            foreach (q('SELECT * FROM photos WHERE collection_id=?', [$cid]) as $p) delete_photo_files($p);
            q('DELETE FROM photos WHERE collection_id=?', [$cid]);
            q('DELETE FROM sets WHERE collection_id=?', [$cid]);
            q('DELETE FROM collections WHERE id=?', [$cid]);
            redirect('admin.php');
        case 'new_set':
            if (trim($_POST['name']) !== '') {
                $pos = (int)q('SELECT COALESCE(MAX(position),0)+1 FROM sets WHERE collection_id=?', [$cid])->fetchColumn();
                q('INSERT INTO sets (collection_id, name, position) VALUES (?,?,?)', [$cid, trim($_POST['name']), $pos]);
                redirect("admin.php?c=$cid&set=" . db()->lastInsertId());
            }
            redirect("admin.php?c=$cid");
        case 'rename_set':
            if (trim($_POST['name']) !== '') q('UPDATE sets SET name=? WHERE id=? AND collection_id=?', [trim($_POST['name']), (int)$_POST['sid'], $cid]);
            redirect("admin.php?c=$cid&set=" . (int)$_POST['sid']);
        case 'delete_set':
            $other = q('SELECT id FROM sets WHERE collection_id=? AND id<>? ORDER BY position LIMIT 1', [$cid, (int)$_POST['sid']])->fetchColumn();
            if ($other) { // photos move to the first remaining set instead of being deleted
                q('UPDATE photos SET set_id=? WHERE set_id=? AND collection_id=?', [$other, (int)$_POST['sid'], $cid]);
                q('DELETE FROM sets WHERE id=? AND collection_id=?', [(int)$_POST['sid'], $cid]);
            }
            redirect("admin.php?c=$cid");
        case 'save_notify':
            $mail = trim($_POST['email']);
            if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) { $msg = 'That email address does not look right.'; break; }
            meta_set('notify_email', $mail); meta_set('notify_uploads', isset($_POST['uploads']) ? '1' : '0');
            $msg = 'Notification settings saved.';
            if (isset($_POST['test'])) $msg = send_mail('Test email from your gallery', "This is a test. Email notifications from your gallery are working.\n")
                ? "Test email sent to $mail. If it does not arrive within a few minutes, check the spam folder."
                : 'The test email could not be sent. Check the address, or the hosting mail service.';
            break;
        case 'change_password':
            if (!password_verify($_POST['current'], $CFG['admin_hash'])) $msg = 'Current password is wrong.';
            elseif (strlen($_POST['new']) < 10) $msg = 'New password needs at least 10 characters.';
            else {
                $CFG['admin_hash'] = password_hash($_POST['new'], PASSWORD_DEFAULT);
                file_put_contents(__DIR__ . '/config.php', "<?php\nreturn " . var_export($CFG, true) . ";\n");
                $msg = 'Password changed.';
            }
            break;
    }
}

$folders = q('SELECT * FROM folders ORDER BY name')->fetchAll();
$folderOpts = [0 => 'No folder']; foreach ($folders as $f) $folderOpts[$f['id']] = $f['name'];
$site = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . '://' . $_SERVER['HTTP_HOST'] . $B;
$usage = q('SELECT COUNT(*) n, COALESCE(SUM(size),0) b FROM photos')->fetch();

function sidebar($active) { global $B, $CFG, $usage; ?>
<aside class="side"><a class="brand" href="<?= $B ?>/admin.php"><?= e($CFG['site_name']) ?></a>
<nav><a class="<?= $active === 'collections' ? 'on' : '' ?>" href="<?= $B ?>/admin.php"><?= icon('image') ?>Collections</a>
<a href="<?= $B ?>/" target="_blank"><?= icon('window') ?>Homepage</a>
<a class="<?= $active === 'account' ? 'on' : '' ?>" href="<?= $B ?>/admin.php?account=1"><?= icon('gear') ?>Settings</a></nav>
<p class="usage"><?= number_format($usage['b'] / 1073741824, 2) ?> GB used &middot; <?= (int)$usage['n'] ?> photos</p>
<a class="logout" href="<?= $B ?>/admin.php?logout=1">Log out</a></aside>
<?php }

// ---------- Account ----------
if (isset($_GET['account'])) {
    page_top('Settings'); ?>
    <div class="shell"><?php sidebar('account'); ?><main class="content"><h1>Settings</h1>
    <?php if ($msg) echo '<p class="note">' . e($msg) . '</p>'; ?>
    <section class="panel"><h2>Storage</h2><p><?= (int)$usage['n'] ?> photos, <?= number_format($usage['b'] / 1073741824, 2) ?> GB of originals stored.</p></section>
    <section class="panel"><h2>Email notifications</h2>
    <form method="post" class="stack"><?= hidden('save_notify') ?>
    <label>Send notifications to <input type="email" name="email" value="<?= e(meta_get('notify_email')) ?>" placeholder="you@example.com"></label>
    <label class="toggle"><input type="checkbox" name="uploads" <?= meta_get('notify_uploads') === '1' ? 'checked' : '' ?>><i></i><span><b>Upload finished</b><small>Email me when a batch of photos has finished uploading.</small></span></label>
    <div class="row"><button class="btn primary">Save</button><button class="btn" name="test" value="1">Save and send test email</button></div></form></section>
    <section class="panel"><h2>Change password</h2>
    <form method="post" class="stack"><?= hidden('change_password') ?>
    <label>Current password <input type="password" name="current"></label>
    <label>New password <input type="password" name="new"></label><button class="btn primary">Change password</button></form></section>
    </main></div>
    <?php page_end(); exit;
}

// ---------- One collection ----------
if (isset($_GET['c'])) {
    $c = q('SELECT * FROM collections WHERE id=?', [(int)$_GET['c']])->fetch();
    if (!$c) redirect('admin.php');
    $cid = (int)$c['id'];
    $tab = in_array($_GET['tab'] ?? '', ['design', 'settings', 'activity']) ? $_GET['tab'] : 'photos';
    $sub = $_GET['sub'] ?? '';
    $cover = cover_photo($c);
    $presets = q('SELECT * FROM presets ORDER BY name')->fetchAll();
    $link = "$site/g/{$c['slug']}";
    $url = function ($tab, $sub = '') use ($B, $cid) { return "$B/admin.php?c=$cid" . ($tab !== 'photos' ? "&tab=$tab" : '') . ($sub ? "&sub=$sub" : ''); };
    page_top($c['name'], 'workspace'); ?>
<header class="cbar">
  <a class="backbtn" href="<?= $B ?>/admin.php" aria-label="All collections"><?= icon('back') ?></a>
  <div class="cname"><strong><?= e($c['name']) ?></strong><span><?= e(nice_date($c['event_date'])) ?></span></div>
  <?= sel('status', ['published' => 'Published', 'draft' => 'Draft'], $c['status'], 'id="status" class="statussel ' . e($c['status']) . '" aria-label="Status"') ?>
  <form class="search" action="<?= $B ?>/admin.php"><input type="hidden" name="c" value="<?= $cid ?>"><?= icon('search') ?><input name="find" value="<?= e($_GET['find'] ?? '') ?>" placeholder="Search filenames"></form>
  <span class="grow"></span>
  <details class="menu"><summary class="btn ghost">More</summary><div>
    <button type="button" onclick="navigator.clipboard.writeText(<?= e(json_encode($link)) ?>);this.lastChild.textContent='Link copied'"><?= icon('link') ?><span>Get direct link</span></button>
    <button type="button" onclick="document.getElementById('presetdlg').showModal()"><?= icon('gear') ?><span>Manage presets</span></button>
    <button type="button" onclick="document.getElementById('movedlg').showModal()"><?= icon('move') ?><span>Move to</span></button>
    <form method="post" onsubmit="return confirm('Duplicate this collection? The copy starts as a draft.')"><?= hidden('duplicate', $cid) ?><button><?= icon('copy') ?><span>Duplicate</span></button></form>
    <form method="post" onsubmit="return confirm('Delete this collection and all its photos? This cannot be undone.')"><?= hidden('delete_collection', $cid) ?><button><?= icon('trash') ?><span>Delete collection</span></button></form>
    <hr><form method="post" onsubmit="const n = prompt('Name for this style preset (saves cover, typography, color and grid)'); if (!n) return false; this.elements.name.value = n;"><?= hidden('save_preset', $cid) ?><input type="hidden" name="name"><button><?= icon('wand') ?><span>Create Style</span></button></form>
  </div></details>
  <a class="btn ghost" href="<?= $B ?>/g/<?= e($c['slug']) ?>?preview=1" target="_blank">Preview</a>
  <button class="btn primary" type="button" onclick="document.getElementById('share').showModal()">Share</button>
</header>
<dialog id="share"><h2>Share this gallery</h2>
  <?php if ($c['status'] !== 'published') echo '<p class="err">This collection is a draft. Publish it before sharing, or clients will not be able to open it.</p>'; ?>
  <label>Link <input id="sharelink" readonly value="<?= e($link) ?>"></label>
  <?php if ($c['password'] !== '') { ?><label>Password <input readonly value="<?= e($c['password']) ?>"></label><?php } ?>
  <div class="row end"><button class="btn" type="button" onclick="this.closest('dialog').close()">Close</button>
  <button class="btn primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('sharelink').value);this.textContent='Copied'">Copy link</button></div></dialog>

<dialog id="movedlg"><h2>Move to folder</h2><form method="post" class="stack"><?= hidden('move_folder', $cid) ?>
  <label>Folder <?= sel('folder_id', $folderOpts, (int)$c['folder_id']) ?></label>
  <div class="row end"><button class="btn" type="button" onclick="this.closest('dialog').close()">Cancel</button><button class="btn primary">Move</button></div></form></dialog>
<dialog id="presetdlg"><h2>Style presets</h2>
  <?php if (!$presets) echo '<p class="muted">No presets yet. Set up the design you like, then choose More &rarr; Create Style to save it for reuse.</p>'; ?>
  <?php foreach ($presets as $pr) { ?><div class="presetrow"><strong><?= e($pr['name']) ?></strong><span class="grow"></span>
    <form method="post"><?= hidden('apply_preset', $cid) ?><input type="hidden" name="pid" value="<?= $pr['id'] ?>"><button class="btn primary">Apply</button></form>
    <form method="post" onsubmit="return confirm('Delete this preset?')"><?= hidden('delete_preset', $cid) ?><input type="hidden" name="pid" value="<?= $pr['id'] ?>"><button class="btn danger">Delete</button></form></div><?php } ?>
  <div class="row end"><button class="btn" type="button" onclick="this.closest('dialog').close()">Close</button></div></dialog>

<div class="cwrap">
<aside class="cside">
  <div class="coverthumb"><?php if ($cover) { ?><img src="<?= e(r2_url('GET', $cover['key_web'])) ?>" alt="" style="object-position:<?= (int)$c['focal_x'] ?>% <?= (int)$c['focal_y'] ?>%"><?php } ?></div>
  <nav class="tabs">
    <a class="<?= $tab === 'photos' ? 'on' : '' ?>" href="<?= $url('photos') ?>" title="Photos"><?= icon('image') ?></a>
    <a class="<?= $tab === 'design' ? 'on' : '' ?>" href="<?= $url('design') ?>" title="Design"><?= icon('brush') ?></a>
    <a class="<?= $tab === 'settings' ? 'on' : '' ?>" href="<?= $url('settings') ?>" title="Settings"><?= icon('gear') ?></a>
    <a class="<?= $tab === 'activity' ? 'on' : '' ?>" href="<?= $url('activity') ?>" title="Activity"><?= icon('activity') ?></a>
  </nav>
<?php
    // ===== Photos =====
    if ($tab === 'photos') {
        $sets = q('SELECT s.*, (SELECT COUNT(*) FROM photos p WHERE p.set_id=s.id) n FROM sets s WHERE collection_id=? ORDER BY position, id', [$cid])->fetchAll();
        $cur = $sets[0];
        foreach ($sets as $s) if ($s['id'] == (int)($_GET['set'] ?? 0)) $cur = $s;
        $find = trim($_GET['find'] ?? '');
        $photos = $find !== ''
            ? q('SELECT * FROM photos WHERE collection_id=? AND filename LIKE ? ORDER BY filename', [$cid, "%$find%"])->fetchAll()
            : q('SELECT * FROM photos WHERE set_id=? ORDER BY ' . photo_order($c['sort_mode']), [$cur['id']])->fetchAll();
        $maxpos = (int)q('SELECT COALESCE(MAX(position),0) FROM photos WHERE set_id=?', [$cur['id']])->fetchColumn();
        $setOpts = []; foreach ($sets as $s) $setOpts[$s['id']] = $s['name']; ?>
  <div class="sidehead"><span>Photos</span><button type="button" class="link" id="addset"><?= icon('plus') ?>Add Set</button></div>
  <ul class="sidelist"><?php foreach ($sets as $s) { $on = !$find && $s['id'] == $cur['id']; ?>
    <li class="<?= $on ? 'on' : '' ?>"><a href="<?= $B ?>/admin.php?c=<?= $cid ?>&set=<?= $s['id'] ?>"><span><?= e($s['name']) ?> (<span data-count="<?= $s['id'] ?>"><?= $s['n'] ?></span>)</span></a>
    <?php if ($on) { ?><button type="button" class="mini" id="renset" title="Rename set"><?= icon('pencil') ?></button><?php if (count($sets) > 1) { ?><button type="button" class="mini" id="delset" title="Delete set"><?= icon('trash') ?></button><?php } } ?></li>
  <?php } ?></ul>
</aside>
<main class="cmain" id="drop">
  <div class="mainhead"><h1><?= $find !== '' ? 'Results for "' . e($find) . '"' : e($cur['name']) ?></h1><span class="grow"></span>
    <?php if ($find !== '') { ?><a class="link" href="<?= $url('photos') ?>">Clear search</a><?php } else { ?>
    <?= sel('sort_mode', ['manual' => 'Sort by: Manual', 'up_desc' => 'Uploaded: New → Old', 'up_asc' => 'Uploaded: Old → New', 'date_desc' => 'Date Taken: New → Old', 'date_asc' => 'Date Taken: Old → New', 'name_asc' => 'Name: A-Z', 'name_desc' => 'Name: Z-A', 'random' => 'Random'], $c['sort_mode'], 'id="sort" class="plain" aria-label="Photo order"') ?>
    <label class="link addmedia"><?= icon('plus') ?>Add Media<input type="file" id="files" accept="image/jpeg,image/png,image/webp" multiple hidden></label><?php } ?>
  </div>
  <div class="selbar" id="selbar" hidden><strong id="selcount"></strong>
    <button type="button" data-act="cover">Set as cover</button>
    <?php if (count($sets) > 1) { echo sel('moveto', $setOpts, $cur['id'], 'class="plain"'); ?><button type="button" data-act="move">Move to set</button><?php } ?>
    <button type="button" data-act="delete" class="danger">Delete</button><span class="grow"></span>
    <button type="button" id="selall">Select all</button><button type="button" id="selnone">Clear</button></div>
  <div id="grid" class="tiles"><?php foreach ($photos as $p) { ?>
    <label class="tile<?= $p['id'] == $c['cover_photo_id'] ? ' iscover' : '' ?>" draggable="<?= $find === '' ? 'true' : 'false' ?>" data-id="<?= $p['id'] ?>" data-pos="<?= (int)$p['position'] ?>" title="<?= e($p['filename']) ?>">
      <input type="checkbox" value="<?= $p['id'] ?>"><img loading="lazy" src="<?= e(r2_url('GET', $p['key_thumb'])) ?>" alt=""></label>
  <?php } ?></div>
  <?php if (!$photos) { ?><div class="emptystate"><?= icon('image') ?><p><?= $find !== '' ? 'No photos match that search.' : 'No photos in this set yet.' ?></p>
    <?php if ($find === '') echo '<p class="muted">Press Add Media, or drag photos from your computer onto this page.</p>'; ?></div><?php } ?>
  <form method="post" id="act" hidden><?= hidden('', $cid) ?><input name="sid" value="<?= $cur['id'] ?>"><input name="name"></form>
</main>
<script>window.G = <?= json_encode(['api' => "$B/api.php", 'cid' => $cid, 'set' => (int)$cur['id'], 'csrf' => csrf(), 'maxpos' => $maxpos, 'setName' => $cur['name'], 'sort' => $c['sort_mode'], 'find' => $find]) ?>;</script>
<script>window.initPhotos ? initPhotos() : addEventListener('load', () => initPhotos());</script>
<?php
    // ===== Design =====
    } elseif ($tab === 'design') {
        $sub = in_array($sub, ['typography', 'color', 'grid']) ? $sub : 'cover';
        $opt = function ($field, $value, $inner, $label) use ($c) {
            return '<button type="button" class="opt' . ($c[$field] === $value ? ' on' : '') . '" data-field="' . $field . '" data-value="' . e($value) . '"><span class="optbox">' . $inner . '</span><span class="optlabel">' . e($label) . '</span></button>';
        }; ?>
  <div class="sidehead"><span>Design</span></div>
  <ul class="sidelist nav">
    <li class="<?= $sub === 'cover' ? 'on' : '' ?>"><a href="<?= $url('design', 'cover') ?>"><?= icon('image') ?>Cover</a></li>
    <li class="<?= $sub === 'typography' ? 'on' : '' ?>"><a href="<?= $url('design', 'typography') ?>"><?= icon('type') ?>Typography</a></li>
    <li class="<?= $sub === 'color' ? 'on' : '' ?>"><a href="<?= $url('design', 'color') ?>"><?= icon('palette') ?>Color</a></li>
    <li class="<?= $sub === 'grid' ? 'on' : '' ?>"><a href="<?= $url('design', 'grid') ?>"><?= icon('grid') ?>Grid</a></li>
  </ul>
</aside>
<main class="cmain design">
  <section class="pick">
  <?php if ($sub === 'cover') { ?>
    <div class="mainhead"><h1>Cover</h1><span class="grow"></span><a class="link" href="<?= $url('photos') ?>"><?= icon('image') ?>Cover Photo</a>
      <?php if ($cover) { ?><button type="button" class="link" onclick="document.getElementById('focaldlg').showModal()"><?= icon('target') ?>Focal</button><?php } ?></div>
    <div class="opts"><?php foreach (covers() as $k => $label) echo $opt('cover_style', $k, '<span class="mock m-' . $k . '"><i></i><b>TITLE</b></span>', $label); ?></div>
    <p class="muted">To change the cover photo, open Photos, select one photo and press "Set as cover".</p>
  <?php } elseif ($sub === 'typography') { ?>
    <div class="mainhead"><h1>Typography</h1></div>
    <div class="opts"><?php foreach (typefaces() as $k => $t) echo $opt('font', $k,
        '<span class="sample"><b style="font-family:' . e($t[2]) . ';font-weight:' . $t[3] . ';text-transform:' . $t[4] . ';letter-spacing:' . $t[5] . '">' . e($t[0]) . '</b><small style="font-family:' . e($t[6]) . '">' . e($t[1]) . '</small></span>', $t[0]); ?></div>
  <?php } elseif ($sub === 'color') { ?>
    <div class="mainhead"><h1>Color</h1></div>
    <div class="opts"><?php foreach (palettes() as $k => $p) echo $opt('palette', $k,
        '<span class="dots"><i style="background:' . $p[0] . '"></i><i style="background:' . $p[1] . '"></i><i style="background:' . $p[2] . '"></i></span>', ucfirst($k)); ?></div>
  <?php } else { ?>
    <div class="mainhead"><h1>Grid</h1></div>
    <h3>Grid Style</h3><div class="opts"><?= $opt('grid_style', 'masonry', '<span class="gi g-v"><i></i><i></i><i></i><i></i></span>', 'Vertical') . $opt('grid_style', 'rows', '<span class="gi g-h"><i></i><i></i><i></i><i></i></span>', 'Horizontal') ?></div>
    <h3>Thumbnail Size</h3><div class="opts"><?= $opt('grid_size', 'medium', '<span class="gi g-6"><i></i><i></i><i></i><i></i><i></i><i></i></span>', 'Regular') . $opt('grid_size', 'large', '<span class="gi g-3"><i></i><i></i><i></i></span>', 'Large') ?></div>
    <h3>Grid Spacing</h3><div class="opts"><?= $opt('grid_gap', 'small', '<span class="gi g-4"><i></i><i></i><i></i><i></i></span>', 'Regular') . $opt('grid_gap', 'large', '<span class="gi g-4 wide"><i></i><i></i><i></i><i></i></span>', 'Large') ?></div>
  <?php } ?>
  </section>
  <?php if ($cover) { ?><dialog id="focaldlg" class="wide"><h2>Focal point</h2><p class="muted">Click the most important part of the photo. The cover keeps that spot in view on every screen size.</p>
    <div class="focal" id="focal"><img src="<?= e(r2_url('GET', $cover['key_web'])) ?>" alt=""><i id="focaldot" style="left:<?= (int)$c['focal_x'] ?>%;top:<?= (int)$c['focal_y'] ?>%"></i></div>
    <div class="row end"><button class="btn" type="button" onclick="this.closest('dialog').close()">Cancel</button><button class="btn primary" type="button" id="focalsave">Save</button></div></dialog><?php } ?>
  <section class="preview"><div class="frame" id="frame"><iframe id="pv" src="<?= $B ?>/g.php?s=<?= e($c['slug']) ?>&embed=1" title="Gallery preview"></iframe></div>
    <div class="devices"><button type="button" class="on" data-w="desktop" title="Desktop"><?= icon('desktop') ?></button><button type="button" data-w="phone" title="Phone"><?= icon('phone') ?></button></div></section>
</main>
<script>{
const API = <?= json_encode("$B/api.php") ?>, CID = <?= $cid ?>, CSRF = <?= json_encode(csrf()) ?>;
document.querySelectorAll('.opt').forEach(b => b.onclick = async () => {
  const fd = new FormData(); fd.append('csrf', CSRF); fd.append('cid', CID); fd.append('field', b.dataset.field); fd.append('value', b.dataset.value);
  const r = await (await fetch(API + '?a=set', {method: 'POST', body: fd})).json();
  if (r.error) return alert(r.error);
  document.querySelectorAll('.opt[data-field="' + b.dataset.field + '"]').forEach(o => o.classList.toggle('on', o === b));
  document.getElementById('pv').contentWindow.location.reload();
});
const focal = document.getElementById('focal');
if (focal) {
  let fx = <?= (int)$c['focal_x'] ?>, fy = <?= (int)$c['focal_y'] ?>;
  focal.onclick = ev => {
    const r = focal.getBoundingClientRect();
    fx = Math.round((ev.clientX - r.left) / r.width * 100); fy = Math.round((ev.clientY - r.top) / r.height * 100);
    Object.assign(document.getElementById('focaldot').style, {left: fx + '%', top: fy + '%'});
  };
  document.getElementById('focalsave').onclick = async () => {
    const fd = new FormData(); fd.append('csrf', CSRF); fd.append('cid', CID); fd.append('x', fx); fd.append('y', fy);
    await fetch(API + '?a=focal', {method: 'POST', body: fd});
    document.getElementById('focaldlg').close(); document.getElementById('pv').contentWindow.location.reload();
  };
}
document.querySelectorAll('.devices button').forEach(b => b.onclick = () => {
  document.querySelectorAll('.devices button').forEach(o => o.classList.toggle('on', o === b));
  document.getElementById('frame').classList.toggle('phone', b.dataset.w === 'phone');
});
}</script>
<?php
    // ===== Settings =====
    } elseif ($tab === 'settings') {
        $sub = in_array($sub, ['privacy', 'download', 'favorite']) ? $sub : 'general';
        $pill = function ($on) { return '<em class="' . ($on ? 'yes' : '') . '">' . ($on ? 'On' : 'Off') . '</em>'; }; ?>
  <div class="sidehead"><span>Settings</span></div>
  <ul class="sidelist nav">
    <li class="<?= $sub === 'general' ? 'on' : '' ?>"><a href="<?= $url('settings') ?>"><?= icon('wrench') ?>General</a></li>
    <li class="<?= $sub === 'privacy' ? 'on' : '' ?>"><a href="<?= $url('settings', 'privacy') ?>"><?= icon('lock') ?>Privacy</a></li>
    <li class="<?= $sub === 'download' ? 'on' : '' ?>"><a href="<?= $url('settings', 'download') ?>"><?= icon('download') ?>Download</a><?= $pill($c['allow_download']) ?></li>
    <li class="<?= $sub === 'favorite' ? 'on' : '' ?>"><a href="<?= $url('settings', 'favorite') ?>"><?= icon('heart') ?>Favorite</a><?= $pill($c['allow_favorite']) ?></li>
  </ul>
</aside>
<main class="cmain form">
  <?php if (isset($_GET['saved'])) echo '<p class="note">Settings saved.</p>'; ?>
  <?php if ($sub === 'general') { ?>
  <h1>General Settings</h1>
  <form method="post" class="fields"><?= hidden('save_general', $cid) ?>
    <label><b>Collection Name</b><input name="name" value="<?= e($c['name']) ?>"></label>
    <label><b>Event Date</b><input type="date" name="event_date" value="<?= e($c['event_date']) ?>"></label>
    <label><b>Collection URL</b><input name="slug" value="<?= e($c['slug']) ?>"><small>Clients open this collection at <?= e("$site/g/") ?><u><?= e($c['slug']) ?></u></small></label>
    <label><b>Category Tags</b><input name="tags" value="<?= e(str_replace(',', ', ', $c['tags'])) ?>" placeholder="wedding, outdoor, summer"><small>Separate tags with commas. Tags help you filter your collections.</small></label>
    <label><b>Folder</b><?= sel('folder_id', $folderOpts, (int)$c['folder_id']) ?></label>
    <label><b>Auto Expiry</b><input type="date" name="expires_at" value="<?= e($c['expires_at']) ?>"><small>Optional. The collection is hidden from clients after this date.</small></label>
    <label class="toggle"><input type="checkbox" name="show_filenames" <?= $c['show_filenames'] ? 'checked' : '' ?>><i></i><span><b>Show Filenames</b><small>Display each photo's filename in the gallery.</small></span></label>
    <button class="btn primary">Save</button>
  </form>
  <form method="post" class="dangerzone" onsubmit="return confirm('Delete this collection and all its photos? This cannot be undone.')"><?= hidden('delete_collection', $cid) ?>
    <b>Delete Collection</b><small>Removes the collection and every photo in it, permanently.</small><button class="btn danger">Delete collection</button></form>
  <?php } elseif ($sub === 'download') { ?>
  <h1>Download Settings</h1>
  <form method="post" class="fields"><?= hidden('save_download', $cid) ?>
    <label class="toggle"><input type="checkbox" name="on" <?= $c['allow_download'] ? 'checked' : '' ?>><i></i><span><b>Photo Download</b><small>Let clients download single photos in original resolution from the full-screen view.</small></span></label>
    <button class="btn primary">Save</button></form>
  <?php } elseif ($sub === 'favorite') { ?>
  <h1>Favorite Settings</h1>
  <form method="post" class="fields"><?= hidden('save_favorite', $cid) ?>
    <label class="toggle"><input type="checkbox" name="on" <?= $c['allow_favorite'] ? 'checked' : '' ?>><i></i><span><b>Favorite Photos</b><small>Let clients mark favorites. They enter their name the first time, so you can see whose selection it is.</small></span></label>
    <button class="btn primary">Save</button></form>
  <?php } else { ?>
  <h1>Privacy Settings</h1>
  <form method="post" class="fields"><?= hidden('save_privacy', $cid) ?>
    <label><b>Collection Password</b><input name="password" value="<?= e($c['password']) ?>" autocomplete="off" placeholder="No password"><small>Leave empty to let anyone with the link view the collection.</small></label>
    <label class="toggle"><input type="checkbox" name="show_home" <?= $c['hide_home'] ? '' : 'checked' ?>><i></i><span><b>Show on Homepage</b><small>List this collection on your public homepage.</small></span></label>
    <button class="btn primary">Save</button>
  </form>
  <?php } ?>
</main>
<?php
    // ===== Activity =====
    } else {
        $sub = in_array($sub, ['favorites', 'comments']) ? $sub : 'downloads'; ?>
  <div class="sidehead"><span>Activities</span></div>
  <ul class="sidelist nav">
    <li class="<?= $sub === 'downloads' ? 'on' : '' ?>"><a href="<?= $url('activity') ?>"><?= icon('download') ?>Download Activity</a></li>
    <li class="<?= $sub === 'favorites' ? 'on' : '' ?>"><a href="<?= $url('activity', 'favorites') ?>"><?= icon('heart') ?>Favorite Activity</a></li>
    <li class="<?= $sub === 'comments' ? 'on' : '' ?>"><a href="<?= $url('activity', 'comments') ?>"><?= icon('pencil') ?>Comments</a></li></ul>
</aside>
<main class="cmain">
  <?php if ($sub === 'downloads') {
        $rows = q('SELECT d.client, d.created_at, p.filename, p.key_thumb FROM downloads d JOIN photos p ON p.id=d.photo_id WHERE d.collection_id=? ORDER BY d.id DESC LIMIT 500', [$cid])->fetchAll(); ?>
  <div class="mainhead"><h1>Download Activity</h1></div>
  <?php if ($rows) { ?><table class="list"><thead><tr><th>Photo</th><th>Client</th><th>Downloaded</th></tr></thead><tbody>
    <?php foreach ($rows as $r) { ?><tr class="still"><td><span class="colname"><span class="sq"><img loading="lazy" src="<?= e(r2_url('GET', $r['key_thumb'])) ?>" alt=""></span><span><?= e($r['filename']) ?></span></span></td>
    <td><?= e($r['client']) ?></td><td><?= e(date('M j, Y H:i', strtotime($r['created_at']))) ?></td></tr><?php } ?></tbody></table>
  <?php } else { ?><div class="emptystate"><?= icon('download') ?><p>No downloads yet.</p><p class="muted">Each photo a client downloads is listed here.</p></div><?php } ?>

  <?php } elseif ($sub === 'favorites') {
        $rows = q('SELECT f.client, p.filename, p.key_thumb FROM favorites f JOIN photos p ON p.id=f.photo_id WHERE f.collection_id=? ORDER BY f.client, p.filename', [$cid])->fetchAll();
        $by = []; foreach ($rows as $r) $by[$r['client']][] = $r; ?>
  <div class="mainhead"><h1>Favorite Activity</h1></div>
  <?php foreach ($by as $client => $list) { $names = implode(', ', array_column($list, 'filename')); ?>
  <section class="favlist"><div class="mainhead"><h2><?= e($client) ?> <small><?= count($list) ?> photos</small></h2><span class="grow"></span>
    <button type="button" class="btn" onclick="navigator.clipboard.writeText(this.dataset.names);this.textContent='Copied'" data-names="<?= e($names) ?>">Copy filenames</button></div>
    <div class="tiles small"><?php foreach ($list as $r) { ?><span class="tile" title="<?= e($r['filename']) ?>"><img loading="lazy" src="<?= e(r2_url('GET', $r['key_thumb'])) ?>" alt=""></span><?php } ?></div></section>
  <?php } if (!$by) { ?><div class="emptystate"><?= icon('heart') ?><p>No favorites yet.</p><p class="muted">When clients mark favorites, their selections appear here, grouped by name.</p></div><?php } ?>

  <?php } else {
        $rows = q('SELECT m.client, m.body, m.created_at, p.filename, p.key_thumb FROM comments m JOIN photos p ON p.id=m.photo_id WHERE m.collection_id=? ORDER BY m.id DESC LIMIT 500', [$cid])->fetchAll(); ?>
  <div class="mainhead"><h1>Comments</h1></div>
  <?php if ($rows) { ?><table class="list"><thead><tr><th>Photo</th><th>Client</th><th>Comment</th><th>Date</th></tr></thead><tbody>
    <?php foreach ($rows as $r) { ?><tr class="still"><td><span class="colname"><span class="sq"><img loading="lazy" src="<?= e(r2_url('GET', $r['key_thumb'])) ?>" alt=""></span><span><?= e($r['filename']) ?></span></span></td>
    <td><?= e($r['client']) ?></td><td class="wrap"><?= nl2br(e($r['body'])) ?></td><td><?= e(date('M j, Y H:i', strtotime($r['created_at']))) ?></td></tr><?php } ?></tbody></table>
  <?php } else { ?><div class="emptystate"><?= icon('pencil') ?><p>No comments yet.</p><p class="muted">Comments clients leave on photos are private: only you and that client see them.</p></div><?php } ?>
  <?php } ?>
</main>
<?php } ?>
</div>
<script>{
document.getElementById('status').onchange = async ev => {
  const fd = new FormData(); fd.append('csrf', <?= json_encode(csrf()) ?>); fd.append('cid', <?= $cid ?>); fd.append('field', 'status'); fd.append('value', ev.target.value);
  await fetch(<?= json_encode("$B/api.php?a=set") ?>, {method: 'POST', body: fd}); window.nav ? nav(location.href, false) : location.reload();
};
}</script>
    <?php page_end(); exit;
}

// ---------- Collections list ----------
$where = []; $args = [];
if (!empty($_GET['folder'])) { $where[] = 'c.folder_id=?'; $args[] = (int)$_GET['folder']; }
if (!empty($_GET['tag'])) { $where[] = 'FIND_IN_SET(?, c.tags)'; $args[] = $_GET['tag']; }
if (!empty($_GET['status'])) { $where[] = 'c.status=?'; $args[] = $_GET['status']; }
if (!empty($_GET['q'])) { $where[] = 'c.name LIKE ?'; $args[] = '%' . $_GET['q'] . '%'; }
$cols = q('SELECT c.*, p.key_thumb, (SELECT COUNT(*) FROM photos x WHERE x.collection_id=c.id) n
           FROM collections c LEFT JOIN photos p ON p.id=c.cover_photo_id'
          . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY COALESCE(c.event_date, c.created_at) DESC, c.id DESC', $args)->fetchAll();
$tagOpts = ['' => 'Category Tag'];
foreach (q("SELECT tags FROM collections WHERE tags<>''") as $r) foreach (explode(',', $r['tags']) as $t) $tagOpts[$t] = $t;
$folderFilter = ['' => 'Folder']; foreach ($folders as $f) $folderFilter[$f['id']] = $f['name'];
$curFolder = null; foreach ($folders as $f) if (($_GET['folder'] ?? 0) == $f['id']) $curFolder = $f;
page_top('Collections'); ?>
<div class="shell"><?php sidebar('collections'); ?>
<main class="content">
  <div class="mainhead"><h1>Collections</h1>
    <form class="search" id="filters"><?= icon('search') ?><input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search"></form><span class="grow"></span>
    <button class="btn" type="button" onclick="document.getElementById('newfolder').showModal()">New Folder</button>
    <button class="btn primary" type="button" onclick="document.getElementById('newcol').showModal()">New Collection</button></div>
  <div class="chips">
    <?= sel('status', ['' => 'Status', 'published' => 'Published', 'draft' => 'Draft'], $_GET['status'] ?? '', 'form="filters" class="chip" onchange="this.form.requestSubmit()"') ?>
    <?= sel('tag', $tagOpts, $_GET['tag'] ?? '', 'form="filters" class="chip" onchange="this.form.requestSubmit()"') ?>
    <?php if ($folders) echo sel('folder', $folderFilter, $_GET['folder'] ?? '', 'form="filters" class="chip" onchange="this.form.requestSubmit()"'); ?>
    <?php if ($where) { ?><a class="link" href="<?= $B ?>/admin.php">Clear filters</a><?php } ?>
  </div>
  <?php if ($curFolder) { ?><div class="folderbar"><?= icon('folder') ?><span>Share this folder: <code><?= e("$site/?f={$curFolder['share_token']}") ?></code></span><span class="grow"></span>
    <form method="post" onsubmit="return confirm('Delete this folder? Its collections are kept.')"><?= hidden('delete_folder') ?><input type="hidden" name="fid" value="<?= $curFolder['id'] ?>"><button class="link danger">Delete folder</button></form></div><?php } ?>

  <?php if ($cols) { ?><table class="list"><thead><tr><th>Name</th><th>Status</th><th>Password</th><th>Date created</th></tr></thead><tbody>
  <?php foreach ($cols as $c) { ?><tr onclick="nav('<?= $B ?>/admin.php?c=<?= $c['id'] ?>')">
    <td><a class="colname" href="<?= $B ?>/admin.php?c=<?= $c['id'] ?>"><span class="sq"><?php if ($c['key_thumb']) { ?><img loading="lazy" src="<?= e(r2_url('GET', $c['key_thumb'])) ?>" alt=""><?php } ?></span>
      <span><strong><?= e($c['name']) ?></strong><small><?= (int)$c['n'] ?> items<?= $c['event_date'] ? ' &bull; ' . e(nice_date($c['event_date'])) : '' ?></small></span></a></td>
    <td><span class="pill <?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
    <td><?= $c['password'] !== '' ? e($c['password']) : '<span class="muted">-</span>' ?></td>
    <td><?= e(nice_date($c['created_at'])) ?></td></tr>
  <?php } ?></tbody></table>
  <?php } else { ?><div class="emptystate"><?= icon('image') ?><p><?= $where ? 'No collections match these filters.' : 'No collections yet.' ?></p>
    <?php if (!$where) echo '<p class="muted">Press New Collection to create your first gallery.</p>'; ?></div><?php } ?>
</main></div>

<dialog id="newcol"><h2>New Collection</h2><form method="post" class="stack"><?= hidden('new_collection') ?>
  <label>Collection name <input name="name" required placeholder="e.g. Sara &amp; Daniel"></label>
  <label>Event date <input type="date" name="event_date"></label>
  <label>Folder <?= sel('folder_id', $folderOpts, (int)($_GET['folder'] ?? 0)) ?></label>
  <div class="row end"><button class="btn" type="button" onclick="this.closest('dialog').close()">Cancel</button><button class="btn primary">Create</button></div></form></dialog>
<dialog id="newfolder"><h2>New Folder</h2><form method="post" class="stack"><?= hidden('new_folder') ?>
  <label>Folder name <input name="name" required></label>
  <div class="row end"><button class="btn" type="button" onclick="this.closest('dialog').close()">Cancel</button><button class="btn primary">Create</button></div></form></dialog>
<?php page_end();
