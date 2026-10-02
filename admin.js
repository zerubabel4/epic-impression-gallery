// Photos tab: uploads with progress, selection, set management, drag to arrange.
const G = window.G, grid = document.getElementById('grid');

async function api(a, data) {
  const fd = new FormData(); fd.append('csrf', G.csrf); fd.append('cid', G.cid);
  for (const k in data) fd.append(k, data[k]);
  const r = await (await fetch(G.api + '?a=' + a, {method: 'POST', body: fd})).json();
  if (r.error) throw new Error(r.error);
  return r;
}

// ---------- Uploads ----------
// Four photos upload at once. The web-size and thumbnail copies are made in the
// browser; the original goes to storage untouched.
const up = document.getElementById('up'), upTitle = document.getElementById('uptitle'),
      upBar = document.getElementById('upbar'), upSub = document.getElementById('upsub');
let uploading = false;

function shrink(bmp, max) {
  const s = Math.min(1, max / Math.max(bmp.width, bmp.height)), c = document.createElement('canvas');
  c.width = Math.round(bmp.width * s); c.height = Math.round(bmp.height * s);
  c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
  return new Promise(res => c.toBlob(res, 'image/jpeg', 0.85));
}
function put(url, body, onProgress) {
  return new Promise((resolve, reject) => {
    const x = new XMLHttpRequest();
    x.open('PUT', url);
    if (onProgress) x.upload.onprogress = ev => onProgress(ev.loaded);
    x.onload = () => x.status >= 200 && x.status < 300 ? resolve() : reject(new Error('Storage refused the upload (' + x.status + ')'));
    x.onerror = () => reject(new Error('Connection lost'));
    x.send(body);
  });
}
function fmtSpeed(bps) { return bps > 1048576 ? (bps / 1048576).toFixed(1) + ' MB/s' : Math.round(bps / 1024) + ' KB/s'; }
function fmtTime(s) {
  if (!isFinite(s)) return '';
  if (s < 60) return 'less than a minute left';
  if (s < 3600) return 'about ' + Math.round(s / 60) + ' min left';
  return 'about ' + (s / 3600).toFixed(1) + ' h left';
}

async function uploadAll(files) {
  files = files.filter(f => /^image\/(jpeg|png|webp)$/.test(f.type));
  if (!files.length || uploading) return;
  uploading = true; up.hidden = false;
  const total = files.length, totalBytes = files.reduce((s, f) => s + f.size, 0), start = performance.now();
  const sent = new Map(), failed = []; let done = 0;
  const render = () => {
    let bytes = 0; sent.forEach(v => bytes += v);
    const secs = (performance.now() - start) / 1000, speed = bytes / Math.max(secs, 0.5);
    upTitle.textContent = `Uploading ${Math.min(done + 1, total)} of ${total} to "${G.setName}"`;
    upBar.style.width = (totalBytes ? bytes / totalBytes * 100 : 0).toFixed(1) + '%';
    upSub.textContent = `${done} done · ${fmtSpeed(speed)} · ${fmtTime((totalBytes - bytes) / speed)}`;
  };
  const one = async (f, index) => {
    const bmp = await createImageBitmap(f, {imageOrientation: 'from-image'});
    const [web, thumb] = [await shrink(bmp, 2048), await shrink(bmp, 600)];
    const dims = {width: bmp.width, height: bmp.height}; bmp.close();
    const s = await api('sign', {name: f.name});
    await Promise.all([put(s.urls.orig, f, n => { sent.set(f, n); render(); }), put(s.urls.web, web), put(s.urls.thumb, thumb)]);
    await api('save', {set_id: G.set, filename: f.name, key_orig: s.keys.orig, key_web: s.keys.web, key_thumb: s.keys.thumb,
      width: dims.width, height: dims.height, size: f.size, taken_at: f.lastModified, position: G.maxpos + index + 1});
  };
  const queue = files.map((f, i) => [f, i]);
  const worker = async () => {
    while (queue.length) {
      const [f, i] = queue.shift();
      try { await one(f, i); } catch (err) { failed.push(f.name + ': ' + err.message); }
      sent.set(f, f.size); done++; render();
    }
  };
  render();
  await Promise.all([worker(), worker(), worker(), worker()]);
  uploading = false;
  if (failed.length) {
    upTitle.textContent = `${total - failed.length} of ${total} uploaded, ${failed.length} failed`;
    upSub.textContent = 'Reloading…';
    alert('These files failed:\n' + failed.join('\n'));
  }
  location.reload();
}
const fileInput = document.getElementById('files');
if (fileInput) fileInput.onchange = ev => uploadAll([...ev.target.files]);
window.addEventListener('beforeunload', ev => { if (uploading) { ev.preventDefault(); ev.returnValue = ''; } });

