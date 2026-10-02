// Admin front end. Loaded once and kept alive: pages change without a full reload,
// so uploads keep running while you move between tabs, settings and collections.

// ---------- Navigation without page reloads ----------
async function nav(url, push = true, opts) {
  let r;
  try { r = await fetch(url, opts); } catch (err) { location.href = url; return; }
  const doc = new DOMParser().parseFromString(await r.text(), 'text/html'), next = doc.getElementById('app');
  if (!next) { location.href = r.url; return; } // not an admin page (for example the login screen)
  document.querySelectorAll('dialog[open]').forEach(d => d.close());
  const app = document.getElementById('app');
  app.innerHTML = next.innerHTML;
  document.body.className = doc.body.className; document.title = doc.title;
  if (push && r.url !== location.href) history.pushState(null, '', r.url);
  app.querySelectorAll('script').forEach(old => { const s = document.createElement('script'); s.textContent = old.textContent; old.replaceWith(s); });
  window.scrollTo(0, 0);
}
const isAdminUrl = u => u.origin === location.origin && /\/admin\.php$/.test(u.pathname) && !u.searchParams.has('logout');
document.addEventListener('click', ev => {
  document.querySelectorAll('details.menu[open]').forEach(d => { if (!d.contains(ev.target)) d.open = false; });
  const a = ev.target.closest('a[href]');
  if (!a || a.target || ev.defaultPrevented || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.button) return;
  const u = new URL(a.href);
  if (!isAdminUrl(u)) return;
  ev.preventDefault(); nav(u.href);
});
document.addEventListener('submit', ev => {
  const f = ev.target;
  if (ev.defaultPrevented || !isAdminUrl(new URL(f.action || location.href, location.href))) return;
  ev.preventDefault();
  const data = new FormData(f, ev.submitter);
  if ((f.method || 'get').toLowerCase() === 'post') nav(f.action || location.href, true, {method: 'POST', body: data});
  else { const u = new URL(f.action || location.href, location.href); u.search = new URLSearchParams(data).toString(); nav(u.href); }
});
window.addEventListener('popstate', () => nav(location.href, false));

