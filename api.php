<?php
// Admin-only JSON endpoints used by the upload and photo management screen.
require __DIR__ . '/lib.php';
header('Content-Type: application/json');
if (!is_admin()) { http_response_code(403); exit('{"error":"Not logged in"}'); }
check_csrf();

$cid = (int)($_POST['cid'] ?? 0);
$col = q('SELECT * FROM collections WHERE id=?', [$cid])->fetch();
if (!$col) { http_response_code(404); exit('{"error":"Collection not found"}'); }
$ids = array_values(array_filter(array_map('intval', explode(',', $_POST['ids'] ?? ''))));
$in = $ids ? implode(',', $ids) : '0';
$out = ['ok' => true];

switch ($_GET['a'] ?? '') {
    case 'sign': // hand the browser three upload URLs: original, web size, thumbnail
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($_POST['name'] ?? 'photo.jpg'));
        $dir = "c$cid/" . bin2hex(random_bytes(8));
        $out['keys'] = ['orig' => "$dir/$name", 'web' => "$dir/web.jpg", 'thumb' => "$dir/thumb.jpg"];
        foreach ($out['keys'] as $k => $key) $out['urls'][$k] = r2_url('PUT', $key, 3600);
        break;

    case 'save':
        $set = (int)$_POST['set_id'];
        if (!q('SELECT id FROM sets WHERE id=? AND collection_id=?', [$set, $cid])->fetch()) exit('{"error":"Set not found"}');
        foreach (['key_orig', 'key_web', 'key_thumb'] as $k)
            if (strpos($_POST[$k] ?? '', "c$cid/") !== 0) exit('{"error":"Bad file key"}');
        $pos = (int)q('SELECT COALESCE(MAX(position),0)+1 FROM photos WHERE set_id=?', [$set])->fetchColumn();
        $taken = !empty($_POST['taken_at']) ? date('Y-m-d H:i:s', (int)($_POST['taken_at'] / 1000)) : null;
        q('INSERT INTO photos (collection_id,set_id,filename,key_orig,key_web,key_thumb,width,height,size,taken_at,position)
           VALUES (?,?,?,?,?,?,?,?,?,?,?)',
          [$cid, $set, substr($_POST['filename'], 0, 255), $_POST['key_orig'], $_POST['key_web'], $_POST['key_thumb'],
           (int)$_POST['width'], (int)$_POST['height'], (int)$_POST['size'], $taken, $pos]);
        if (!$col['cover_photo_id']) q('UPDATE collections SET cover_photo_id=? WHERE id=?', [db()->lastInsertId(), $cid]);
        break;

    case 'order': // manual arrangement: ids arrive in their new order
        foreach ($ids as $i => $id) q('UPDATE photos SET position=? WHERE id=? AND collection_id=?', [$i + 1, $id, $cid]);
        q("UPDATE collections SET sort_mode='manual' WHERE id=?", [$cid]);
        break;

    case 'delete':
        foreach (q("SELECT * FROM photos WHERE collection_id=? AND id IN ($in)", [$cid]) as $p) delete_photo_files($p);
        q("DELETE FROM photos WHERE collection_id=? AND id IN ($in)", [$cid]);
        if (in_array((int)$col['cover_photo_id'], $ids)) {
            $next = q('SELECT id FROM photos WHERE collection_id=? ORDER BY id LIMIT 1', [$cid])->fetchColumn();
            q('UPDATE collections SET cover_photo_id=? WHERE id=?', [$next ?: null, $cid]);
        }
        break;

    case 'move':
        $set = (int)$_POST['set_id'];
        if (!q('SELECT id FROM sets WHERE id=? AND collection_id=?', [$set, $cid])->fetch()) exit('{"error":"Set not found"}');
        q("UPDATE photos SET set_id=? WHERE collection_id=? AND id IN ($in)", [$set, $cid]);
        break;

    case 'cover':
        if ($ids && q('SELECT id FROM photos WHERE id=? AND collection_id=?', [$ids[0], $cid])->fetch())
            q('UPDATE collections SET cover_photo_id=? WHERE id=?', [$ids[0], $cid]);
        break;

    default:
        http_response_code(400); $out = ['error' => 'Unknown action'];
}
echo json_encode($out);
