@extends('layouts.master')

@section('title', 'Penjualan')

@section('container')
    <style>
        .pj-filter .form-group { margin-bottom: 10px; }
        .pj-filter label { font-size: 12px; color: #666; margin-bottom: 2px; font-weight: 600; }

        /* Kartu ringkasan */
        .pj-kpis { display: flex; flex-wrap: wrap; margin: 0 -8px 4px; }
        .pj-kpi-col { flex: 1 1 230px; padding: 0 8px; margin-bottom: 16px; }
        .pj-kpi { position: relative; overflow: hidden; height: 100%; min-height: 104px; background: #fff;
            border-radius: 16px; padding: 18px 20px; box-shadow: 0 4px 16px rgba(30, 41, 59, .07);
            border: 1px solid #eef1f6; display: flex; align-items: center; gap: 16px;
            transition: transform .15s ease, box-shadow .15s ease; }
        .pj-kpi:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(30, 41, 59, .12); }
        .pj-ico { flex: 0 0 52px; width: 52px; height: 52px; border-radius: 14px; display: flex;
            align-items: center; justify-content: center; font-size: 22px; }
        .pj-lbl { font-size: 11px; letter-spacing: .6px; text-transform: uppercase; color: #8a94a6; font-weight: 700; }
        .pj-val { font-size: 24px; line-height: 1.25; font-weight: 800; color: #1f2937; margin: 2px 0; white-space: nowrap; }
        .pj-sub { font-size: 11px; color: #8a94a6; line-height: 1.5; }
        .pj-sub b { color: #4b5563; font-weight: 600; }
        .pj-kpi.is-loading .pj-val { color: transparent; background: linear-gradient(90deg, #eef1f6, #f8f9fb, #eef1f6);
            background-size: 200% 100%; animation: pjShimmer 1.2s infinite; border-radius: 6px; width: 120px; height: 28px; }
        @keyframes pjShimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        .pj-kpi.k-blue .pj-ico   { background: #e8f1ff; color: #2563eb; }
        .pj-kpi.k-green .pj-ico  { background: #e6f8ef; color: #16a34a; }
        .pj-kpi.k-amber .pj-ico  { background: #fff4e0; color: #f59e0b; }
        .pj-kpi.k-hero { background: linear-gradient(135deg, #4f46e5 0%, #2563eb 60%, #0ea5e9 100%); border: 0; }
        .pj-kpi.k-hero .pj-ico { background: rgba(255, 255, 255, .2); color: #fff; }
        .pj-kpi.k-hero .pj-lbl, .pj-kpi.k-hero .pj-sub { color: rgba(255, 255, 255, .8); }
        .pj-kpi.k-hero .pj-val { color: #fff; }
        .pj-kpi.k-hero.is-loading .pj-val { background: rgba(255, 255, 255, .25); }
        .pj-kpi::after { content: ''; position: absolute; right: -28px; top: -28px; width: 96px; height: 96px;
            border-radius: 50%; background: rgba(37, 99, 235, .05); }
        .pj-kpi.k-hero::after { background: rgba(255, 255, 255, .1); }

        .pj-chip { display: inline-flex; align-items: center; margin: 0 8px 8px 0; padding: 6px 14px 6px 10px;
            background: #fff; border: 1px solid #e5e9f0; border-radius: 999px; font-size: 12px;
            box-shadow: 0 1px 3px rgba(30, 41, 59, .05); }
        .pj-chip i.dot { width: 8px; height: 8px; border-radius: 50%; background: #2563eb; margin-right: 8px; }
        .pj-chip b { margin-left: 8px; color: #111827; }
        .pj-retur { display: inline-block; padding: 6px 14px; border-radius: 10px; background: #fff7ed;
            color: #9a3412; border: 1px solid #fed7aa; font-size: 12px; margin-bottom: 8px; }

        #penjualanTable td.details-control { cursor: pointer; text-align: center; width: 28px; color: #3c8dbc; }
        #penjualanTable tr.shown td.details-control { color: #dd4b39; }
        #penjualanTable td.num { text-align: right; white-space: nowrap; }
        .pj-detail { padding: 8px 4px; background: #f9fafc; }
        .pj-hint { font-size: 12px; color: #777; margin-top: 4px; }
        </style>

    <section class="content-header">
        <h1>Penjualan <small id="pjPeriode">{{ $filter->periodLabel() }}</small></h1>
    </section>

    <section class="content">
        {{-- Tombol aksi --}}
        <div style="margin-bottom:12px">
            <a href="{{ route('penjualan.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Buat Penjualan Baru</a>
            <a href="{{ route('cashier.history') }}" class="btn btn-info"><i class="fa fa-history"></i> History Kasir</a>
            <a href="{{ route('refundPenjualan.create') }}" class="btn btn-warning"><i class="fa fa-exchange"></i> Retur / Ganti Barang</a>
            <a href="#" id="btnRekap" class="btn btn-success" title="Unduh rekap Excel sesuai filter aktif"><i class="fa fa-file-excel-o"></i> Download Rekap</a>
        </div>

        {{-- Filter --}}
        @php
            $advOpen = (! $bound && $filter->outletId) || $filter->kasirId || $filter->method !== '' || $filter->status !== 'all';
        @endphp
        <div class="box box-primary pj-filter">
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="f-preset">Periode</label>
                            <select id="f-preset" class="form-control">
                                @foreach ($presets as $key => $label)
                                    <option value="{{ $key }}" @selected($filter->preset === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6" id="rangeWrap" style="{{ $filter->preset === 'custom' ? '' : 'display:none' }}">
                        <div class="form-group">
                            <label for="f-range">Rentang tanggal</label>
                            <input type="text" id="f-range" class="form-control" autocomplete="off" readonly
                                   data-start="{{ $filter->start->toDateString() }}" data-end="{{ $filter->end->toDateString() }}">
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-8">
                        <div class="form-group">
                            <label for="f-q">Cari</label>
                            <input type="text" id="f-q" class="form-control" value="{{ $filter->search }}"
                                   placeholder="Invoice, kasir, customer, kode/nama produk">
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div>
                                <button type="button" id="btnApply" class="btn btn-primary"><i class="fa fa-search"></i> Terapkan</button>
                                <button type="button" id="btnReset" class="btn btn-default" title="Kembali ke hari ini"><i class="fa fa-refresh"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <a href="#advFilter" data-toggle="collapse" style="font-size:12px">
                    <i class="fa fa-sliders"></i> Filter Lanjutan
                </a>
                <div id="advFilter" class="collapse {{ $advOpen ? 'in' : '' }}" style="margin-top:10px">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="f-outlet">Outlet</label>
                                <select id="f-outlet" class="form-control select2" style="width:100%" @disabled($bound)>
                                    @unless ($bound)
                                        <option value="">Semua outlet</option>
                                    @endunless
                                    @foreach ($options['outlets'] as $outlet)
                                        <option value="{{ $outlet->id }}" @selected($filter->outletId === (int) $outlet->id)>{{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                                @if ($bound)
                                    <div class="pj-hint">Anda hanya dapat melihat penjualan outlet sendiri.</div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="f-kasir">Akun kasir</label>
                                <select id="f-kasir" class="form-control select2" style="width:100%">
                                    <option value="">Semua kasir</option>
                                    @foreach ($options['kasirs'] as $kasir)
                                        <option value="{{ $kasir->id }}" @selected($filter->kasirId === (int) $kasir->id)>{{ $kasir->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="f-metode">Metode pembayaran</label>
                                <select id="f-metode" class="form-control select2" style="width:100%">
                                    <option value="">Semua metode</option>
                                    <option value="tunai" @selected($filter->method === 'tunai')>Tunai (tanpa metode terdaftar)</option>
                                    @foreach ($options['methods'] as $method)
                                        <option value="{{ $method->id }}" @selected($filter->method === (string) $method->id)>{{ $method->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="f-status">Status</label>
                                <select id="f-status" class="form-control">
                                    <option value="all" @selected($filter->status === 'all')>Semua</option>
                                    <option value="normal" @selected($filter->status === 'normal')>Normal (tanpa retur)</option>
                                    <option value="retur" @selected($filter->status === 'retur')>Ada retur / ganti barang</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kartu ringkasan (mengikuti filter) --}}
        <div class="pj-kpis">
            <div class="pj-kpi-col">
                <div class="pj-kpi k-blue is-loading" id="kTrx">
                    <div class="pj-ico"><i class="fa fa-file-text-o"></i></div>
                    <div>
                        <div class="pj-lbl">Jumlah Transaksi</div>
                        <div class="pj-val" id="sTrx">&nbsp;</div>
                        <div class="pj-sub">nota pada filter ini</div>
                    </div>
                </div>
            </div>
            <div class="pj-kpi-col">
                <div class="pj-kpi k-green is-loading" id="kSub">
                    <div class="pj-ico"><i class="fa fa-shopping-bag"></i></div>
                    <div>
                        <div class="pj-lbl">Total Penjualan</div>
                        <div class="pj-val" id="sSubtotal">&nbsp;</div>
                        <div class="pj-sub">sebelum diskon, promo &amp; voucher</div>
                    </div>
                </div>
            </div>
            <div class="pj-kpi-col">
                <div class="pj-kpi k-amber is-loading" id="kPot">
                    <div class="pj-ico"><i class="fa fa-tags"></i></div>
                    <div>
                        <div class="pj-lbl">Potongan</div>
                        <div class="pj-val" id="sPotongan">&nbsp;</div>
                        <div class="pj-sub" id="sPotonganDetail">diskon + promo + voucher</div>
                    </div>
                </div>
            </div>
            <div class="pj-kpi-col">
                <div class="pj-kpi k-hero is-loading" id="kGrand">
                    <div class="pj-ico"><i class="fa fa-money"></i></div>
                    <div>
                        <div class="pj-lbl">Grand Total</div>
                        <div class="pj-val" id="sGrand">&nbsp;</div>
                        <div class="pj-sub">nilai nota, sebelum retur</div>
                    </div>
                </div>
            </div>
        </div>
        <div style="margin-bottom:12px">
            <div id="sMetode"></div>
            <div id="sRetur"></div>
        </div>

        {{-- Tabel --}}
        <div class="box">
            <div class="box-body table-responsive">
                <table id="penjualanTable" class="table table-bordered table-striped" style="width:100%">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Tanggal</th>
                            <th>Invoice</th>
                            <th>Customer</th>
                            <th>Shift</th>
                            <th class="text-right">Item</th>
                            <th>Metode</th>
                            <th class="text-right">Grand Total</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </section>
@endsection

@section('page-script')
<script>
$(function () {
    var FMT_VIEW = 'DD/MM/YYYY', FMT_API = 'YYYY-MM-DD';
    var LOCALE = {
        format: FMT_VIEW, separator: ' - ',
        applyLabel: 'Terapkan', cancelLabel: 'Batal', customRangeLabel: 'Custom',
        fromLabel: 'Dari', toLabel: 'Sampai', firstDay: 1,
        daysOfWeek: ['Mg', 'Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb'],
        monthNames: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
    };
    var URLS = {
        index:   @json(route('penjualan.index')),
        data:    @json(route('penjualan.data')),
        summary: @json(route('penjualan.summary')),
        rekap:   @json(route('penjualan.rekap')),
        detail:  @json(route('penjualan.detail', ['penjualan' => '__ID__']))
    };
    var BOUND = @json((bool) $bound);

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }
    function rp(n) { return 'Rp. ' + Math.round(Number(n) || 0).toLocaleString('id-ID'); }

    /* ---------- Rentang tanggal (hanya untuk preset "Pilih tanggal") ---------- */
    var $range = $('#f-range');
    var rs = moment($range.data('start'), FMT_API), re = moment($range.data('end'), FMT_API);
    $range.daterangepicker({
        startDate: rs, endDate: re, autoUpdateInput: false, locale: LOCALE
    }).on('apply.daterangepicker', function (ev, picker) {
        $(this).val(picker.startDate.format(FMT_VIEW) + ' - ' + picker.endDate.format(FMT_VIEW));
        applyFilter();
    });
    $range.val(rs.format(FMT_VIEW) + ' - ' + re.format(FMT_VIEW));

    /* ---------- Kumpulkan filter -> parameter (sama untuk tabel, kartu, cetak, URL) ---------- */
    function params() {
        var p = {preset: $('#f-preset').val()};
        if (p.preset === 'custom') {
            var dp = $range.data('daterangepicker');
            p.tanggal_mulai = dp.startDate.format(FMT_API);
            p.tanggal_selesai = dp.endDate.format(FMT_API);
        }
        if (!BOUND && $('#f-outlet').val()) { p.outlet_id = $('#f-outlet').val(); }
        if ($('#f-kasir').val())  { p.kasir_id = $('#f-kasir').val(); }
        if ($('#f-metode').val()) { p.metode = $('#f-metode').val(); }
        if ($('#f-status').val() && $('#f-status').val() !== 'all') { p.status = $('#f-status').val(); }
        var q = $.trim($('#f-q').val());
        if (q) { p.q = q; }
        return p;
    }

    function syncUrl(p) {
        // "Hari ini" tanpa filter lain = URL bersih; selain itu filter tersimpan di URL (bisa di-bookmark).
        var keys = Object.keys(p);
        var clean = keys.length === 1 && p.preset === 'today';
        var url = clean ? URLS.index : URLS.index + '?' + $.param(p);
        if (window.history && history.replaceState) { history.replaceState(null, '', url); }
    }

    /* ---------- Kartu ringkasan ---------- */
    var summaryXhr = null;
    function loading(on) { $('.pj-kpi').toggleClass('is-loading', on); }
    function loadSummary(p) {
        loading(true);
        $('#sTrx, #sSubtotal, #sPotongan, #sGrand').html('&nbsp;');
        $('#sPotonganDetail').text('diskon + promo + voucher');
        $('#sMetode').empty(); $('#sRetur').empty();
        if (summaryXhr) { summaryXhr.abort(); }
        summaryXhr = $.getJSON(URLS.summary, p).done(function (r) {
            loading(false);
            $('#sTrx').text(Number(r.transaksi).toLocaleString('id-ID'));
            $('#sSubtotal').text(rp(r.subtotal));
            $('#sPotongan').text(rp(r.potongan));
            $('#sPotonganDetail').html('Diskon <b>' + rp(r.diskon) + '</b> · Promo <b>' + rp(r.promo) + '</b> · Voucher <b>' + rp(r.voucher) + '</b>');
            $('#sGrand').text(rp(r.grand));
            $('#pjPeriode').text(r.periode);

            var colors = ['#2563eb', '#16a34a', '#f59e0b', '#a855f7', '#ef4444', '#0ea5e9', '#14b8a6'];
            var html = '';
            $.each(r.metode, function (i, m) {
                html += '<span class="pj-chip"><i class="dot" style="background:' + colors[i % colors.length] + '"></i>' +
                    esc(m.metode) + ' <small class="text-muted" style="margin-left:6px">' + m.trx + ' nota</small><b>' + rp(m.total) + '</b></span>';
            });
            $('#sMetode').html(html);

            if (r.retur && r.retur.jumlah > 0) {
                $('#sRetur').html('<span class="pj-retur"><i class="fa fa-exchange"></i> Retur / ganti barang pada nota di periode ini: <b>' + r.retur.jumlah +
                    ' retur</b> (' + r.retur.nota + ' nota) · nilai retur <b>' + rp(r.retur.nilai) +
                    '</b> · barang pengganti <b>' + rp(r.retur.pengganti) + '</b></span>');
            }
        }).fail(function (xhr, status) {
            if (status === 'abort') { return; }
            loading(false);
            $('#sTrx, #sSubtotal, #sPotongan, #sGrand').text('-');
        });
    }

    /* ---------- Tabel (server-side) ---------- */
    if ($.fn.DataTable.isDataTable('#penjualanTable')) { $('#penjualanTable').DataTable().destroy(); }
    var table = $('#penjualanTable').DataTable({
        processing: true,
        serverSide: true,
        searching: false,
        pageLength: 25,
        lengthMenu: [25, 50, 100],
        order: [[1, 'desc']],
        ajax: {
            url: URLS.data,
            data: function (d) { $.extend(d, params()); }
        },
        language: {
            processing: 'Memuat…', lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ nota', infoEmpty: 'Tidak ada nota',
            zeroRecords: 'Tidak ada penjualan pada filter ini.', emptyTable: 'Tidak ada penjualan pada filter ini.',
            paginate: {previous: '‹', next: '›'}
        },
        columns: [
            {data: null, orderable: false, className: 'details-control', defaultContent: '<i class="fa fa-plus-square-o"></i>'},
            {data: 'tanggal', render: function (v) { return esc(v); }},
            {data: 'code', render: function (v) { return '<b>' + esc(v) + '</b>'; }},
            {data: 'customer', render: function (v) { return esc(v); }},
            {data: 'shift', render: function (v) { return esc(v); }},
            {data: 'item_count', orderable: false, className: 'num'},
            {data: 'metode', render: function (v) { return '<span class="label label-default">' + esc(v) + '</span>'; }},
            {data: 'grand', className: 'num', render: function (v) { return '<b>' + rp(v) + '</b>'; }},
            {data: 'aksi_html', orderable: false, className: 'text-nowrap'}
        ]
    });

    $('#penjualanTable tbody').on('click', 'td.details-control', function () {
        var $td = $(this), $tr = $td.closest('tr'), row = table.row($tr);
        if (row.child.isShown()) {
            row.child.hide(); $tr.removeClass('shown');
            $td.find('i').attr('class', 'fa fa-plus-square-o');
            return;
        }
        $tr.addClass('shown');
        $td.find('i').attr('class', 'fa fa-minus-square-o');
        row.child('<div class="text-center text-muted" style="padding:12px">Memuat detail…</div>').show();
        $.getJSON(URLS.detail.replace('__ID__', row.data().id)).done(function (r) {
            row.child(r.html).show();
        }).fail(function () {
            row.child('<div class="text-center text-danger" style="padding:12px">Gagal memuat detail nota.</div>').show();
        });
    });

    /* ---------- Terapkan / Reset ---------- */
    function applyFilter() {
        var p = params();
        syncUrl(p);
        loadSummary(p);
        table.ajax.reload();
    }

    $('#btnApply').on('click', applyFilter);
    $('#f-q').on('keydown', function (e) { if (e.which === 13) { e.preventDefault(); applyFilter(); } });
    $('#f-outlet, #f-kasir, #f-metode, #f-status').on('change', applyFilter);

    $('#f-preset').on('change', function () {
        var custom = $(this).val() === 'custom';
        $('#rangeWrap').toggle(custom);
        if (custom) {
            $range.data('daterangepicker').show();
        } else {
            applyFilter();
        }
    });

    $('#btnReset').on('click', function () {
        $('#f-preset').val('today');
        $('#rangeWrap').hide();
        $('#f-q').val('');
        $('#f-kasir, #f-metode').val('').trigger('change.select2');
        $('#f-status').val('all');
        if (!BOUND) { $('#f-outlet').val('').trigger('change.select2'); }
        applyFilter();
    });

    /* ---------- Download Rekap (Excel, filter aktif) ---------- */
    function fileNameFrom(cd) {
        var m = /filename\*=UTF-8''([^;]+)/i.exec(cd || '');
        if (m) { try { return decodeURIComponent(m[1]); } catch (e) {} }
        m = /filename="?([^";]+)"?/i.exec(cd || '');
        return m ? m[1] : 'Rekap-Penjualan.xlsx';
    }
    $('#btnRekap').on('click', function (e) {
        e.preventDefault();
        var $b = $(this);
        if ($b.hasClass('disabled')) { return; }
        var html = $b.html();
        $b.addClass('disabled').html('<i class="fa fa-spinner fa-spin"></i> Menyiapkan Excel…');

        fetch(URLS.rekap + '?' + $.param(params()), {
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}
        }).then(function (res) {
            if (!res.ok) {
                return res.text().then(function (t) {
                    var msg = 'Gagal membuat rekap (kode ' + res.status + ').';
                    try { msg = JSON.parse(t).message || msg; } catch (err) {}
                    throw new Error(msg);
                });
            }
            var name = fileNameFrom(res.headers.get('Content-Disposition'));
            return res.blob().then(function (blob) { return {blob: blob, name: name}; });
        }).then(function (f) {
            var url = URL.createObjectURL(f.blob);
            var a = document.createElement('a');
            a.href = url; a.download = f.name;
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        }).catch(function (err) {
            alert(err.message || 'Gagal membuat rekap.');
        }).then(function () {
            $b.removeClass('disabled').html(html);
        });
    });

    loadSummary(params());
});
</script>
@endsection