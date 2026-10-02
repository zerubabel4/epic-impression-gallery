<?php
// Client actions inside a gallery: name, favorites, private comments, single photo download.
require __DIR__ . '/lib.php';
$c = q('SELECT * FROM collections WHERE id=?', [(int)($_REQUEST['cid'] ?? 0)])->fetch();
if (!$c || !can_view($c)) { http_response_code(403); exit('Not allowed.'); }
$cid = (int)$c['id'];
$a = $_GET['a'] ?? '';
$photo = function () use ($cid) {
    $p = q('SELECT * FROM photos WHERE id=? AND collection_id=?', [(int)($_REQUEST['id'] ?? 0), $cid])->fetch();
    if (!$p) { http_response_code(404); exit('Photo not found.'); }
    return $p;
};

if ($a === 'download') { // original file, straight from storage
    if (!$c['allow_download'] && !is_admin()) { http_response_code(403); exit('Downloads are switched off for this gallery.'); }
    $p = $photo();
    if (!is_admin()) q('INSERT INTO downloads (collection_id, photo_id, client) VALUES (?,?,?)', [$cid, $p['id'], client_name($cid) ?: 'Guest']);
    $file = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $p['filename']);
    header('Location: ' . r2_url('GET', $p['key_orig'], 300, ['response-content-disposition' => 'attachment; filename="' . $file . '"']));
    exit;
}

header('Content-Type: application/json');
check_csrf();
$fail = function ($msg, $code = 400) { http_response_code($code); exit(json_encode(['error' => $msg])); };
if ($a === 'name') {
    $n = trim(preg_replace('/\s+/', ' ', $_POST['name'] ?? ''));
    if ($n === '' || strlen($n) > 80) $fail('Please enter your name.');
    $_SESSION['client'][$cid] = $n;
    $favs = array_map('intval', q('SELECT photo_id FROM favorites WHERE collection_id=? AND client=?', [$cid, $n])->fetchAll(PDO::FETCH_COLUMN));
    exit(json_encode(['ok' => true, 'name' => $n, 'favs' => $favs]));
}
$name = client_name($cid);
if ($name === '') $fail('name_needed', 401);
$listComments = function ($pid) use ($cid, $name) {
    return q('SELECT client, body, created_at FROM comments WHERE collection_id=? AND photo_id=? AND client=? ORDER BY id', [$cid, $pid, $name])->fetchAll();
};
switch ($a) {
    case 'fav':
        if (!$c['allow_favorite'] && !is_admin()) $fail('Favorites are switched off for this gallery.', 403);
        $p = $photo();
        $on = (bool)q('SELECT id FROM favorites WHERE photo_id=? AND client=?', [$p['id'], $name])->fetch();
        if ($on) q('DELETE FROM favorites WHERE photo_id=? AND client=?', [$p['id'], $name]);
        else q('INSERT INTO favorites (collection_id, photo_id, client) VALUES (?,?,?)', [$cid, $p['id'], $name]);
        echo json_encode(['ok' => true, 'on' => !$on]);
        break;
    case 'comments':
        echo json_encode(['ok' => true, 'comments' => $listComments($photo()['id'])]);
        break;
    case 'comment':
        $p = $photo();
        $body = trim($_POST['body'] ?? '');
        if ($body === '') $fail('Write a comment first.');
        q('INSERT INTO comments (collection_id, photo_id, client, body) VALUES (?,?,?,?)', [$cid, $p['id'], $name, substr($body, 0, 2000)]);
        echo json_encode(['ok' => true, 'comments' => $listComments($p['id'])]);
        break;
    default:
        $fail('Unknown action');
}