// ---------- Uploader ----------
const UP = {jobs: [], nextPos: {}, tab: 'uploading', open: true, limit: 6};
const fmtMB = b => (b / 1048576).toFixed(b < 10485760 ? 2 : 1) + ' MB';
const fmtSpeed = bps => bps > 1048576 ? (bps / 1048576).toFixed(1) + ' MB/s' : Math.round(bps / 1024) + ' KB/s';
function fmtTime(s) {
  if (!isFinite(s) || s <= 0) return '';
  if (s < 60) return ' · less than a minute left';
  if (s < 3600) return ' · about ' + Math.round(s / 60) + ' min left';
  return ' · about ' + (s / 3600).toFixed(1) + ' h left';
}
async function upApi(ctx, a, data) {
  const fd = new FormData(); fd.append('csrf', ctx.csrf); fd.append('cid', ctx.cid);
  for (const k in data) fd.append(k, data[k]);
  const r = await (await fetch(ctx.api + '?a=' + a, {method: 'POST', body: fd})).json();
  if (r.error) throw new Error(r.error);
  return r;
}
function shrink(src, max) {
  const s = Math.min(1, max / Math.max(src.width, src.height)), c = document.createElement('canvas');
  c.width = Math.round(src.width * s); c.height = Math.round(src.height * s);
  c.getContext('2d').drawImage(src, 0, 0, c.width, c.height);
  return c;
}
const toJpeg = c => new Promise(res => c.toBlob(res, 'image/jpeg', 0.85));
// Resizing runs in two background workers; the main-thread version is only a fallback for old browsers.
const SCRIPT_BASE = document.currentScript.src.replace(/admin\.js.*$/, '');
const resizers = [], waiting = new Map(); let resizeSeq = 0;
function makeCopies(file) {
  if (!window.OffscreenCanvas || !window.Worker) return makeCopiesHere(file);
  if (!resizers.length) for (let i = 0; i < 2; i++) {
    const w = new Worker(SCRIPT_BASE + 'upload-worker.js?v=6');
    w.onmessage = ev => { const p = waiting.get(ev.data.id); waiting.delete(ev.data.id); ev.data.error ? p.reject(new Error('Could not read this image')) : p.resolve(ev.data); };
    resizers.push(w);
  }
  return new Promise((resolve, reject) => { const id = ++resizeSeq; waiting.set(id, {resolve, reject}); resizers[id % resizers.length].postMessage({id, file}); });
}
async function makeCopiesHere(file) {
  const bmp = await createImageBitmap(file, {imageOrientation: 'from-image'});
  const width = bmp.width, height = bmp.height, webCanvas = shrink(bmp, 2048); bmp.close();
  return {web: await toJpeg(webCanvas), thumb: await toJpeg(shrink(webCanvas, 600)), width, height};
}
function putOnce(url, body, job, onProgress) {
  return new Promise((resolve, reject) => {
    const x = new XMLHttpRequest(); job.xhrs.push(x);
    x.open('PUT', url);
    if (onProgress) x.upload.onprogress = ev => onProgress(ev.loaded);
    x.onload = () => x.status >= 200 && x.status < 300 ? resolve() : reject(new Error('Storage refused the upload (' + x.status + ')'));
    x.onerror = () => reject(new Error('Connection lost'));
    x.onabort = () => reject(new Error('Stopped'));
    x.send(body);
  });
}
async function put(url, body, job, onProgress) { // one automatic retry on a dropped connection
  try { return await putOnce(url, body, job, onProgress); }
  catch (err) { if (job.stopped || err.message !== 'Connection lost') throw err; return putOnce(url, body, job, onProgress); }
}
// Capture date from a JPEG's camera data (EXIF DateTimeOriginal), as "YYYY-MM-DD HH:MM:SS".
async function takenDate(file) {
  try {
    const v = new DataView(await file.slice(0, 262144).arrayBuffer());
    if (v.getUint16(0) !== 0xFFD8) return '';
    let o = 2;
    while (o + 4 < v.byteLength) {
      const marker = v.getUint16(o), len = v.getUint16(o + 2);
      if (marker === 0xFFE1 && v.getUint32(o + 4) === 0x45786966) { // "Exif"
        const t = o + 10, le = v.getUint16(t) === 0x4949;
        const u16 = p => v.getUint16(p, le), u32 = p => v.getUint32(p, le);
        const find = (ifd, tag) => { const n = u16(ifd); for (let i = 0; i < n; i++) { const e = ifd + 2 + i * 12; if (u16(e) === tag) return e; } return 0; };
        const ptr = find(t + u32(t + 4), 0x8769); if (!ptr) return '';
        const e = find(t + u32(ptr + 8), 0x9003); if (!e) return '';
        let str = ''; const at = t + u32(e + 8);
        for (let i = 0; i < 19; i++) str += String.fromCharCode(v.getUint8(at + i));
        const m = str.match(/^(\d{4}):(\d\d):(\d\d) (\d\d:\d\d:\d\d)$/);
        return m ? `${m[1]}-${m[2]}-${m[3]} ${m[4]}` : '';
      }
      if ((marker & 0xFF00) !== 0xFF00) return '';
      o += 2 + len;
    }
  } catch (err) {}
  return '';
}

