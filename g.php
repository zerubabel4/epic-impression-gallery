<?php
// Client-facing gallery: password gate, cover, sets, photo grid, full-screen viewer
// with favorite, download, share link, private comments and slideshow.
require __DIR__ . '/lib.php';
$B = base();
$c = q('SELECT * FROM collections WHERE slug=?', [$_GET['s'] ?? ''])->fetch();
$expired = $c && $c['expires_at'] && $c['expires_at'] < date('Y-m-d');
if (!$c || (!is_admin() && ($c['status'] !== 'published' || $expired))) {
    http_response_code(404);
    exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Not available</title><p style="font-family:sans-serif;text-align:center;margin-top:20vh">This gallery is not available.</p>');
}
$cid = (int)$c['id'];
$vars = palette_vars($c['palette'] ?? 'light') . ';' . type_vars($c['font']);
$embedded = isset($_GET['embed']);
$preview = is_admin() && isset($_GET['preview']); // opened with the Preview button in the dashboard // shown inside the admin design preview

function gi($n) {
    $p = [
        'heart' => '<path d="M12 20s-7-4.4-7-9.5A4 4 0 0112 8a4 4 0 017 2.5c0 5.1-7 9.5-7 9.5z"/>',
        'download' => '<path d="M12 4v11M7.5 11l4.5 4.5 4.5-4.5M5 20h14"/>',
        'share' => '<path d="M14 5l6 6-6 6M20 11h-8a8 8 0 00-8 8"/>',
        'play' => '<path d="M7 4.5v15l12-7.5z"/>',
        'comment' => '<path d="M4 5h16v11H9l-5 4z"/>',
        'back' => '<path d="M11 5l-7 7 7 7M4 12h16"/>',
        'prev' => '<path d="M15 5l-7 7 7 7"/>', 'next' => '<path d="M9 5l7 7-7 7"/>',
    ][$n];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
function top($c, $vars) { global $B; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title><?= e($c['name']) ?></title>
<link rel="preconnect" href="https://fonts.bunny.net"><link rel="stylesheet" href="<?= e(FONT_LINK) ?>">
<link rel="stylesheet" href="<?= $B ?>/style.css?v=4"></head>
<body class="gallery" style="<?= e($vars) ?>">
<?php }

// Password gate
$err = '';
if ($c['password'] !== '' && !is_admin() && empty($_SESSION['g'][$cid])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        if (hash_equals($c['password'], (string)($_POST['password'] ?? ''))) {
            $_SESSION['g'][$cid] = 1;
            header('Location: ' . $B . '/g/' . $c['slug'] . (isset($_GET['photo']) ? '?photo=' . (int)$_GET['photo'] : '')); exit;
        }
        sleep(1); $err = 'That password is not correct.';
    }
    top($c, $vars); ?>
    <main class="gate"><h1><?= e($c['name']) ?></h1><p>This gallery is private. Enter the password to view it.</p>
    <?php if ($err) echo '<p class="err">' . e($err) . '</p>'; ?>
    <form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>">
    <input type="password" name="password" placeholder="Password" autofocus><button>View gallery</button></form></main></body></html>
    <?php exit;
}

$sets = q('SELECT s.* FROM sets s WHERE collection_id=? AND EXISTS (SELECT 1 FROM photos p WHERE p.set_id=s.id) ORDER BY position, id', [$cid])->fetchAll();
$cur = $sets[0] ?? null;
$linked = isset($_GET['photo']) ? q('SELECT id, set_id FROM photos WHERE id=? AND collection_id=?', [(int)$_GET['photo'], $cid])->fetch() : null;
$want = $linked ? $linked['set_id'] : (int)($_GET['set'] ?? 0);
foreach ($sets as $s) if ($s['id'] == $want) $cur = $s;
$photos = $cur ? q('SELECT * FROM photos WHERE set_id=? ORDER BY ' . photo_order($c['sort_mode']), [$cur['id']])->fetchAll() : [];
$cover = cover_photo($c);
$rowH = $c['grid_size'] === 'large' ? 380 : 260;
$date = $c['event_date'] ? date('F jS, Y', strtotime($c['event_date'])) : '';
$style = isset(covers()[$c['cover_style']]) ? $c['cover_style'] : 'center';
$name = client_name($cid);
$favs = $name !== '' ? array_map('intval', q('SELECT photo_id FROM favorites WHERE collection_id=? AND client=?', [$cid, $name])->fetchAll(PDO::FETCH_COLUMN)) : [];
$canFav = $c['allow_favorite'] || is_admin(); $canDl = $c['allow_download'] || is_admin();
top($c, $vars);

