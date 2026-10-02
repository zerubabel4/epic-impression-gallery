<?php
// Client-facing gallery: password gate, cover, sets, photo grid and lightbox.
require __DIR__ . '/lib.php';
$B = base();
$c = q('SELECT * FROM collections WHERE slug=?', [$_GET['s'] ?? ''])->fetch();
$expired = $c && $c['expires_at'] && $c['expires_at'] < date('Y-m-d');
if (!$c || (!is_admin() && ($c['status'] !== 'published' || $expired))) {
    http_response_code(404);
    exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Not available</title><p style="font-family:sans-serif;text-align:center;margin-top:20vh">This gallery is not available.</p>');
}
$fonts = ['serif' => 'Georgia, "Times New Roman", serif', 'sans' => '"Helvetica Neue", Helvetica, Arial, sans-serif',
          'classic' => '"Palatino Linotype", Palatino, "Book Antiqua", serif', 'mono' => '"Courier New", Courier, monospace'];
$vars = "--bg:{$c['color_bg']};--text:{$c['color_text']};--accent:{$c['color_accent']};--font:" . ($fonts[$c['font']] ?? $fonts['serif']);

function top($c, $vars) { global $B; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title><?= e($c['name']) ?></title><link rel="stylesheet" href="<?= $B ?>/style.css"></head>
<body class="gallery" style="<?= e($vars) ?>">
<?php }

// Password gate
$err = '';
if ($c['password'] !== '' && !is_admin() && empty($_SESSION['g'][$c['id']])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        if (hash_equals($c['password'], (string)($_POST['password'] ?? ''))) {
            $_SESSION['g'][$c['id']] = 1;
            header('Location: ' . $B . '/g/' . $c['slug']); exit;
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

$sets = q('SELECT s.* FROM sets s WHERE collection_id=? AND EXISTS (SELECT 1 FROM photos p WHERE p.set_id=s.id) ORDER BY position, id', [$c['id']])->fetchAll();
$cur = $sets[0] ?? null;
foreach ($sets as $s) if ($s['id'] == (int)($_GET['set'] ?? 0)) $cur = $s;
$order = ['manual' => 'position, id', 'name_asc' => 'filename', 'name_desc' => 'filename DESC', 'date_asc' => 'taken_at, id', 'date_desc' => 'taken_at DESC, id DESC'][$c['sort_mode']] ?? 'position, id';
$photos = $cur ? q("SELECT * FROM photos WHERE set_id=? ORDER BY $order", [$cur['id']])->fetchAll() : [];
$cover = $c['cover_photo_id'] ? q('SELECT * FROM photos WHERE id=?', [$c['cover_photo_id']])->fetch() : null;
$rowH = ['small' => 160, 'medium' => 240, 'large' => 340][$c['grid_size']] ?? 240;
$date = $c['event_date'] ? date('F j, Y', strtotime($c['event_date'])) : '';
top($c, $vars);

if (is_admin() && ($c['status'] !== 'published' || $expired)) echo '<p class="preview">Preview only: clients cannot see this gallery (' . ($expired ? 'expired' : 'draft') . ').</p>';

if ($cover && $c['cover_style'] !== 'none') { ?>
<header class="cover <?= e($c['cover_style']) ?>"><img src="<?= e(r2_url('GET', $cover['key_web'])) ?>" alt="">
<div class="title"><h1><?= e($c['name']) ?></h1><?php if ($date) echo '<p>' . e($date) . '</p>'; ?><a href="#photos">View gallery</a></div></header>
<?php } else { ?>
<header class="plain"><h1><?= e($c['name']) ?></h1><?php if ($date) echo '<p>' . e($date) . '</p>'; ?></header>
<?php } ?>

<main id="photos">
<?php if (count($sets) > 1) { ?><nav class="sets"><?php foreach ($sets as $s) { ?>
<a class="<?= $s['id'] == $cur['id'] ? 'on' : '' ?>" href="<?= $B ?>/g/<?= e($c['slug']) ?>?set=<?= $s['id'] ?>#photos"><?= e($s['name']) ?></a><?php } ?></nav><?php } ?>

<div class="grid <?= e($c['grid_style']) ?> size-<?= e($c['grid_size']) ?> gap-<?= e($c['grid_gap']) ?>">
<?php foreach ($photos as $i => $p) { $ar = $p['height'] ? $p['width'] / $p['height'] : 1.5; ?>
<a class="ph" href="<?= e(r2_url('GET', $p['key_web'])) ?>" data-i="<?= $i ?>" data-name="<?= e($p['filename']) ?>"
   style="aspect-ratio:<?= round($ar, 4) ?>;flex:<?= round($ar, 4) ?> 1 <?= round($ar * $rowH) ?>px">
<img loading="lazy" src="<?= e(r2_url('GET', $p['key_thumb'])) ?>" alt="<?= e($p['filename']) ?>">
<?php if ($c['show_filenames']) echo '<span>' . e($p['filename']) . '</span>'; ?></a>
<?php } ?>
</div>
<?php if (!$photos) echo '<p class="empty">No photos here yet.</p>'; ?>
</main>

<div id="lb" hidden><button id="lbx" aria-label="Close">&times;</button><button id="lbp" aria-label="Previous">&#8249;</button>
<figure><img id="lbi" alt=""><figcaption id="lbc"></figcaption></figure><button id="lbn" aria-label="Next">&#8250;</button></div>
<script>
const items = [...document.querySelectorAll('.ph')], lb = document.getElementById('lb'), img = document.getElementById('lbi');
const cap = document.getElementById('lbc'), names = <?= $c['show_filenames'] ? 'true' : 'false' ?>;
let at = 0;
function show(i) {
  at = (i + items.length) % items.length;
  img.src = items[at].href; cap.textContent = names ? items[at].dataset.name : '';
  lb.hidden = false; document.body.style.overflow = 'hidden';
}
function close() { lb.hidden = true; img.src = ''; document.body.style.overflow = ''; }
items.forEach((a, i) => a.onclick = ev => { ev.preventDefault(); show(i); });
document.getElementById('lbx').onclick = close;
document.getElementById('lbp').onclick = () => show(at - 1);
document.getElementById('lbn').onclick = () => show(at + 1);
lb.onclick = ev => { if (ev.target === lb) close(); };
document.onkeydown = ev => {
  if (lb.hidden) return;
  if (ev.key === 'Escape') close(); if (ev.key === 'ArrowLeft') show(at - 1); if (ev.key === 'ArrowRight') show(at + 1);
};
let sx = null;
lb.ontouchstart = ev => sx = ev.touches[0].clientX;
lb.ontouchend = ev => { if (sx === null) return; const d = ev.changedTouches[0].clientX - sx; if (Math.abs(d) > 50) show(at + (d < 0 ? 1 : -1)); sx = null; };
</script>
</body></html>