// Drop photos from the computer anywhere on the page
const drop = document.getElementById('drop');
const hasFiles = ev => ev.dataTransfer && [...ev.dataTransfer.types].includes('Files');
drop.addEventListener('dragover', ev => { if (hasFiles(ev) && fileInput) { ev.preventDefault(); drop.classList.add('dropping'); } });
drop.addEventListener('dragleave', () => drop.classList.remove('dropping'));
drop.addEventListener('drop', ev => {
  if (!hasFiles(ev) || !fileInput) return;
  ev.preventDefault(); drop.classList.remove('dropping'); uploadAll([...ev.dataTransfer.files]);
});

// ---------- Selection ----------
const selbar = document.getElementById('selbar'), selcount = document.getElementById('selcount');
const picked = () => [...grid.querySelectorAll('input:checked')].map(i => i.value);
function refreshSel() {
  const n = picked().length;
  selbar.hidden = !n; selcount.textContent = n + ' selected';
  grid.classList.toggle('selecting', n > 0);
}
grid.addEventListener('change', refreshSel);
document.getElementById('selall').onclick = () => { grid.querySelectorAll('input').forEach(b => b.checked = true); refreshSel(); };
document.getElementById('selnone').onclick = () => { grid.querySelectorAll('input').forEach(b => b.checked = false); refreshSel(); };
document.querySelectorAll('[data-act]').forEach(b => b.onclick = async () => {
  const ids = picked(), act = b.dataset.act;
  if (act === 'cover' && ids.length !== 1) return alert('Select exactly one photo to use as the cover.');
  if (act === 'delete' && !confirm(`Delete ${ids.length} photo(s)? This cannot be undone.`)) return;
  const data = {ids: ids.join(',')};
  if (act === 'move') data.set_id = document.querySelector('[name=moveto]').value;
  try { await api(act, data); location.reload(); } catch (err) { alert(err.message); }
});

// ---------- Sets and sorting ----------
const act = document.getElementById('act');
function submitAct(doWhat, name) { act.elements.do.value = doWhat; act.elements.name.value = name || ''; act.submit(); }
document.getElementById('addset').onclick = () => { const n = prompt('Name of the new set'); if (n && n.trim()) submitAct('new_set', n.trim()); };
const ren = document.getElementById('renset'), del = document.getElementById('delset');
if (ren) ren.onclick = () => { const n = prompt('Rename this set', G.setName); if (n && n.trim()) submitAct('rename_set', n.trim()); };
if (del) del.onclick = () => { if (confirm('Delete this set? Its photos move to another set.')) submitAct('delete_set'); };
const sort = document.getElementById('sort');
if (sort) sort.onchange = async () => { await api('set', {field: 'sort_mode', value: sort.value}); location.reload(); };

// ---------- Drag to arrange ----------
let dragged = null;
grid.addEventListener('dragstart', ev => { dragged = ev.target.closest('.tile[draggable=true]'); });
grid.addEventListener('dragover', ev => {
  if (!dragged) return;
  ev.preventDefault();
  const over = ev.target.closest('.tile');
  if (!over || over === dragged) return;
  const r = over.getBoundingClientRect();
  grid.insertBefore(dragged, ev.clientX < r.left + r.width / 2 ? over : over.nextSibling);
});
grid.addEventListener('drop', async ev => {
  if (!dragged) return;
  ev.preventDefault(); ev.stopPropagation(); dragged = null;
  try { await api('order', {ids: [...grid.children].map(c => c.dataset.id).join(',')}); if (sort) sort.value = 'manual'; }
  catch (err) { alert(err.message); }
});
grid.addEventListener('dragend', () => { dragged = null; });
