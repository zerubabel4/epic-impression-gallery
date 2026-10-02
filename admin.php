<?php
// Photographer's dashboard: login, collections, folders, tags, sets, uploads, design settings.
require __DIR__ . '/lib.php';
$B = base();

if (isset($_GET['logout'])) { session_destroy(); redirect('admin.php'); }

function page_top($title) { global $B, $CFG; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title><?= e($title) ?></title><link rel="stylesheet" href="<?= $B ?>/style.css"></head>
<body class="admin"><?php if (is_admin()) { ?><header class="bar"><a href="<?= $B ?>/admin.php"><strong><?= e($CFG['site_name']) ?></strong></a>
<nav><a href="<?= $B ?>/" target="_blank">View homepage</a><a href="<?= $B ?>/admin.php?account=1">Account</a><a href="<?= $B ?>/admin.php?logout=1">Log out</a></nav></header><?php } ?><main>
<?php }
function page_end() { echo '</main></body></html>'; }
function sel($name, $opts, $cur) {
    $h = "<select name=\"$name\">";
    foreach ($opts as $v => $label) $h .= '<option value="' . e($v) . '"' . ((string)$v === (string)$cur ? ' selected' : '') . '>' . e($label) . '</option>';
    return $h . '</select>';
}
function unique_slug($name, $except = 0) {
    $base = slugify($name); $slug = $base; $i = 2;
    while (q('SELECT id FROM collections WHERE slug=? AND id<>?', [$slug, $except])->fetch()) $slug = $base . '-' . $i++;
    return $slug;
}
function hex($v, $d) { return preg_match('/^#[0-9a-fA-F]{6}$/', $v ?? '') ? $v : $d; }

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
    page_top('Log in'); ?>
    <div class="narrow"><h1>Log in</h1><?php if ($err) echo '<p class="err">' . e($err) . '</p>'; ?>
    <form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrf() ?>">
    <label>Password <input type="password" name="password" autofocus></label><button>Log in</button></form></div>
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
            if ($name !== '') {
                q('INSERT INTO collections (name, slug, folder_id) VALUES (?,?,?)', [$name, unique_slug($name), ((int)$_POST['folder_id']) ?: null]);
                $cid = db()->lastInsertId();
                q("INSERT INTO sets (collection_id, name, position) VALUES (?, 'Highlights', 1)", [$cid]);
                redirect("admin.php?c=$cid");
            }
            break;
        case 'new_folder':
            if (trim($_POST['name']) !== '') q('INSERT INTO folders (name, share_token) VALUES (?,?)', [trim($_POST['name']), bin2hex(random_bytes(12))]);
            redirect('admin.php');
        case 'delete_folder':
            q('UPDATE collections SET folder_id=NULL WHERE folder_id=?', [(int)$_POST['fid']]);
            q('DELETE FROM folders WHERE id=?', [(int)$_POST['fid']]);
            redirect('admin.php');
        case 'save_collection':
            $tags = implode(',', array_unique(array_filter(array_map(function ($t) { return trim(strtolower($t)); }, explode(',', $_POST['tags'])))));
            q('UPDATE collections SET name=?, slug=?, event_date=?, folder_id=?, tags=?, status=?, password=?, hide_home=?, expires_at=?,
                cover_style=?, grid_style=?, grid_size=?, grid_gap=?, font=?, color_bg=?, color_text=?, color_accent=?, show_filenames=?, sort_mode=? WHERE id=?',
              [trim($_POST['name']) ?: 'Untitled', unique_slug($_POST['slug'] ?: $_POST['name'], $cid), $_POST['event_date'] ?: null,
               ((int)$_POST['folder_id']) ?: null, $tags, $_POST['status'] === 'published' ? 'published' : 'draft', trim($_POST['password']),
               isset($_POST['hide_home']) ? 1 : 0, $_POST['expires_at'] ?: null, $_POST['cover_style'], $_POST['grid_style'], $_POST['grid_size'],
               $_POST['grid_gap'], $_POST['font'], hex($_POST['color_bg'], '#ffffff'), hex($_POST['color_text'], '#222222'),
               hex($_POST['color_accent'], '#8a6d4b'), isset($_POST['show_filenames']) ? 1 : 0, $_POST['sort_mode'], $cid]);
            redirect("admin.php?c=$cid&saved=1");
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