if ($preview && !$embedded) { ?>
<p class="ownerbar">Viewing collection as <b>Owner</b><?= $c['status'] !== 'published' || $expired ? ' (' . ($expired ? 'expired' : 'draft') . ', hidden from clients)' : '' ?>.
<a href="<?= $B ?>/admin.php?c=<?= $cid ?>">Edit collection</a><a href="<?= $B ?>/admin.php">Dashboard</a></p>
<?php }

if ($cover && $style !== 'none' && !$linked) { ?>
<header class="cover c-<?= e($style) ?>"><div class="cimg"><img src="<?= e(r2_url('GET', $cover['key_web'])) ?>" alt="" style="object-position:<?= (int)($c['focal_x'] ?? 50) ?>% <?= (int)($c['focal_y'] ?? 50) ?>%"></div>
<div class="ctitle"><h1><?= e($c['name']) ?></h1><?php if ($date) echo '<p>' . e($date) . '</p>'; ?><a href="#photos">View gallery</a></div>
<p class="studio"><?= e($CFG['site_name']) ?></p></header>
<?php } ?>

<div class="gbar" id="photos"><div class="gname"><strong><?= e($c['name']) ?></strong><span><?= e($CFG['site_name']) ?></span></div>
<?php if (count($sets) > 1) { ?><nav class="sets"><?php foreach ($sets as $s) { ?>
<a class="<?= $s['id'] == $cur['id'] ? 'on' : '' ?>" href="<?= $B ?>/g.php?s=<?= e($c['slug']) ?>&set=<?= $s['id'] ?><?= $preview ? '&preview=1' : '' ?>#photos"><?= e($s['name']) ?></a><?php } ?></nav><?php } ?>
<span class="grow"></span>
<div class="gtools"><?php if ($canFav) { ?><button type="button" id="favfilter" title="Show my favorites"><?= gi('heart') ?><i id="favcount"></i></button><?php } ?>
<?php if ($photos) { ?><button type="button" id="playall" title="Slideshow"><?= gi('play') ?></button><?php } ?></div></div>

<main>
<div id="grid" class="grid <?= e($c['grid_style']) ?> size-<?= e($c['grid_size']) ?> gap-<?= e($c['grid_gap']) ?>">
<?php foreach ($photos as $i => $p) { $ar = $p['height'] ? $p['width'] / $p['height'] : 1.5; ?>
<div class="ph" tabindex="0" role="button" data-i="<?= $i ?>" data-id="<?= $p['id'] ?>" data-web="<?= e(r2_url('GET', $p['key_web'])) ?>" data-name="<?= e($p['filename']) ?>"
   style="aspect-ratio:<?= round($ar, 4) ?>;flex:<?= round($ar, 4) ?> 1 <?= round($ar * $rowH) ?>px">
<img loading="lazy" src="<?= e(r2_url('GET', $p['key_thumb'])) ?>" alt="<?= e($p['filename']) ?>">
<?php if ($canFav) { ?><button type="button" class="tfav" title="Favorite"><?= gi('heart') ?></button><?php } ?>
<?php if ($c['show_filenames']) echo '<span>' . e($p['filename']) . '</span>'; ?></div>
<?php } ?>
</div>
<p class="empty" id="empty" <?= $photos ? 'hidden' : '' ?>><?= $photos ? 'You have no favorites in this set yet.' : 'No photos here yet.' ?></p>
</main>
<footer class="gfoot"><?= e($CFG['site_name']) ?></footer>

<div id="vw" hidden>
  <header><button type="button" id="vback" title="Back to gallery"><?= gi('back') ?></button><span class="grow"></span>
    <?php if ($canFav) { ?><button type="button" id="vfav" title="Favorite"><?= gi('heart') ?></button><?php } ?>
    <?php if ($canDl) { ?><a id="vdl" title="Download"><?= gi('download') ?></a><?php } ?>
    <button type="button" id="vshare" title="Copy link to this photo"><?= gi('share') ?></button>
    <button type="button" id="vcom" title="Comment"><?= gi('comment') ?></button>
    <button type="button" id="vplay" title="Slideshow"><?= gi('play') ?></button></header>
  <button type="button" id="vprev" title="Previous"><?= gi('prev') ?></button>
  <figure><img id="vimg" alt=""><figcaption id="vcap"></figcaption></figure>
  <button type="button" id="vnext" title="Next"><?= gi('next') ?></button>
  <aside id="cpanel" hidden><h2>Comments</h2><p class="hint">Only you and the photographer can see these.</p>
    <div id="clist"></div><form id="cform"><textarea id="cbody" rows="3" placeholder="Write a comment about this photo" required></textarea><button>Send</button></form></aside>