function addUploads(files, ctx) {
  files = files.filter(f => /^image\/(jpeg|png|webp)$/.test(f.type));
  if (!files.length) return;
  if (!UP.jobs.some(j => j.state === 'queued' || j.state === 'uploading')) { UP.jobs = []; UP.start = performance.now(); } // new batch
  UP.nextPos[ctx.set] = Math.max(UP.nextPos[ctx.set] || 0, ctx.maxpos);
  for (const file of files) UP.jobs.push({file, ctx: {...ctx}, pos: ++UP.nextPos[ctx.set], state: 'queued', sent: 0, xhrs: [], err: ''});
  UP.tab = 'uploading'; UP.open = true; UP.closed = false; UP.notified = false;
  pump(); drawUp();
}
function pump() {
  while (UP.jobs.filter(j => j.state === 'uploading').length < UP.limit) {
    const job = UP.jobs.find(j => j.state === 'queued');
    if (!job) break;
    runJob(job);
  }
}
async function runJob(job) {
  job.state = 'uploading'; job.sent = 0; job.err = '';
  const f = job.file, ctx = job.ctx;
  try {
    const s = await upApi(ctx, 'sign', {name: f.name});
    // The original starts uploading at once; the smaller copies are made while it travels.
    const orig = put(s.urls.orig, f, job, n => { job.sent = n; drawUp(); });
    orig.catch(() => {});
    const {web, thumb, ...dims} = await makeCopies(f);
    await Promise.all([orig, put(s.urls.web, web, job), put(s.urls.thumb, thumb, job)]);
    const r = await upApi(ctx, 'save', {set_id: ctx.set, filename: f.name, key_orig: s.keys.orig, key_web: s.keys.web, key_thumb: s.keys.thumb,
      width: dims.width, height: dims.height, size: f.size, taken_at: f.lastModified, taken: await takenDate(f), position: job.pos});
    job.state = 'done'; job.sent = f.size;
    showUploaded(job, r.id, URL.createObjectURL(thumb));
  } catch (err) {
    job.xhrs.forEach(x => x.abort());
    job.state = job.stopped ? 'stopped' : 'failed'; job.err = err.message;
  }
  job.xhrs = [];
  pump(); drawUp();
  if (!UP.notified && !UP.jobs.some(j => j.state === 'queued' || j.state === 'uploading')) batchFinished();
}
// When the whole batch has settled, ask the server to send the notification email (if switched on in Settings).
function batchFinished() {
  UP.notified = true;
  const per = new Map();
  UP.jobs.forEach(j => { const e = per.get(j.ctx.cid) || {ctx: j.ctx, done: 0, failed: 0}; j.state === 'done' ? e.done++ : e.failed++; per.set(j.ctx.cid, e); });
  per.forEach(e => { if (e.done) upApi(e.ctx, 'notify', {done: e.done, failed: e.failed}).catch(() => {}); });
}
// A finished photo appears in the grid straight away, if that set is on screen.
function showUploaded(job, id, thumbUrl) {
  const g = window.G, grid = document.getElementById('grid');
  document.querySelectorAll('[data-count="' + job.ctx.set + '"]').forEach(el => el.textContent = +el.textContent + 1);
  if (!g || !grid || g.cid !== job.ctx.cid || g.set !== job.ctx.set || g.find) return;
  const t = document.createElement('label');
  t.className = 'tile'; t.draggable = true; t.dataset.id = id; t.dataset.pos = job.pos; t.title = job.file.name;
  t.innerHTML = '<input type="checkbox"><img alt="">';
  t.firstChild.value = id; t.lastChild.src = thumbUrl;
  const after = g.sort === 'manual' ? [...grid.children].find(c => +c.dataset.pos > job.pos) : null;
  grid.insertBefore(t, after || null);
  const empty = document.querySelector('.emptystate'); if (empty) empty.hidden = true;
}
function stopUploads() {
  UP.jobs.forEach(j => {
    if (j.state === 'queued') { j.state = 'stopped'; j.err = 'Stopped'; }
    if (j.state === 'uploading') { j.stopped = true; j.xhrs.forEach(x => x.abort()); }
  });
  drawUp();
}

let upPanel = null, upQueued = false;
function drawUp() { if (!upQueued) { upQueued = true; requestAnimationFrame(() => { upQueued = false; renderUp(); }); } }
function renderUp() {
  if (!upPanel) {
    upPanel = document.createElement('div'); upPanel.className = 'up';
    upPanel.innerHTML = '<div class="uphead"><div><strong></strong><span></span></div><button type="button" data-up="toggle" title="Show or hide the list"></button><button type="button" data-up="close" title="Stop or close">&times;</button></div>' +
      '<div class="bar"><i></i></div><div class="upbody"><div class="uptabs"></div><ul></ul></div>';
    document.body.append(upPanel);
    upPanel.addEventListener('click', ev => {
      const b = ev.target.closest('[data-up]'); if (!b) return;
      const act = b.dataset.up, busy = UP.jobs.some(j => j.state === 'queued' || j.state === 'uploading');
      if (act === 'toggle') UP.open = !UP.open;
      if (act === 'tab') UP.tab = b.dataset.tab;
      if (act === 'retry') { UP.jobs.forEach(j => { if (j.state === 'failed') { j.state = 'queued'; j.stopped = false; } }); UP.tab = 'uploading'; UP.notified = false; pump(); }
      if (act === 'close') { if (!busy) UP.closed = true; else if (confirm('Stop the upload? Photos that already finished are kept.')) stopUploads(); }
      drawUp();
    });
  }
  const jobs = UP.jobs, by = s => jobs.filter(j => j.state === s);
  const active = [...by('uploading'), ...by('queued')], done = by('done'), failed = [...by('failed'), ...by('stopped')];
  upPanel.hidden = !jobs.length || UP.closed;
  if (upPanel.hidden) return;
  const total = jobs.reduce((s, j) => s + j.file.size, 0), sent = jobs.reduce((s, j) => s + (j.state === 'done' ? j.file.size : j.state === 'uploading' ? j.sent : 0), 0);
  const left = active.reduce((s, j) => s + j.file.size - j.sent, 0), speed = sent / Math.max((performance.now() - UP.start) / 1000, 0.5);
  const head = upPanel.querySelector('.uphead');
  head.querySelector('strong').textContent = active.length ? `Uploading ${jobs.length} items` : `Upload finished: ${done.length} of ${jobs.length} uploaded`;
  head.querySelector('span').textContent = active.length ? `${fmtMB(sent)} / ${fmtMB(total)} · ${fmtSpeed(speed)}${fmtTime(left / speed)}` : (failed.length ? `${failed.length} not uploaded` : 'All photos are in the gallery');
  head.querySelector('[data-up=toggle]').textContent = UP.open ? '⌄' : '⌃';
  upPanel.querySelector('.bar i').style.width = (total ? (sent + failed.reduce((s, j) => s + j.file.size, 0)) / total * 100 : 0).toFixed(1) + '%';
  upPanel.querySelector('.upbody').hidden = !UP.open;
  if (!UP.open) return;
  if (UP.tab === 'failed' && !failed.length) UP.tab = 'uploading';
  const tab = (id, label, n) => n || id !== 'failed' ? `<button type="button" data-up="tab" data-tab="${id}" class="${UP.tab === id ? 'on' : ''}">${label} (${n})</button>` : '';
  upPanel.querySelector('.uptabs').innerHTML = tab('uploading', 'Uploading', active.length) + tab('done', 'Completed', done.length) + tab('failed', 'Not uploaded', failed.length) +
    (UP.tab === 'failed' && by('failed').length + by('stopped').length ? '<button type="button" data-up="retry" class="retry">Retry these</button>' : '');
  const list = UP.tab === 'done' ? done.slice().reverse() : UP.tab === 'failed' ? failed : active;
  const ul = upPanel.querySelector('ul'); ul.textContent = '';
  list.slice(0, 60).forEach(j => {
    const li = document.createElement('li'), name = document.createElement('b'), info = document.createElement('span');
    name.textContent = j.file.name;
    const pct = Math.round(j.sent / j.file.size * 100);
    info.textContent = ({queued: 'Queued', uploading: 'Uploading ' + pct + '%', done: 'Uploaded', failed: 'Failed: ' + j.err, stopped: 'Stopped'})[j.state] + ' · ' + fmtMB(j.file.size);
    li.className = j.state; li.append(name, info); ul.append(li);
  });
  if (list.length > 60) { const li = document.createElement('li'); li.className = 'more'; li.textContent = `and ${list.length - 60} more`; ul.append(li); }
}
window.addEventListener('beforeunload', ev => { if (UP.jobs.some(j => j.state === 'queued' || j.state === 'uploading')) { ev.preventDefault(); ev.returnValue = ''; } });

