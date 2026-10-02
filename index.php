<?php
// Public homepage: published galleries that are not hidden. With ?f=TOKEN it shows one shared folder.
require __DIR__ . '/lib.php';
$B = base();
$title = $CFG['site_name']; $args = [date('Y-m-d')];
$where = "c.status='published' AND (c.expires_at IS NULL OR c.expires_at >= ?)";
if (!empty($_GET['f'])) {
    $folder = q('SELECT * FROM folders WHERE share_token=?', [$_GET['f']])->fetch();
    if (!$folder) { http_response_code(404); exit('Folder not found.'); }
    $where .= ' AND c.folder_id=?'; $args[] = $folder['id']; $title = $folder['name'];
} else {
    $where .= ' AND c.hide_home=0';
}
$cols = q("SELECT c.*, p.key_web FROM collections c LEFT JOIN photos p ON p.id=c.cover_photo_id
           WHERE $where ORDER BY COALESCE(c.event_date, c.created_at) DESC, c.id DESC", $args)->fetchAll();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?></title>
<link rel="preconnect" href="https://fonts.bunny.net"><link rel="stylesheet" href="<?= e(FONT_LINK) ?>">
<link rel="stylesheet" href="<?= $B ?>/style.css?v=3"></head>
<body class="gallery home" style="<?= e(palette_vars('light') . ';' . type_vars('sans')) ?>">
<?php if (is_admin()) { ?><p class="ownerbar">Viewing your homepage as <b>Owner</b>. <a href="<?= $B ?>/admin.php">Open dashboard to add or edit collections</a></p><?php } ?>
<header class="hometop"><h1><?= e($title) ?></h1></header>
<main class="homegrid"><?php foreach ($cols as $c) { ?>
<a href="<?= $B ?>/g/<?= e($c['slug']) ?>"><div class="thumb"><?php if ($c['key_web'] && $c['password'] === '') { ?><img loading="lazy" src="<?= e(r2_url('GET', $c['key_web'])) ?>" alt=""><?php } ?></div>
<strong><?= e($c['name']) ?></strong><?php if ($c['event_date']) echo '<span>' . e(date('F jS, Y', strtotime($c['event_date']))) . '</span>'; ?>
<?php if ($c['password'] !== '') echo '<span>Private</span>'; ?></a>
<?php } ?></main>
<?php if (!$cols) echo '<p class="empty">No galleries to show yet.</p>'; ?>
<footer class="gfoot"><a href="<?= $B ?>/admin.php"><?= is_admin() ? 'Dashboard' : 'Owner login' ?></a></footer>
</body></html>