</div>
<dialog id="who"><form id="whoform"><h2>What's your name?</h2><p>Your name lets the photographer know whose favorites and comments these are.</p>
  <input id="whoname" maxlength="80" placeholder="Your name" required><div class="drow"><button type="button" id="whocancel">Cancel</button><button class="primary">Continue</button></div></form></dialog>
<div id="toast" hidden></div>

<script>
const CFG = <?= json_encode(['api' => "$B/c.php", 'cid' => $cid, 'csrf' => csrf(), 'name' => $name, 'favs' => $favs,
    'share' => (empty($_SERVER['HTTPS']) ? 'http' : 'https') . '://' . $_SERVER['HTTP_HOST'] . "$B/g/{$c['slug']}?photo=",
    'names' => (bool)$c['show_filenames'], 'open' => $linked ? (int)$linked['id'] : 0]) ?>;
const $ = id => document.getElementById(id);
const tiles = [...document.querySelectorAll('.ph')], vw = $('vw'), img = $('vimg');
const favs = new Set(CFG.favs);
let at = 0, timer = null;

function toast(msg) { const t = $('toast'); t.textContent = msg; t.hidden = false; clearTimeout(t.t); t.t = setTimeout(() => t.hidden = true, 2200); }
async function call(a, data) {
  const fd = new FormData(); fd.append('csrf', CFG.csrf); fd.append('cid', CFG.cid);
  for (const k in data) fd.append(k, data[k]);
  const r = await fetch(CFG.api + '?a=' + a, {method: 'POST', body: fd});
  return r.json();
}
// Ask for the visitor's name once, the first time they favorite or comment.
function needName() {
  if (CFG.name) return Promise.resolve(true);
  return new Promise(resolve => {
    const d = $('who'); d.showModal();
    $('whocancel').onclick = () => { d.close(); resolve(false); };
    $('whoform').onsubmit = async ev => {
      ev.preventDefault();
      const r = await call('name', {name: $('whoname').value});
      if (r.error) return toast(r.error);
      CFG.name = r.name; favs.clear(); r.favs.forEach(f => favs.add(f)); paintFavs();
      d.close(); resolve(true);
    };
  });
}

// ---------- Favorites ----------
function paintFavs() {
  tiles.forEach(t => t.classList.toggle('fav', favs.has(+t.dataset.id)));
  if ($('favcount')) $('favcount').textContent = favs.size || '';
  if ($('vfav') && tiles[at]) $('vfav').classList.toggle('on', favs.has(+tiles[at].dataset.id));
  if (document.body.classList.contains('onlyfavs')) $('empty').hidden = tiles.some(t => t.classList.contains('fav'));
}
async function toggleFav(id) {
  if (!await needName()) return;
  const r = await call('fav', {id});
  if (r.error) return toast(r.error);
  r.on ? favs.add(id) : favs.delete(id); paintFavs();
  toast(r.on ? 'Added to favorites' : 'Removed from favorites');
}
if ($('favfilter')) $('favfilter').onclick = () => {
  const on = document.body.classList.toggle('onlyfavs'); $('favfilter').classList.toggle('on', on);
  $('empty').hidden = !on || tiles.some(t => t.classList.contains('fav'));
};