// ---------- Photos tab (runs each time that tab is shown) ----------
function initPhotos() {
  const G = window.G, grid = document.getElementById('grid');
  const api = (a, data) => upApi(G, a, data), reload = () => nav(location.href, false);

  const fileInput = document.getElementById('files');
  if (fileInput) fileInput.onchange = ev => { addUploads([...ev.target.files], G); ev.target.value = ''; };
  const drop = document.getElementById('drop');
  const hasFiles = ev => ev.dataTransfer && [...ev.dataTransfer.types].includes('Files');
  drop.addEventListener('dragover', ev => { if (hasFiles(ev) && fileInput) { ev.preventDefault(); drop.classList.add('dropping'); } });
  drop.addEventListener('dragleave', () => drop.classList.remove('dropping'));
  drop.addEventListener('drop', ev => {
    if (!hasFiles(ev) || !fileInput) return;
    ev.preventDefault(); drop.classList.remove('dropping'); addUploads([...ev.dataTransfer.files], G);
  });

  // Selection
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
    try { await api(act, data); reload(); } catch (err) { alert(err.message); }
  });

  // Sets and sorting
  const act = document.getElementById('act');
  function submitAct(doWhat, name) { act.elements.do.value = doWhat; act.elements.name.value = name || ''; act.requestSubmit(); }
  document.getElementById('addset').onclick = () => { const n = prompt('Name of the new set'); if (n && n.trim()) submitAct('new_set', n.trim()); };
  const ren = document.getElementById('renset'), del = document.getElementById('delset');
  if (ren) ren.onclick = () => { const n = prompt('Rename this set', G.setName); if (n && n.trim()) submitAct('rename_set', n.trim()); };
  if (del) del.onclick = () => { if (confirm('Delete this set? Its photos move to another set.')) submitAct('delete_set'); };
  const sort = document.getElementById('sort');
  if (sort) sort.onchange = async () => { await api('set', {field: 'sort_mode', value: sort.value}); reload(); };

  // Drag to arrange
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
    try { await api('order', {ids: [...grid.children].map(c => c.dataset.id).join(',')}); if (sort) sort.value = 'manual'; G.sort = 'manual'; }
    catch (err) { alert(err.message); }
  });
  grid.addEventListener('dragend', () => { dragged = null; });
}