// ---------- Account ----------
if (isset($_GET['account'])) {
    $u = q('SELECT COUNT(*) n, COALESCE(SUM(size),0) b FROM photos')->fetch();
    page_top('Account'); ?>
    <div class="narrow"><h1>Account</h1><?php if ($msg) echo '<p class="note">' . e($msg) . '</p>'; ?>
    <h2>Storage</h2><p><?= (int)$u['n'] ?> photos, <?= number_format($u['b'] / 1073741824, 2) ?> GB of originals stored.</p>
    <h2>Change password</h2>
    <form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="do" value="change_password">
    <label>Current password <input type="password" name="current"></label>
    <label>New password <input type="password" name="new"></label><button>Change password</button></form></div>
    <?php page_end(); exit;
}

// ---------- One collection ----------
if (isset($_GET['c'])) {
    $c = q('SELECT * FROM collections WHERE id=?', [(int)$_GET['c']])->fetch();
    if (!$c) redirect('admin.php');
    $sets = q('SELECT s.*, (SELECT COUNT(*) FROM photos p WHERE p.set_id=s.id) n FROM sets s WHERE collection_id=? ORDER BY position, id', [$c['id']])->fetchAll();
    $setId = (int)($_GET['set'] ?? $sets[0]['id']); $cur = $sets[0];
    foreach ($sets as $s) if ($s['id'] == $setId) $cur = $s;
    $order = ['manual' => 'position, id', 'name_asc' => 'filename', 'name_desc' => 'filename DESC', 'date_asc' => 'taken_at, id', 'date_desc' => 'taken_at DESC, id DESC'][$c['sort_mode']] ?? 'position, id';
    $photos = q("SELECT * FROM photos WHERE set_id=? ORDER BY $order", [$cur['id']])->fetchAll();
    $setOpts = []; foreach ($sets as $s) $setOpts[$s['id']] = $s['name'];
    page_top($c['name']); ?>
    <p><a href="<?= $B ?>/admin.php">&larr; All collections</a></p>
    <div class="row between"><h1><?= e($c['name']) ?> <span class="pill <?= e($c['status']) ?>"><?= e($c['status']) ?></span></h1>
    <a class="btn" href="<?= $B ?>/g/<?= e($c['slug']) ?>" target="_blank">Open gallery</a></div>
    <p class="muted">Client link: <code><?= e("$site/g/{$c['slug']}") ?></code><?= $c['password'] !== '' ? ' &middot; password: <code>' . e($c['password']) . '</code>' : '' ?></p>
    <?php if (isset($_GET['saved'])) echo '<p class="note">Settings saved.</p>'; ?>

    <h2>Photos</h2>
    <div class="tabs"><?php foreach ($sets as $s) { ?><a class="<?= $s['id'] == $cur['id'] ? 'on' : '' ?>" href="?c=<?= $c['id'] ?>&set=<?= $s['id'] ?>"><?= e($s['name']) ?> (<?= $s['n'] ?>)</a><?php } ?>
    <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="do" value="new_set"><input type="hidden" name="cid" value="<?= $c['id'] ?>">
    <input name="name" placeholder="New set name"><button>Add set</button></form></div>

    <div class="row tools">
      <label class="btn primary">Upload photos to "<?= e($cur['name']) ?>"<input type="file" id="files" accept="image/jpeg,image/png,image/webp" multiple hidden></label>
      <button type="button" id="selall">Select all</button>
      <button type="button" data-act="cover">Use as cover</button>
      <?php if (count($sets) > 1) { echo sel('moveto', $setOpts, $cur['id']); ?><button type="button" data-act="move">Move to set</button><?php } ?>
      <button type="button" data-act="delete" class="danger">Delete selected</button>
      <span id="status" class="muted"></span>
    </div>
    <?php if ($c['sort_mode'] !== 'manual') echo '<p class="muted">Photos are sorted automatically. Dragging a photo switches this collection to manual order.</p>'; ?>
    <div id="grid" class="agrid"><?php foreach ($photos as $p) { ?>
      <label class="acell<?= $p['id'] == $c['cover_photo_id'] ? ' cover' : '' ?>" draggable="true" data-id="<?= $p['id'] ?>">
        <input type="checkbox" value="<?= $p['id'] ?>"><img loading="lazy" src="<?= e(r2_url('GET', $p['key_thumb'])) ?>" alt=""><span><?= e($p['filename']) ?></span></label>
    <?php } ?></div>
    <?php if (!$photos) echo '<p class="muted">No photos in this set yet.</p>'; ?>

    <details><summary>Rename or delete this set</summary>
    <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="cid" value="<?= $c['id'] ?>"><input type="hidden" name="sid" value="<?= $cur['id'] ?>">
    <input name="name" value="<?= e($cur['name']) ?>"><button name="do" value="rename_set">Rename</button>
    <?php if (count($sets) > 1) { ?><button name="do" value="delete_set" class="danger" onclick="return confirm('Delete this set? Its photos move to another set.')">Delete set</button><?php } ?></form></details>

    <h2>Settings</h2>
    <form method="post" class="settings"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="do" value="save_collection"><input type="hidden" name="cid" value="<?= $c['id'] ?>">
    <fieldset><legend>Collection</legend>
      <label>Name <input name="name" value="<?= e($c['name']) ?>"></label>
      <label>Link name <input name="slug" value="<?= e($c['slug']) ?>"></label>
      <label>Event date <input type="date" name="event_date" value="<?= e($c['event_date']) ?>"></label>
      <label>Folder <?= sel('folder_id', $folderOpts, (int)$c['folder_id']) ?></label>
      <label>Tags (comma separated) <input name="tags" value="<?= e(str_replace(',', ', ', $c['tags'])) ?>"></label>
    </fieldset>
    <fieldset><legend>Access</legend>
      <label>Status <?= sel('status', ['draft' => 'Draft (only you)', 'published' => 'Published'], $c['status']) ?></label>
      <label>Gallery password (empty = none) <input name="password" value="<?= e($c['password']) ?>" autocomplete="off"></label>
      <label>Expires on (empty = never) <input type="date" name="expires_at" value="<?= e($c['expires_at']) ?>"></label>
      <label class="check"><input type="checkbox" name="hide_home" <?= $c['hide_home'] ? 'checked' : '' ?>> Hide from homepage</label>
    </fieldset>
    <fieldset><legend>Design</legend>
      <label>Cover <?= sel('cover_style', ['full' => 'Full screen', 'split' => 'Split', 'banner' => 'Banner', 'none' => 'No cover'], $c['cover_style']) ?></label>
      <label>Grid layout <?= sel('grid_style', ['masonry' => 'Vertical (masonry)', 'rows' => 'Horizontal rows', 'square' => 'Squares'], $c['grid_style']) ?></label>
      <label>Thumbnail size <?= sel('grid_size', ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'], $c['grid_size']) ?></label>
      <label>Spacing <?= sel('grid_gap', ['small' => 'Small', 'large' => 'Large'], $c['grid_gap']) ?></label>
      <label>Font <?= sel('font', ['serif' => 'Serif', 'sans' => 'Sans', 'classic' => 'Classic', 'mono' => 'Mono'], $c['font']) ?></label>
      <label>Background <input type="color" name="color_bg" value="<?= e($c['color_bg']) ?>"></label>
      <label>Text <input type="color" name="color_text" value="<?= e($c['color_text']) ?>"></label>
      <label>Accent <input type="color" name="color_accent" value="<?= e($c['color_accent']) ?>"></label>
      <label>Photo order <?= sel('sort_mode', ['manual' => 'Manual (drag to arrange)', 'name_asc' => 'Filename A-Z', 'name_desc' => 'Filename Z-A', 'date_asc' => 'Oldest first', 'date_desc' => 'Newest first'], $c['sort_mode']) ?></label>
      <label class="check"><input type="checkbox" name="show_filenames" <?= $c['show_filenames'] ? 'checked' : '' ?>> Show filenames in gallery</label>
    </fieldset>
    <button class="primary">Save settings</button></form>

    <form method="post" onsubmit="return confirm('Delete this collection and all its photos? This cannot be undone.')">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="do" value="delete_collection"><input type="hidden" name="cid" value="<?= $c['id'] ?>">
    <p><button class="danger">Delete collection</button></p></form>

<script>
const API = <?= json_encode("$B/api.php") ?>, CID = <?= (int)$c['id'] ?>, SET = <?= (int)$cur['id'] ?>, CSRF = <?= json_encode(csrf()) ?>;
const grid = document.getElementById('grid'), statusEl = document.getElementById('status');
async function api(a, data) {
  const fd = new FormData(); fd.append('csrf', CSRF); fd.append('cid', CID);
  for (const k in data) fd.append(k, data[k]);
  const r = await (await fetch(API + '?a=' + a, {method: 'POST', body: fd})).json();
  if (r.error) throw new Error(r.error);
  return r;
}
function shrink(bmp, max) {
  const s = Math.min(1, max / Math.max(bmp.width, bmp.height)), c = document.createElement('canvas');
  c.width = Math.round(bmp.width * s); c.height = Math.round(bmp.height * s);
  c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
  return new Promise(res => c.toBlob(res, 'image/jpeg', 0.85));
}
async function put(url, body) {
  const r = await fetch(url, {method: 'PUT', body});
  if (!r.ok) throw new Error('Storage refused the upload (' + r.status + ')');
}
// Originals go straight from this browser to storage; web and thumbnail sizes are made here first.
document.getElementById('files').onchange = async ev => {
  const files = [...ev.target.files]; let done = 0, failed = [];
  for (const f of files) {
    statusEl.textContent = `Uploading ${done + 1} of ${files.length}: ${f.name}`;
    try {
      const bmp = await createImageBitmap(f, {imageOrientation: 'from-image'});
      const [web, thumb] = [await shrink(bmp, 2048), await shrink(bmp, 600)];
      const s = await api('sign', {name: f.name});
      await put(s.urls.orig, f); await put(s.urls.web, web); await put(s.urls.thumb, thumb);
      await api('save', {set_id: SET, filename: f.name, key_orig: s.keys.orig, key_web: s.keys.web, key_thumb: s.keys.thumb,
        width: bmp.width, height: bmp.height, size: f.size, taken_at: f.lastModified});
      bmp.close(); done++;
    } catch (err) { failed.push(f.name + ': ' + err.message); }
  }
  if (failed.length) alert('These files failed:\n' + failed.join('\n'));
  location.reload();
};
const picked = () => [...grid.querySelectorAll('input:checked')].map(i => i.value);
document.getElementById('selall').onclick = () => {
  const boxes = [...grid.querySelectorAll('input')], on = boxes.some(b => !b.checked);
  boxes.forEach(b => b.checked = on);
};
document.querySelectorAll('[data-act]').forEach(b => b.onclick = async () => {
  const ids = picked(), act = b.dataset.act;
  if (!ids.length) return alert('Select at least one photo first.');
  if (act === 'delete' && !confirm(`Delete ${ids.length} photo(s)? This cannot be undone.`)) return;
  const data = {ids: ids.join(',')};
  if (act === 'move') data.set_id = document.querySelector('[name=moveto]').value;
  try { await api(act, data); location.reload(); } catch (err) { alert(err.message); }
});
// Drag to arrange
let dragged = null;
grid.addEventListener('dragstart', ev => { dragged = ev.target.closest('.acell'); });
grid.addEventListener('dragover', ev => {
  ev.preventDefault();
  const over = ev.target.closest('.acell');
  if (!dragged || !over || over === dragged) return;
  const r = over.getBoundingClientRect();
  grid.insertBefore(dragged, ev.clientX < r.left + r.width / 2 ? over : over.nextSibling);
});
grid.addEventListener('drop', async ev => {
  ev.preventDefault(); if (!dragged) return; dragged = null;
  try { await api('order', {ids: [...grid.children].map(c => c.dataset.id).join(',')}); statusEl.textContent = 'Order saved.'; }
  catch (err) { alert(err.message); }
});
</script>
    <?php page_end(); exit;
}

// ---------- Dashboard ----------
$where = []; $args = [];
if (!empty($_GET['folder'])) { $where[] = 'c.folder_id=?'; $args[] = (int)$_GET['folder']; }
if (!empty($_GET['tag'])) { $where[] = 'FIND_IN_SET(?, c.tags)'; $args[] = $_GET['tag']; }
if (!empty($_GET['q'])) { $where[] = 'c.name LIKE ?'; $args[] = '%' . $_GET['q'] . '%'; }
$cols = q('SELECT c.*, p.key_thumb, f.name folder_name, (SELECT COUNT(*) FROM photos x WHERE x.collection_id=c.id) n
           FROM collections c LEFT JOIN photos p ON p.id=c.cover_photo_id LEFT JOIN folders f ON f.id=c.folder_id'
          . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY COALESCE(c.event_date, c.created_at) DESC, c.id DESC', $args)->fetchAll();
$allTags = [];
foreach (q("SELECT tags FROM collections WHERE tags<>''") as $r) foreach (explode(',', $r['tags']) as $t) $allTags[$t] = 1;
ksort($allTags);
page_top('Collections'); ?>
<h1>Collections</h1>
<form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="do" value="new_collection">
<input name="name" placeholder="New collection name" required><?= sel('folder_id', $folderOpts, (int)($_GET['folder'] ?? 0)) ?><button class="primary">Create collection</button></form>

<div class="filters"><form class="inline"><input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search collections"><button>Search</button></form>
<a class="chip<?= empty($_GET['folder']) && empty($_GET['tag']) && empty($_GET['q']) ? ' on' : '' ?>" href="<?= $B ?>/admin.php">All</a>
<?php foreach ($folders as $f) { ?><a class="chip<?= ($_GET['folder'] ?? 0) == $f['id'] ? ' on' : '' ?>" href="?folder=<?= $f['id'] ?>">&#128193; <?= e($f['name']) ?></a><?php } ?>
<?php foreach ($allTags as $t => $_) { ?><a class="chip<?= ($_GET['tag'] ?? '') === (string)$t ? ' on' : '' ?>" href="?tag=<?= urlencode($t) ?>">#<?= e($t) ?></a><?php } ?></div>

<?php foreach ($folders as $f) if (($_GET['folder'] ?? 0) == $f['id']) { ?>
<p class="muted">Folder share link: <code><?= e("$site/?f={$f['share_token']}") ?></code> (shows this folder's published collections)</p>
<form method="post" onsubmit="return confirm('Delete this folder? Its collections are kept.')"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="do" value="delete_folder"><input type="hidden" name="fid" value="<?= $f['id'] ?>"><button class="danger">Delete folder</button></form>
<?php } ?>

<div class="cards"><?php foreach ($cols as $c) { ?>
<a class="card" href="?c=<?= $c['id'] ?>"><div class="thumb"><?php if ($c['key_thumb']) { ?><img loading="lazy" src="<?= e(r2_url('GET', $c['key_thumb'])) ?>" alt=""><?php } ?></div>
<strong><?= e($c['name']) ?></strong><span class="muted"><?= (int)$c['n'] ?> photos<?= $c['folder_name'] ? ' &middot; ' . e($c['folder_name']) : '' ?></span>
<span><span class="pill <?= e($c['status']) ?>"><?= e($c['status']) ?></span><?= $c['password'] !== '' ? ' <span class="pill">password</span>' : '' ?></span></a>
<?php } ?></div>
<?php if (!$cols) echo '<p class="muted">No collections here yet.</p>'; ?>

<h2>Folders</h2>
<form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="do" value="new_folder">
<input name="name" placeholder="New folder name" required><button>Create folder</button></form>
<?php page_end();
