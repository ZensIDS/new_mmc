{{-- Dashboard Penjualan (Tahap 3). Data dimuat via AJAX dari route('dashboard.sales-data'). --}}
<style>
    .ds-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:15px; padding:12px 15px;
        background:#fff; border-radius:6px; box-shadow:0 1px 2px rgba(0,0,0,.08); }
    .ds-toolbar .ds-title { font-weight:600; font-size:15px; margin-right:auto; }
    .ds-toolbar .ds-title small { display:block; font-weight:400; color:#888; font-size:12px; }
    .ds-toolbar .form-control { height:34px; }
    #dsRange { min-width:210px; cursor:pointer; background:#fff; }

    .ds-card { position:relative; background:#fff; border-radius:6px; padding:16px 16px 14px 84px; margin-bottom:15px;
        box-shadow:0 1px 2px rgba(0,0,0,.08); min-height:96px; overflow:hidden; }
    .ds-card .ds-ico { position:absolute; left:0; top:0; bottom:0; width:68px; display:flex; align-items:center;
        justify-content:center; color:#fff; font-size:26px; }
    .ds-card .ds-lbl { color:#777; font-size:12px; text-transform:uppercase; letter-spacing:.4px; }
    .ds-card .ds-val { font-size:22px; font-weight:700; line-height:1.25; margin:2px 0; word-break:break-word; }
    .ds-card .ds-sub { font-size:12px; color:#888; }
    .ds-ico.c-green  { background:linear-gradient(135deg,#00a65a,#2ecc71); }
    .ds-ico.c-blue   { background:linear-gradient(135deg,#0073b7,#3c8dbc); }
    .ds-ico.c-purple { background:linear-gradient(135deg,#605ca8,#8e7cc3); }
    .ds-ico.c-orange { background:linear-gradient(135deg,#f39c12,#f5b041); }
    .ds-ico.c-teal   { background:linear-gradient(135deg,#00b3a4,#39cccc); }
    .ds-ico.c-red    { background:linear-gradient(135deg,#dd4b39,#e8786b); }

    .ds-delta { font-size:12px; font-weight:600; }
    .ds-up { color:#00a65a; } .ds-down { color:#dd4b39; } .ds-flat { color:#999; font-weight:400; }

    #dsBody.is-loading { opacity:.45; pointer-events:none; transition:opacity .15s; }
    .ds-chart { min-height:280px; }
    .ds-table td, .ds-table th { vertical-align:middle !important; }
    .ds-num { text-align:right; white-space:nowrap; }
    .ds-note { font-size:12px; color:#888; margin:-5px 0 15px; }
    .ds-empty { color:#999; text-align:center; padding:25px 10px; }
</style>

<div class="ds-toolbar">
    <div class="ds-title">
        <i class="fa fa-line-chart text-aqua"></i> Rekap Penjualan
        <small id="dsPeriodInfo">Memuat data…</small>
    </div>
    @unless($outletBound ?? false)
        <select id="dsOutlet" class="form-control input-sm" style="width:auto; min-width:170px;">
            <option value="">Semua Outlet</option>
            @foreach(($outlets ?? []) as $o)
                <option value="{{ $o->id }}">{{ $o->name }}</option>
            @endforeach
        </select>
    @else
        <span class="label label-info" style="font-size:12px;"><i class="fa fa-lock"></i>
            {{ optional(($outlets ?? collect())->first())->name ?? 'Outlet Anda' }}</span>
    @endunless
    <input type="text" id="dsRange" class="form-control input-sm" readonly>
    <button type="button" id="dsRefresh" class="btn btn-sm btn-default" title="Muat ulang (abaikan cache)">
        <i class="fa fa-refresh"></i>
    </button>
</div>

<div id="dsError" class="alert alert-danger" style="display:none;"></div>

<div id="dsBody">
    {{-- Baris A: kartu --}}
    <div class="row">
        <div class="col-lg-4 col-sm-6">
            <div class="ds-card">
                <div class="ds-ico c-green"><i class="fa fa-money"></i></div>
                <div class="ds-lbl">Omzet Hari Ini</div>
                <div class="ds-val" id="cTodayOmzet">–</div>
                <div class="ds-sub" id="cTodayDelta">&nbsp;</div>
            </div>
        </div>
        <div class="col-lg-4 col-sm-6">
            <div class="ds-card">
                <div class="ds-ico c-blue"><i class="fa fa-shopping-cart"></i></div>
                <div class="ds-lbl">Transaksi Hari Ini</div>
                <div class="ds-val" id="cTodayTrx">–</div>
                <div class="ds-sub">Nota + retur dengan barang pengganti</div>
            </div>
        </div>
        <div class="col-lg-4 col-sm-6">
            <div class="ds-card">
                <div class="ds-ico c-purple"><i class="fa fa-calendar"></i></div>
                <div class="ds-lbl">Omzet Periode</div>
                <div class="ds-val" id="cPeriodOmzet">–</div>
                <div class="ds-sub" id="cPeriodDelta">&nbsp;</div>
            </div>
        </div>
        <div class="col-lg-4 col-sm-6">
            <div class="ds-card">
                <div class="ds-ico c-orange"><i class="fa fa-file-text-o"></i></div>
                <div class="ds-lbl">Transaksi Periode</div>
                <div class="ds-val" id="cPeriodTrx">–</div>
                <div class="ds-sub" id="cPeriodTrxDelta">&nbsp;</div>
            </div>
        </div>
        <div class="col-lg-4 col-sm-6">
            <div class="ds-card">
                <div class="ds-ico c-teal"><i class="fa fa-balance-scale"></i></div>
                <div class="ds-lbl">Rata-rata / Transaksi</div>
                <div class="ds-val" id="cAvg">–</div>
                <div class="ds-sub">Omzet periode ÷ jumlah transaksi</div>
            </div>
        </div>
        <div class="col-lg-4 col-sm-6">
            <div class="ds-card">
                <div class="ds-ico c-red"><i class="fa fa-tags"></i></div>
                <div class="ds-lbl">Total Diskon + Promo + Voucher</div>
                <div class="ds-val" id="cPotongan">–</div>
                <div class="ds-sub" id="cRetur">&nbsp;</div>
            </div>
        </div>
    </div>
    <p class="ds-note">
        <i class="fa fa-info-circle"></i>
        Aturan hitung sama dengan Laporan Penjualan Outlet: barang yang diretur tidak dihitung sebagai penjualan,
        barang pengganti dihitung pada tanggal retur.
    </p>

    {{-- Baris B: grafik --}}
    <div class="row">
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-area-chart"></i> Tren Penjualan <small id="dsTrenInfo"></small></h3></div>
                <div class="box-body"><div id="chartTren" class="ds-chart"></div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="box box-success">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-credit-card"></i> Metode Pembayaran</h3></div>
                <div class="box-body"><div id="chartMetode" class="ds-chart"></div></div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6" id="boxOutlet">
            <div class="box box-info">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-building-o"></i> Penjualan per Outlet</h3></div>
                <div class="box-body"><div id="chartOutlet" class="ds-chart"></div></div>
            </div>
        </div>
        <div class="col-md-6" id="boxJam">
            <div class="box box-warning">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-clock-o"></i> Jam Ramai</h3></div>
                <div class="box-body"><div id="chartJam" class="ds-chart"></div></div>
            </div>
        </div>
    </div>

    {{-- Baris C: ranking --}}
    <div class="row" style="display: none;">
        <div class="col-md-6">
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-trophy"></i> Top 10 Produk Terjual</h3></div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover ds-table">
                        <thead><tr><th style="width:36px">#</th><th>Produk</th><th class="ds-num">Qty</th><th class="ds-num">Omzet</th></tr></thead>
                        <tbody id="tbProduk"></tbody>
                    </table>
                </div>
                <div class="box-footer text-muted"><small>Qty sudah dikurangi retur yang tertaut ke item nota.</small></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-user"></i> Top Kasir</h3></div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover ds-table">
                        <thead><tr><th style="width:36px">#</th><th>Kasir</th><th class="ds-num">Transaksi</th><th class="ds-num">Omzet</th></tr></thead>
                        <tbody id="tbKasir"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-list"></i> Transaksi Terbaru</h3>
                    @if(Route::has('penjualan.index'))
                        <div class="box-tools pull-right"><a href="{{ route('penjualan.index') }}" class="btn btn-xs btn-default">Lihat Semua</a></div>
                    @endif
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover ds-table">
                        <thead><tr><th>Waktu</th><th>Invoice</th><th>Outlet</th><th>Kasir</th><th>Metode</th><th class="ds-num">Total</th></tr></thead>
                        <tbody id="tbTerbaru"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>