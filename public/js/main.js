/* Emping UMKM - SPA. Semua status/label/warna/aksi dari window.APP_CONFIG (constants.php). */
(() => {
'use strict';
const CFG = window.APP_CONFIG;
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const app = $('#app');
const rupiah = n => 'Rp ' + Number(n).toLocaleString('id-ID');
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
const stInfo = k => CFG.status[k] || {label: k, color: '#6b7280', description: ''};
const badge = (label, color) => `<span class="badge" style="background:${esc(color)}">${esc(label)}</span>`;
const statusBadge = k => badge(stInfo(k).label, stInfo(k).color);
const payBadge = k => { const p = CFG.paymentStatus[k] || {label: k, color: '#6b7280'}; return badge(p.label, p.color); };
const fmtTime = s => s ? new Date(s.replace(' ', 'T')).toLocaleString('id-ID', {day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'}) : '';

const store = {
  get(k, d) { try { return JSON.parse(localStorage.getItem(k)) ?? d; } catch { return d; } },
  set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch {} },
};
const state = {
  products: [], meta: {}, cart: store.get('emping_cart', {}),
  myOrders: store.get('emping_orders', []), csrf: null, admin: false, notifs: [],
  adminFilter: 'all',
};

// ---------- API (envelope seragam {ok,data,meta|error}) ----------
async function api(path, {method = 'GET', body} = {}) {
  const headers = {};
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (state.csrf && method !== 'GET') headers['X-CSRF-Token'] = state.csrf;
  let res, json;
  try {
    res = await fetch(CFG.apiBase + path, {method, headers, credentials: 'same-origin', body: body !== undefined ? JSON.stringify(body) : undefined});
    json = await res.json();
  } catch {
    throw new Error('Tidak dapat terhubung ke server. Pastikan Apache dan MySQL di XAMPP berjalan.');
  }
  if (!json.ok) { const e = new Error(json.error?.message || 'Terjadi kesalahan'); e.code = json.error?.code; throw e; }
  return json;
}
function toast(msg, type = '') {
  const t = document.createElement('div'); t.className = 'toast ' + type; t.textContent = msg;
  $('#toasts').append(t); setTimeout(() => t.remove(), 4200);
}
const fail = e => toast(e.message, 'err');

// ---------- Modal ----------
function openModal(html) { $('#modal').innerHTML = html; $('#modalOverlay').hidden = false; }
function closeModal() { $('#modalOverlay').hidden = true; $('#modal').innerHTML = ''; }
$('#modalOverlay').addEventListener('click', e => { if (e.target.id === 'modalOverlay') closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
function askReason(title) {
  return new Promise(resolve => {
    openModal(`<h3>${esc(title)}</h3><label for="rsn">Alasan</label><textarea id="rsn" rows="3" maxlength="300"></textarea>
      <div class="row" style="margin-top:1rem;justify-content:flex-end"><button class="btn btn-ghost" id="rsnNo">Batal</button><button class="btn btn-danger" id="rsnYes">Kirim</button></div>`);
    $('#rsn').focus();
    $('#rsnNo').onclick = () => { closeModal(); resolve(null); };
    $('#rsnYes').onclick = () => { const v = $('#rsn').value.trim(); if (!v) return toast('Alasan wajib diisi', 'err'); closeModal(); resolve(v); };
  });
}
function confirmBox(text, okLabel = 'Ya') {
  return new Promise(resolve => {
    openModal(`<p>${esc(text)}</p><div class="row" style="justify-content:flex-end"><button class="btn btn-ghost" id="cNo">Tidak</button><button class="btn btn-danger" id="cYes">${esc(okLabel)}</button></div>`);
    $('#cNo').onclick = () => { closeModal(); resolve(false); };
    $('#cYes').onclick = () => { closeModal(); resolve(true); };
  });
}

// ---------- Produk & keranjang ----------
async function loadProducts() {
  const r = await api('products.php');
  state.products = r.data.products; state.meta = r.meta;
  // buang item keranjang yang produknya sudah tidak ada / melebihi stok
  for (const id of Object.keys(state.cart)) {
    const p = state.products.find(x => x.id == id);
    if (!p || p.stok_tersedia < 1) delete state.cart[id]; else state.cart[id] = Math.min(state.cart[id], p.stok_tersedia);
  }
  saveCart();
}
function saveCart() { store.set('emping_cart', state.cart); const n = Object.values(state.cart).reduce((a, b) => a + b, 0); const c = $('#cartCount'); c.textContent = n; c.hidden = n === 0; }
function cartTotal() { return Object.entries(state.cart).reduce((s, [id, q]) => s + (state.products.find(p => p.id == id)?.harga || 0) * q, 0); }
function setQty(id, q) {
  const p = state.products.find(x => x.id == id); if (!p) return;
  q = Math.max(0, Math.min(q, p.stok_tersedia));
  if (q === 0) delete state.cart[id]; else state.cart[id] = q;
  saveCart(); renderCart();
}

// ---------- Halaman ----------
function pageHome() {
  app.innerHTML = `<section class="hero"><h1>Emping gurih, dibuat sesuai pesanan</h1>
    <p>Pesan emping mentah atau matang langsung dari ${esc(CFG.siteName)}. Setiap pesanan dikonfirmasi penjual dan ada batas waktu pembatalan yang jelas, jadi tidak ada kerugian mendadak untuk kedua pihak.</p>
    <div class="row"><a class="btn" href="#/order">Pesan Sekarang</a><a class="btn btn-ghost" href="#/tracking">Lacak Pesanan</a></div></section>
    <div class="grid">
      <div class="card"><h3>1. Pesan</h3><p class="muted">Pilih produk dan isi data. Stok langsung di-reserve untuk Anda.</p></div>
      <div class="card"><h3>2. Konfirmasi penjual</h3><p class="muted">Penjual menerima atau menolak. Pesanan di atas Rp ${state.meta.dp_threshold ? Number(state.meta.dp_threshold).toLocaleString('id-ID') : '200.000'} memerlukan DP ${esc(state.meta.dp_percent ?? 30)}%.</p></div>
      <div class="card"><h3>3. Batas pembatalan</h3><p class="muted">Setelah diterima, Anda punya waktu terbatas untuk membatalkan sebelum produksi dimulai.</p></div>
      <div class="card"><h3>4. Produksi &amp; ambil</h3><p class="muted">Pantau status lewat timeline dan notifikasi.</p></div></div>`;
}

function pageOrder() {
  app.innerHTML = `<h2>Pesan Emping</h2><div class="layout"><div><div class="grid" id="catalog"></div></div>
    <aside class="card sticky"><h3>Keranjang</h3><div id="cart"></div>
    <form id="checkout" novalidate>
      <label for="f_nama">Nama</label><input id="f_nama" maxlength="100" autocomplete="name" required>
      <label for="f_hp">Nomor HP / WhatsApp</label><input id="f_hp" inputmode="tel" placeholder="08123456789" autocomplete="tel" required>
      <label for="f_alamat">Alamat / titik ambil</label><textarea id="f_alamat" rows="2" required></textarea>
      <label for="f_cat">Catatan (opsional)</label><input id="f_cat" maxlength="200">
      <button class="btn" style="width:100%;margin-top:1rem" id="btnCheckout">Buat Pesanan</button></form></aside></div>`;
  $('#catalog').innerHTML = state.products.map(p => `<div class="card"><h3>${esc(p.nama_produk)}</h3>
    <div class="muted small">${esc(p.varian)}</div><p class="small">${esc(p.deskripsi)}</p>
    <div class="row between"><span class="price">${esc(p.harga_formatted)}</span><span class="small muted">Stok ${p.stok_tersedia}</span></div>
    <button class="btn btn-sm" style="margin-top:.6rem" data-add="${p.id}" ${p.stok_tersedia < 1 ? 'disabled' : ''}>${p.stok_tersedia < 1 ? 'Habis' : '+ Keranjang'}</button></div>`).join('') || '<div class="empty">Belum ada produk.</div>';
  $$('[data-add]').forEach(b => b.onclick = () => setQty(b.dataset.add, (state.cart[b.dataset.add] || 0) + 1));
  $('#checkout').onsubmit = submitOrder;
  const saved = store.get('emping_customer', {});
  $('#f_nama').value = saved.nama || ''; $('#f_hp').value = saved.hp || '';
  renderCart();
}
function renderCart() {
  const el = $('#cart'); if (!el) return;
  const ids = Object.keys(state.cart);
  if (!ids.length) { el.innerHTML = '<div class="empty small">Keranjang kosong</div>'; return; }
  const total = cartTotal();
  const dp = total > (state.meta.dp_threshold ?? 200000);
  el.innerHTML = ids.map(id => { const p = state.products.find(x => x.id == id); const q = state.cart[id];
    return `<div class="cart-line"><div><div>${esc(p.nama_produk)}</div><div class="small muted">${esc(p.harga_formatted)}</div></div>
    <div class="qty"><button data-q="${id}" data-d="-1" aria-label="Kurangi">-</button><span>${q}</span><button data-q="${id}" data-d="1" aria-label="Tambah">+</button></div></div>`; }).join('')
    + `<div class="row between" style="margin-top:.6rem"><strong>Total</strong><strong class="price">${rupiah(total)}</strong></div>`
    + (dp ? `<div class="alert alert-info small">Total di atas ${rupiah(state.meta.dp_threshold)}: wajib DP ${state.meta.dp_percent}% (${rupiah(Math.round(total * state.meta.dp_percent / 100))}).</div>` : '');
  $$('[data-q]', el).forEach(b => b.onclick = () => setQty(b.dataset.q, (state.cart[b.dataset.q] || 0) + Number(b.dataset.d)));
}
async function submitOrder(e) {
  e.preventDefault();
  if (!Object.keys(state.cart).length) return toast('Keranjang masih kosong', 'err');
  const body = {nama_pemesan: $('#f_nama').value.trim(), no_hp: $('#f_hp').value.trim(), alamat: $('#f_alamat').value.trim(), catatan: $('#f_cat').value.trim(),
    items: Object.entries(state.cart).map(([product_id, jumlah]) => ({product_id: Number(product_id), jumlah}))};
  const btn = $('#btnCheckout'); btn.disabled = true;
  try {
    const r = await api('orders_create.php', {method: 'POST', body}); const d = r.data;
    store.set('emping_customer', {nama: body.nama_pemesan, hp: body.no_hp});
    state.myOrders = [{code: d.order_code, hp: d.no_hp}, ...state.myOrders.filter(o => o.code !== d.order_code)]; store.set('emping_orders', state.myOrders);
    state.cart = {}; saveCart();
    openModal(`<h3>Pesanan dibuat</h3><p>Kode pesanan Anda:</p><p class="countdown" style="font-size:1.4rem">${esc(d.order_code)}</p>
      <p class="muted small">Simpan kode ini. Pesanan tercatat otomatis di menu "Pesanan Saya" pada perangkat ini.</p>
      ${d.dp_required ? `<div class="alert alert-info">Pesanan ini wajib DP <strong>${esc(d.dp_formatted)}</strong>. Setelah penjual menerima, transfer dan kirim bukti di halaman pesanan.</div>` : ''}
      <div class="row" style="justify-content:flex-end"><button class="btn" id="goTrack">Lihat Pesanan</button></div>`);
    $('#goTrack').onclick = () => { closeModal(); sessionStorage.setItem('open_code', d.order_code); location.hash = '#/tracking'; };
    await loadProducts(); refreshNotifs();
  } catch (err) { fail(err); await loadProducts().catch(() => {}); renderCart(); } finally { btn.disabled = false; }
}

// ----- Pesanan Saya -----
async function pageTracking() {
  app.innerHTML = `<h2>Pesanan Saya</h2><div class="layout"><div id="detail"><div class="empty">Pilih pesanan atau cari dengan kode.</div></div>
    <aside><div class="card"><h3>Cari pesanan</h3><form id="lookup"><label for="l_code">Kode pesanan</label><input id="l_code" placeholder="EPG-20261009-ABC123">
    <label for="l_hp">Nomor HP</label><input id="l_hp" inputmode="tel"><button class="btn" style="width:100%;margin-top:.8rem">Cari</button></form></div>
    <div class="card" style="margin-top:1rem"><h3>Tersimpan di perangkat ini</h3><div id="myList"></div></div></aside></div>`;
  $('#lookup').onsubmit = e => { e.preventDefault(); showOrder($('#l_code').value.trim(), $('#l_hp').value.trim(), true); };
  renderMyList();
  const open = sessionStorage.getItem('open_code'); sessionStorage.removeItem('open_code');
  const first = state.myOrders.find(o => o.code === open) || state.myOrders[0];
  if (first) showOrder(first.code, first.hp);
}
function renderMyList() {
  const el = $('#myList'); if (!el) return;
  el.innerHTML = state.myOrders.length ? state.myOrders.map(o => `<div class="cart-line"><a href="#" data-open="${esc(o.code)}">${esc(o.code)}</a></div>`).join('') : '<div class="small muted">Belum ada.</div>';
  $$('[data-open]', el).forEach(a => a.onclick = e => { e.preventDefault(); const o = state.myOrders.find(x => x.code === a.dataset.open); showOrder(o.code, o.hp); });
}
let currentOrder = null;
async function showOrder(code, hp, remember = false) {
  const el = $('#detail'); if (!el) return;
  if (!currentOrder || currentOrder.order_code !== code) el.innerHTML = '<div class="spinner">Memuat...</div>';
  try {
    const r = await api('orders_track.php', {method: 'POST', body: {code, no_hp: hp}});
    currentOrder = r.data.order;
    if (remember && !state.myOrders.some(o => o.code === currentOrder.order_code)) { state.myOrders.unshift({code: currentOrder.order_code, hp: currentOrder.no_hp}); store.set('emping_orders', state.myOrders); renderMyList(); }
    renderOrder(currentOrder, r.meta);
  } catch (e) { currentOrder = null; el.innerHTML = `<div class="alert alert-bad">${esc(e.message)}</div>`; }
}
function renderOrder(o, meta) {
  const el = $('#detail'); if (!el) return;
  const flow = CFG.mainFlow.filter(s => s !== 'menunggu_verifikasi_dp' || o.dp_required);
  const idx = flow.indexOf(o.status), ended = ['dibatalkan_pelanggan', 'ditolak_penjual'].includes(o.status);
  const stepper = ended ? `<div class="alert alert-bad">${statusBadge(o.status)} ${o.alasan_tolak ? 'Alasan: ' + esc(o.alasan_tolak) : ''}</div>`
    : `<div class="stepper">${flow.map((s, i) => `<div class="step ${i < idx ? 'done' : i === idx ? 'now' : ''}">${esc(stInfo(s).label)}</div>`).join('')}</div>`;
  let cd = '';
  if (o.seconds_left !== null) {
    cd = `<div class="card" style="background:var(--gold-l)"><div class="small muted">Batas pembatalan</div>
      <div class="countdown ${o.cancel_expired ? 'expired' : ''}" data-left="${o.seconds_left}" data-at="${Date.now()}">${o.cancel_expired ? 'Waktu habis' : ''}</div>
      ${o.cancel_expired ? `<div class="small">Batas waktu lewat. Untuk perubahan hubungi penjual via WhatsApp.</div>` : ''}</div>`;
  }
  const wa = `https://wa.me/${esc(meta.seller_phone || CFG.sellerPhone)}?text=${encodeURIComponent('Halo, saya mau tanya pesanan ' + o.order_code)}`;
  const dpBox = o.dp_required ? `<div class="alert alert-info"><strong>DP wajib: ${esc(o.dp_formatted)}</strong> (status: ${payBadge(o.status_pembayaran)})
      ${meta.bank_info ? `<div class="small">Transfer ke: ${esc(meta.bank_info)}</div>` : ''}
      ${o.can_upload_dp ? `<form id="dpForm"><label for="dp_ref">No. referensi transfer</label><input id="dp_ref" maxlength="100"><label for="dp_note">Catatan</label><input id="dp_note" maxlength="200"><button class="btn btn-sm" style="margin-top:.6rem">Kirim bukti DP</button></form>` : ''}</div>` : '';
  el.innerHTML = `<div class="card"><div class="row between"><div><h3>${esc(o.order_code)}</h3><div class="small muted">Dibuat ${esc(fmtTime(o.waktu_dibuat))}</div></div>${statusBadge(o.status)}</div>
    ${o.reminder ? '<div class="alert alert-warn">Batas pembatalan hampir habis.</div>' : ''}
    ${stepper}<p class="small muted">${esc(stInfo(o.status).description)}</p>${cd}${dpBox}
    <h4>Item</h4>${o.items.map(i => `<div class="cart-line"><span>${esc(i.nama_produk_snap)} ${esc(i.varian_snap || '')} x ${i.jumlah}</span><span>${rupiah(i.subtotal)}</span></div>`).join('')}
    <div class="row between" style="margin-top:.5rem"><strong>Total</strong><strong class="price">${esc(o.total_formatted)}</strong></div>
    <h4>Timeline</h4><ul class="timeline">${o.timeline.map(t => `<li><strong>${esc(stInfo(t.status_baru).label)}</strong><div class="small">${esc(t.keterangan || '')}</div><div class="small muted">${esc(fmtTime(t.waktu_perubahan))}</div></li>`).join('')}</ul>
    <div class="row"><button class="btn btn-danger" id="btnCancel" ${o.can_cancel ? '' : 'disabled'}>Batalkan Pesanan</button><a class="btn btn-ghost" href="${wa}" target="_blank" rel="noopener">WhatsApp Penjual</a></div>
    ${!o.can_cancel && !ended ? `<div class="small muted" style="margin-top:.4rem">${o.status === 'diproduksi' || o.status === 'siap_kirim' ? 'Pesanan sudah diproduksi dan tidak dapat dibatalkan.' : o.cancel_expired ? 'Batas waktu pembatalan sudah lewat.' : ''}</div>` : ''}</div>`;
  tick();
  const bc = $('#btnCancel'); if (bc) bc.onclick = async () => {
    if (!await confirmBox('Batalkan pesanan ' + o.order_code + '? Tindakan ini tidak dapat diurungkan.', 'Ya, batalkan')) return;
    try { await api('orders_cancel.php', {method: 'POST', body: {code: o.order_code, no_hp: o.no_hp}}); toast('Pesanan dibatalkan', 'ok'); loadProducts(); showOrder(o.order_code, o.no_hp); refreshNotifs(); } catch (e) { fail(e); showOrder(o.order_code, o.no_hp); }
  };
  const df = $('#dpForm'); if (df) df.onsubmit = async e => {
    e.preventDefault();
    try { await api('payments_upload.php', {method: 'POST', body: {code: o.order_code, no_hp: o.no_hp, no_referensi: $('#dp_ref').value, bukti_catatan: $('#dp_note').value}}); toast('Bukti terkirim', 'ok'); showOrder(o.order_code, o.no_hp); } catch (er) { fail(er); }
  };
}
// Hitung mundur: server memberi sisa detik, klien hanya menghitung turun dari saat diterima
function tick() {
  $$('.countdown[data-left]').forEach(el => {
    if (el.classList.contains('expired')) return;
    const left = Math.max(0, Number(el.dataset.left) - Math.floor((Date.now() - Number(el.dataset.at)) / 1000));
    const h = String(Math.floor(left / 3600)).padStart(2, '0'), m = String(Math.floor(left % 3600 / 60)).padStart(2, '0'), s = String(left % 60).padStart(2, '0');
    el.textContent = `${h}:${m}:${s}`;
    if (left === 0 && currentOrder) { el.classList.add('expired'); showOrder(currentOrder.order_code, currentOrder.no_hp); } // sinkron ulang dari server
  });
}
setInterval(tick, 1000);

// ----- Admin -----
async function pageAdmin() {
  if (!state.admin) {
    app.innerHTML = `<h2>Admin</h2><div class="card" style="max-width:360px"><form id="loginForm"><label for="pin">PIN admin</label><input id="pin" type="password" inputmode="numeric" autocomplete="current-password"><button class="btn" style="width:100%;margin-top:.8rem">Masuk</button></form></div>`;
    $('#loginForm').onsubmit = async e => { e.preventDefault(); try { const r = await api('login.php', {method: 'POST', body: {pin: $('#pin').value}}); state.csrf = r.data.csrf_token; state.admin = true; toast('Login berhasil', 'ok'); pageAdmin(); refreshNotifs(); } catch (er) { fail(er); } };
    return;
  }
  app.innerHTML = `<div class="row between"><h2>Dashboard Admin</h2><div class="row"><button class="btn btn-ghost btn-sm" id="btnSettings">Pengaturan</button><button class="btn btn-ghost btn-sm" id="btnLogout">Keluar</button></div></div>
    <div id="adminStats"></div><div class="chips" id="adminChips"></div><div id="adminTable"><div class="spinner">Memuat...</div></div>`;
  $('#btnLogout').onclick = async () => { try { await api('logout.php', {method: 'POST', body: {}}); } catch {} state.admin = false; state.csrf = null; pageAdmin(); refreshNotifs(); };
  $('#btnSettings').onclick = openSettings;
  loadAdmin();
}
async function loadAdmin() {
  if (!state.admin || !$('#adminTable')) return;
  try {
    const r = await api('admin_dashboard.php?status=' + encodeURIComponent(state.adminFilter)); const d = r.data;
    $('#adminStats').innerHTML = `<div class="stats"><div class="card stat"><span class="small muted">Perlu konfirmasi</span><b>${d.stats.menunggu_konfirmasi}</b></div>
      <div class="card stat"><span class="small muted">Perlu verifikasi DP</span><b>${d.stats.menunggu_verifikasi_dp}</b></div>
      <div class="card stat"><span class="small muted">Eskalasi</span><b style="color:var(--bad)">${d.escalated}</b></div>
      <div class="card stat"><span class="small muted">Pendapatan selesai</span><b>${esc(d.revenue_formatted)}</b></div></div>`;
    $('#adminChips').innerHTML = [['all', 'Semua']].concat(Object.keys(CFG.status).map(k => [k, CFG.status[k].label])).map(([k, l]) =>
      `<button class="chip ${state.adminFilter === k ? 'active' : ''}" data-f="${k}">${esc(l)}${k !== 'all' ? ' (' + (d.stats[k] || 0) + ')' : ''}</button>`).join('');
    $$('#adminChips [data-f]').forEach(b => b.onclick = () => { state.adminFilter = b.dataset.f; loadAdmin(); });
    $('#adminTable').innerHTML = d.orders.length ? `<div class="table-wrap"><table><thead><tr><th>Kode</th><th>Pemesan</th><th>Item</th><th>Total</th><th>Status</th><th>Bayar</th><th>Batal s/d</th><th>Aksi</th></tr></thead><tbody>${d.orders.map(o => `<tr class="${o.needs_escalation && o.status === 'menunggu_konfirmasi' ? 'esc' : ''}">
      <td><a href="#" data-det="${esc(o.order_code)}" data-hp="${esc(o.no_hp)}">${esc(o.order_code)}</a>${o.needs_escalation && o.status === 'menunggu_konfirmasi' ? '<div class="small" style="color:var(--bad)">Eskalasi</div>' : ''}</td>
      <td>${esc(o.nama_pemesan)}<div class="small muted">${esc(o.no_hp)}</div></td><td class="small">${esc(o.items_summary)}</td><td>${esc(o.total_formatted)}${o.dp_required ? `<div class="small muted">DP ${esc(o.dp_formatted)}</div>` : ''}</td>
      <td>${statusBadge(o.status)}</td><td>${payBadge(o.status_pembayaran)}${o.has_pending_payment ? '<div class="small">bukti masuk</div>' : ''}</td>
      <td class="small">${o.seconds_left !== null ? `<span class="countdown" style="font-size:.95rem" data-left="${o.seconds_left}" data-at="${Date.now()}"></span>` : '-'}</td>
      <td><div class="row">${o.admin_actions.map(a => `<button class="btn btn-sm ${a.startsWith('reject') ? 'btn-danger' : 'btn-ok'}" data-act="${a}" data-id="${o.id}">${esc(CFG.actionLabels[a])}</button>`).join('') || '<span class="muted small">-</span>'}</div></td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Tidak ada pesanan.</div>';
    $$('#adminTable [data-act]').forEach(b => b.onclick = () => doAction(b.dataset.act, Number(b.dataset.id)));
    $$('#adminTable [data-det]').forEach(a => a.onclick = async e => { e.preventDefault(); try { const r2 = await api('orders_track.php', {method: 'POST', body: {code: a.dataset.det, no_hp: a.dataset.hp}}); showAdminDetail(r2.data.order); } catch (er) { fail(er); } });
    tick();
  } catch (e) { if (e.code === 'UNAUTHORIZED') { state.admin = false; pageAdmin(); } else fail(e); }
}
function showAdminDetail(o) {
  openModal(`<div class="row between"><h3>${esc(o.order_code)}</h3>${statusBadge(o.status)}</div><p class="small">${esc(o.nama_pemesan)} &middot; ${esc(o.no_hp)}<br>${esc(o.alamat)}${o.catatan ? '<br>Catatan: ' + esc(o.catatan) : ''}</p>
    ${o.items.map(i => `<div class="cart-line"><span>${esc(i.nama_produk_snap)} x ${i.jumlah}</span><span>${rupiah(i.subtotal)}</span></div>`).join('')}
    ${o.payments.length ? '<h4>Pembayaran</h4>' + o.payments.map(p => `<div class="small">${rupiah(p.jumlah)} &middot; ${esc(p.status)} &middot; ref: ${esc(p.no_referensi || '-')} ${esc(p.bukti_catatan || '')}</div>`).join('') : ''}
    <h4>Timeline</h4><ul class="timeline">${o.timeline.map(t => `<li><strong>${esc(stInfo(t.status_baru).label)}</strong> <span class="small muted">(${esc(t.aktor)})</span><div class="small">${esc(t.keterangan || '')}</div><div class="small muted">${esc(fmtTime(t.waktu_perubahan))}</div></li>`).join('')}</ul>
    <div class="row" style="justify-content:flex-end"><button class="btn btn-ghost" onclick="document.getElementById('modalOverlay').hidden=true">Tutup</button></div>`);
}
async function doAction(action, id) {
  const body = {action, order_id: id};
  if (CFG.actionNeedsReason.includes(action)) { const r = await askReason(CFG.actionLabels[action]); if (!r) return; body.alasan = r; }
  try { const r = await api('admin.php', {method: 'POST', body}); toast(r.meta.message || 'Berhasil', 'ok'); loadAdmin(); refreshNotifs(); } catch (e) { fail(e); loadAdmin(); }
}
async function openSettings() {
  try {
    const s = (await api('admin_settings.php')).data.settings;
    openModal(`<h3>Pengaturan</h3><form id="setForm">
      <label>Batas pembatalan (jam, 1-24)</label><input name="cancel_window_hours" type="number" min="1" max="24" value="${esc(s.cancel_window_hours)}">
      <label>Ambang wajib DP (Rp)</label><input name="dp_threshold" type="number" min="0" value="${esc(s.dp_threshold)}">
      <label>Persentase DP (%)</label><input name="dp_percent" type="number" min="1" max="100" value="${esc(s.dp_percent)}">
      <label>WhatsApp penjual</label><input name="phone_seller" value="${esc(s.phone_seller)}">
      <label>Info rekening</label><input name="bank_info" value="${esc(s.bank_info || '')}">
      <label>PIN baru (kosongkan jika tidak diganti)</label><input name="new_pin" type="password" inputmode="numeric" autocomplete="new-password">
      <div class="row" style="justify-content:flex-end;margin-top:1rem"><button type="button" class="btn btn-ghost" id="setNo">Batal</button><button class="btn">Simpan</button></div></form>`);
    $('#setNo').onclick = closeModal;
    $('#setForm').onsubmit = async e => { e.preventDefault(); const body = Object.fromEntries(new FormData(e.target)); try { await api('admin_settings.php', {method: 'POST', body}); toast('Pengaturan disimpan', 'ok'); closeModal(); } catch (er) { fail(er); } };
  } catch (e) { fail(e); }
}

// ---------- Notifikasi ----------
async function refreshNotifs() {
  try {
    const r = state.admin ? await api('notifications.php') : (state.myOrders.length ? await api('notifications.php', {method: 'POST', body: {orders: state.myOrders.map(o => ({code: o.code, no_hp: o.hp}))}}) : {data: {notifications: []}});
    state.notifs = r.data.notifications;
  } catch { return; }
  const seenKey = state.admin ? 'emping_seen_admin' : 'emping_seen';
  const seen = store.get(seenKey, 0);
  const unread = state.notifs.filter(n => n.kind === 'warn' || (n.time && new Date(n.time.replace(' ', 'T')).getTime() > seen)).length;
  const b = $('#bellBadge'); b.textContent = unread; b.hidden = unread === 0;
  $('#bellList').innerHTML = state.notifs.length ? state.notifs.map(n => `<div class="notif ${n.kind}"><strong>${esc(n.title)}</strong> <span class="small muted">${esc(n.code)}</span><div>${esc(n.text)}</div><small>${esc(fmtTime(n.time))}</small></div>`).join('') : '<div class="empty small">Belum ada notifikasi</div>';
}
$('#bellBtn').onclick = e => {
  e.stopPropagation(); const dd = $('#bellDropdown'); dd.hidden = !dd.hidden; $('#bellBtn').setAttribute('aria-expanded', String(!dd.hidden));
  if (!dd.hidden) { store.set(state.admin ? 'emping_seen_admin' : 'emping_seen', Date.now()); $('#bellBadge').hidden = true; }
};
document.addEventListener('click', e => { if (!e.target.closest('#bellDropdown')) $('#bellDropdown').hidden = true; });
setInterval(refreshNotifs, 30000);

// ---------- Router ----------
$('#navToggle').onclick = () => { const m = $('#navMenu'); m.classList.toggle('open'); $('#navToggle').setAttribute('aria-expanded', String(m.classList.contains('open'))); };
const routes = {home: pageHome, order: pageOrder, tracking: pageTracking, admin: pageAdmin};
function route() {
  const name = (location.hash.replace(/^#\/?/, '') || 'home').split('?')[0];
  const r = routes[name] ? name : 'home';
  $$('#navMenu a').forEach(a => a.classList.toggle('active', a.dataset.route === r));
  $('#navMenu').classList.remove('open'); currentOrder = null; window.scrollTo(0, 0);
  routes[r]();
}
setInterval(() => { if (location.hash === '#/admin' && state.admin && !$('#modalOverlay').offsetParent) loadAdmin(); }, 20000);
window.addEventListener('hashchange', route);

(async function init() {
  saveCart();
  try { const me = await api('me.php'); if (me.data.logged_in) { state.admin = true; state.csrf = me.data.csrf_token; } } catch {}
  try { await loadProducts(); } catch (e) { toast(e.message, 'err'); }
  route(); refreshNotifs();
})();
})();