// ---------- Full-screen viewer ----------
// Slide the old photo out and the new one in, in the direction of travel.
function slide(dir) {
  if (!dir || !img.getAttribute('src') || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const r = img.getBoundingClientRect(), ghost = img.cloneNode();
  ghost.removeAttribute('id');
  Object.assign(ghost.style, {position: 'fixed', left: r.left + 'px', top: r.top + 'px', width: r.width + 'px', height: r.height + 'px', maxWidth: 'none', maxHeight: 'none', pointerEvents: 'none', zIndex: 11});
  document.body.append(ghost);
  const opt = {duration: 450, easing: 'cubic-bezier(.4, 0, .2, 1)'};
  ghost.animate([{transform: 'none', opacity: 1}, {transform: `translateX(${-dir * 60}vw)`, opacity: 0}], opt).onfinish = () => ghost.remove();
  img.animate([{transform: `translateX(${dir * 60}vw)`, opacity: 0}, {transform: 'none', opacity: 1}], opt);
}
function show(i, dir = 0) {
  at = (i + tiles.length) % tiles.length;
  const t = tiles[at], id = +t.dataset.id;
  if (!vw.hidden) slide(dir);
  img.src = t.dataset.web; $('vcap').textContent = CFG.names ? t.dataset.name : '';
  if ($('vdl')) $('vdl').href = CFG.api + '?a=download&cid=' + CFG.cid + '&id=' + id;
  vw.hidden = false; document.body.style.overflow = 'hidden';
  paintFavs();
  if (!$('cpanel').hidden) loadComments();
  const next = tiles[(at + 1) % tiles.length]; if (next) new Image().src = next.dataset.web;
}
function closeViewer() { stopShow(); vw.hidden = true; img.src = ''; $('cpanel').hidden = true; document.body.style.overflow = ''; }
function startShow() {
  vw.classList.add('show'); $('cpanel').hidden = true;
  if (vw.requestFullscreen) vw.requestFullscreen().catch(() => {});
  timer = setInterval(() => show(at + 1, 1), 4000);
}
function stopShow() {
  if (!timer) return;
  clearInterval(timer); timer = null; vw.classList.remove('show');
  if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
}
tiles.forEach((t, i) => {
  t.onclick = ev => { if (ev.target.closest('.tfav')) { toggleFav(+t.dataset.id); return; } show(i); };
  t.onkeydown = ev => { if (ev.key === 'Enter') show(i); };
});
$('vback').onclick = closeViewer;
$('vprev').onclick = () => show(at - 1, -1);
$('vnext').onclick = () => show(at + 1, 1);
$('vplay').onclick = ev => { ev.stopPropagation(); startShow(); };
if ($('playall')) $('playall').onclick = () => { show(0); startShow(); };
if ($('vfav')) $('vfav').onclick = () => toggleFav(+tiles[at].dataset.id);
$('vshare').onclick = async () => {
  const url = CFG.share + tiles[at].dataset.id;
  try { await navigator.clipboard.writeText(url); toast('Link copied'); } catch (err) { prompt('Copy this link', url); }
};
vw.addEventListener('click', ev => { if (timer) stopShow(); });
document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement) stopShow(); });
document.onkeydown = ev => {
  if (vw.hidden || ev.target.matches('textarea, input')) return;
  if (ev.key === 'Escape') timer ? stopShow() : closeViewer();
  if (ev.key === 'ArrowLeft') show(at - 1, -1);
  if (ev.key === 'ArrowRight') show(at + 1, 1);
};
let sx = null;
vw.ontouchstart = ev => sx = ev.touches[0].clientX;
vw.ontouchend = ev => { if (sx === null) return; const d = ev.changedTouches[0].clientX - sx; if (Math.abs(d) > 50) show(at + (d < 0 ? 1 : -1), d < 0 ? 1 : -1); sx = null; };

// ---------- Private comments ----------
function drawComments(list) {
  const box = $('clist'); box.textContent = '';
  if (!list.length) { const p = document.createElement('p'); p.className = 'hint'; p.textContent = 'No comments on this photo yet.'; box.append(p); }
  list.forEach(c => { const d = document.createElement('div'); d.className = 'cm'; const b = document.createElement('b'); b.textContent = c.client;
    const s = document.createElement('p'); s.textContent = c.body; d.append(b, s); box.append(d); });
}
async function loadComments() { const r = await call('comments', {id: tiles[at].dataset.id}); drawComments(r.comments || []); }
$('vcom').onclick = async () => {
  if (!$('cpanel').hidden) { $('cpanel').hidden = true; return; }
  if (!await needName()) return;
  $('cpanel').hidden = false; loadComments();
};
$('cform').onsubmit = async ev => {
  ev.preventDefault();
  const r = await call('comment', {id: tiles[at].dataset.id, body: $('cbody').value});
  if (r.error) return toast(r.error);
  $('cbody').value = ''; drawComments(r.comments); toast('Comment sent');
};

paintFavs();
if (CFG.open) { const i = tiles.findIndex(t => +t.dataset.id === CFG.open); if (i >= 0) show(i); }
</script>
</body></html>
