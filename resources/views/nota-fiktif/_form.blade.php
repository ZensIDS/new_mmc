@php
    $initItems = old('items');
    if ($initItems === null) {
        $initItems = $nota->exists
            ? $nota->items->map(fn ($i) => [
                'product_id' => $i->product_id, 'nama' => $i->nama, 'satuan' => $i->satuan,
                'qty' => $i->qty + 0, 'harga' => $i->harga + 0,
            ])->values()->all()
            : [];
    }
    $tanggalValue = old('tanggal', optional($nota->tanggal)->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i'));
@endphp

<style>
    .nf-search-wrap { position: relative; }
    .nf-results { position: absolute; z-index: 50; left: 0; right: 0; top: 100%; background: #fff; border: 1px solid #d2d6de;
        max-height: 300px; overflow-y: auto; box-shadow: 0 6px 16px rgba(0,0,0,.12); display: none; }
    .nf-results .nf-opt { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f1f1f1; display: flex; justify-content: space-between; gap: 10px; }
    .nf-results .nf-opt:hover, .nf-results .nf-opt.active { background: #f0f4ff; }
    .nf-results .nf-code { color: #888; font-size: 12px; }
    #nf-items input.form-control { height: 32px; padding: 4px 8px; }
    #nf-items td { vertical-align: middle !important; }
    .nf-sum td { padding: 6px 8px; }
    .nf-big { font-size: 22px; font-weight: 700; }
</style>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul style="margin:0;padding-left:18px">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="callout callout-warning" style="margin-bottom:15px">
    <b>Nota fiktif</b> hanya arsip dokumen: <b>tidak mengurangi stok</b>, tidak masuk penjualan, kas, maupun laporan apa pun.
</div>

<form method="POST" action="{{ $action }}" id="nf-form" autocomplete="off">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <div class="row">
        <div class="col-md-5">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Informasi Nota</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label>No. Nota <small class="text-muted">(kosongkan = otomatis)</small></label>
                        <input type="text" name="code" class="form-control" value="{{ old('code', $nota->code) }}" placeholder="NF-{{ now()->format('Ymd') }}-0001">
                    </div>
                    <div class="form-group">
                        <label>Tanggal &amp; Jam</label>
                        <input type="datetime-local" name="tanggal" class="form-control" value="{{ $tanggalValue }}" required>
                    </div>
                    <div class="form-group">
                        <label>Pilih Outlet <small class="text-muted">(hanya untuk mengisi otomatis, bisa diubah)</small></label>
                        <select id="nf-outlet" class="form-control">
                            <option value="">— isi manual —</option>
                            @foreach ($outlets as $o)
                                <option value="{{ $o->id }}" data-nama="{{ $o->name }}" data-alamat="{{ $o->alamat }}" data-footer="{{ $o->footer ? strip_tags($o->footer) : '' }}">{{ $o->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nama Toko (header nota)</label>
                        <input type="text" name="outlet_nama" id="nf-outlet-nama" class="form-control" value="{{ old('outlet_nama', $nota->outlet_nama) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Alamat Toko</label>
                        <textarea name="outlet_alamat" id="nf-outlet-alamat" class="form-control" rows="2">{{ old('outlet_alamat', $nota->outlet_alamat) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Customer &amp; Pembayaran</h3></div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-sm-6 form-group">
                            <label>Customer</label>
                            <input type="text" name="customer_nama" class="form-control" list="nf-customers" value="{{ old('customer_nama', $nota->customer_nama) }}" placeholder="Ketik / pilih customer">
                            <datalist id="nf-customers">@foreach ($customers as $c)<option value="{{ $c }}">@endforeach</datalist>
                        </div>
                        <div class="col-sm-6 form-group">
                            <label>Kasir</label>
                            <input type="text" name="kasir_nama" class="form-control" value="{{ old('kasir_nama', $nota->kasir_nama) }}">
                        </div>
                        <div class="col-sm-6 form-group">
                            <label>Metode Pembayaran</label>
                            <input type="text" name="metode_bayar" class="form-control" list="nf-payments" value="{{ old('metode_bayar', $nota->metode_bayar) }}" placeholder="Tunai / Transfer / dll">
                            <datalist id="nf-payments"><option value="Tunai">@foreach ($payments as $p)<option value="{{ $p }}">@endforeach</datalist>
                        </div>
                        <div class="col-sm-6 form-group">
                            <label>No. Referensi <small class="text-muted">(opsional)</small></label>
                            <input type="text" name="referensi" class="form-control" value="{{ old('referensi', $nota->referensi) }}">
                        </div>
                        <div class="col-sm-12 form-group">
                            <label>Catatan internal <small class="text-muted">(tidak dicetak)</small></label>
                            <input type="text" name="catatan" class="form-control" value="{{ old('catatan', $nota->catatan) }}">
                        </div>
                        <div class="col-sm-12 form-group">
                            <label>Teks Footer Nota</label>
                            <textarea name="footer" id="nf-footer" class="form-control" rows="2">{{ old('footer', $nota->footer) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="box box-success">
        <div class="box-header with-border"><h3 class="box-title">Barang</h3></div>
        <div class="box-body">
            <div class="row" style="margin-bottom:10px">
                <div class="col-sm-8 nf-search-wrap">
                    <input type="text" id="nf-q" class="form-control input-lg" placeholder="Cari barang (nama / kode) lalu Enter atau klik… bisa scan barcode">
                    <div class="nf-results" id="nf-results"></div>
                </div>
                <div class="col-sm-4">
                    <button type="button" class="btn btn-default btn-lg btn-block" id="nf-add-manual"><i class="fa fa-plus"></i> Barang Manual</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="nf-items">
                    <thead>
                        <tr>
                            <th>Nama Barang</th>
                            <th style="width:90px">Satuan</th>
                            <th style="width:100px">Qty</th>
                            <th style="width:150px">Harga</th>
                            <th style="width:150px" class="text-right">Subtotal</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <p class="text-muted" id="nf-empty">Belum ada barang. Cari barang di atas atau klik “Barang Manual”.</p>

            <div class="row">
                <div class="col-md-5 col-md-offset-7">
                    <table class="table nf-sum">
                        <tr><td>Subtotal</td><td class="text-right" id="nf-subtotal">0</td></tr>
                        <tr><td>Diskon (Rp)</td><td><input type="number" step="any" min="0" name="diskon" id="nf-diskon" class="form-control text-right" value="{{ old('diskon', $nota->diskon ? $nota->diskon + 0 : 0) }}"></td></tr>
                        <tr><td><b>TOTAL</b></td><td class="text-right nf-big" id="nf-total">0</td></tr>
                        <tr><td>Bayar (Rp)</td><td><input type="number" step="any" min="0" name="bayar" id="nf-bayar" class="form-control text-right" value="{{ old('bayar', $nota->exists ? $nota->bayar + 0 : '') }}"></td></tr>
                        <tr><td>Kembali</td><td class="text-right" id="nf-kembali">0</td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="box-footer">
            <a href="{{ route('nota-fiktif.index') }}" class="btn btn-default">Batal</a>
            <button type="submit" class="btn btn-success pull-right"><i class="fa fa-save"></i> Simpan &amp; Lihat Nota</button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var searchUrl = @json(route('nota-fiktif.products'));
    var init = @json(array_values($initItems));
    var bayarTouched = {{ $nota->exists || old('bayar') !== null ? 'true' : 'false' }};
    var tbody = document.querySelector('#nf-items tbody');
    var q = document.getElementById('nf-q');
    var box = document.getElementById('nf-results');
    var results = [], activeIdx = -1, timer = null, seq = 0;

    function fmt(n) { return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(n || 0); }
    function num(v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

    function addRow(it) {
        var k = seq++;
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td><input type="hidden" name="items[' + k + '][product_id]" value="' + esc(it.product_id || '') + '">' +
            '<input type="text" class="form-control" name="items[' + k + '][nama]" value="' + esc(it.nama) + '" required></td>' +
            '<td><input type="text" class="form-control" name="items[' + k + '][satuan]" value="' + esc(it.satuan) + '"></td>' +
            '<td><input type="number" step="any" min="0" class="form-control text-right nf-qty" name="items[' + k + '][qty]" value="' + (it.qty == null ? 1 : it.qty) + '" required></td>' +
            '<td><input type="number" step="any" min="0" class="form-control text-right nf-harga" name="items[' + k + '][harga]" value="' + (it.harga == null ? 0 : it.harga) + '" required></td>' +
            '<td class="text-right nf-sub">0</td>' +
            '<td><button type="button" class="btn btn-xs btn-danger nf-del"><i class="fa fa-times"></i></button></td>';
        tbody.appendChild(tr);
        recalc();
        return tr;
    }

    function recalc() {
        var sub = 0;
        tbody.querySelectorAll('tr').forEach(function (tr) {
            var s = num(tr.querySelector('.nf-qty').value) * num(tr.querySelector('.nf-harga').value);
            tr.querySelector('.nf-sub').textContent = fmt(s);
            sub += s;
        });
        var diskon = Math.min(num(document.getElementById('nf-diskon').value), sub);
        var total = sub - diskon;
        var bayarEl = document.getElementById('nf-bayar');
        if (!bayarTouched) bayarEl.value = total ? total : '';
        var bayar = bayarEl.value === '' ? total : num(bayarEl.value);
        document.getElementById('nf-subtotal').textContent = fmt(sub);
        document.getElementById('nf-total').textContent = fmt(total);
        document.getElementById('nf-kembali').textContent = fmt(Math.max(0, bayar - total));
        document.getElementById('nf-empty').style.display = tbody.children.length ? 'none' : '';
    }

    function addProduct(p) {
        var existing = null;
        tbody.querySelectorAll('tr').forEach(function (tr) {
            if (String(tr.querySelector('input[type=hidden]').value) === String(p.id)) existing = tr;
        });
        if (existing) {
            var qi = existing.querySelector('.nf-qty');
            qi.value = num(qi.value) + 1;
            recalc();
        } else {
            addRow({ product_id: p.id, nama: p.name, satuan: p.satuan, qty: 1, harga: p.harga });
        }
        q.value = ''; hide(); q.focus();
    }

    function hide() { box.style.display = 'none'; results = []; activeIdx = -1; }
    function render() {
        if (!results.length) { box.innerHTML = '<div class="nf-opt text-muted">Tidak ada barang ditemukan</div>'; box.style.display = 'block'; return; }
        box.innerHTML = results.map(function (p, i) {
            return '<div class="nf-opt' + (i === activeIdx ? ' active' : '') + '" data-i="' + i + '"><span>' + esc(p.name) +
                ' <span class="nf-code">' + esc(p.code || '') + '</span></span><b>' + fmt(p.harga) + '</b></div>';
        }).join('');
        box.style.display = 'block';
    }

    q.addEventListener('input', function () {
        clearTimeout(timer);
        var v = q.value.trim();
        if (!v) return hide();
        timer = setTimeout(function () {
            fetch(searchUrl + '?q=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (rows) { if (q.value.trim() !== v) return; results = rows; activeIdx = rows.length ? 0 : -1; render(); })
                .catch(function () { hide(); });
        }, 200);
    });
    q.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); if (results[activeIdx]) addProduct(results[activeIdx]); }
        else if (e.key === 'ArrowDown' && results.length) { e.preventDefault(); activeIdx = (activeIdx + 1) % results.length; render(); }
        else if (e.key === 'ArrowUp' && results.length) { e.preventDefault(); activeIdx = (activeIdx - 1 + results.length) % results.length; render(); }
        else if (e.key === 'Escape') hide();
    });
    box.addEventListener('mousedown', function (e) {
        var el = e.target.closest('.nf-opt[data-i]');
        if (el) { e.preventDefault(); addProduct(results[parseInt(el.dataset.i, 10)]); }
    });
    document.addEventListener('click', function (e) { if (!e.target.closest('.nf-search-wrap')) hide(); });

    document.getElementById('nf-add-manual').addEventListener('click', function () {
        var tr = addRow({ nama: '', satuan: '', qty: 1, harga: 0 });
        tr.querySelector('input[type=text]').focus();
    });
    tbody.addEventListener('input', recalc);
    tbody.addEventListener('click', function (e) {
        var b = e.target.closest('.nf-del');
        if (b) { b.closest('tr').remove(); recalc(); }
    });
    document.getElementById('nf-diskon').addEventListener('input', recalc);
    document.getElementById('nf-bayar').addEventListener('input', function () { bayarTouched = this.value !== ''; recalc(); });

    document.getElementById('nf-outlet').addEventListener('change', function () {
        var o = this.options[this.selectedIndex];
        if (!o.value) return;
        document.getElementById('nf-outlet-nama').value = o.dataset.nama || '';
        document.getElementById('nf-outlet-alamat').value = o.dataset.alamat || '';
        if (o.dataset.footer) document.getElementById('nf-footer').value = o.dataset.footer;
    });

    document.getElementById('nf-form').addEventListener('submit', function (e) {
        if (!tbody.children.length) { e.preventDefault(); alert('Minimal pilih 1 barang.'); }
    });
    // Enter di kolom isian jangan men-submit form tanpa sengaja
    document.getElementById('nf-form').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT') e.preventDefault();
    });

    init.forEach(addRow);
    recalc();
});
</script>