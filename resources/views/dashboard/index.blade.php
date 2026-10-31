@extends('layouts.master')

@section('title', 'Dashboard')

@section('container')
    <section class="content-header">
        <h1>
            Selamat Datang, {{ ucfirst(auth()->user()->name) }}!
            <small>{{ ($showSales ?? false) ? 'Rekap penjualan' : 'Ringkasan gudang' }}</small>
        </h1>
        <ol class="breadcrumb">
            <li class="active">Dashboard</li>
        </ol>
    </section>

    <section class="content">
        {{-- Kartu permintaan hapus yang menunggu (khusus superadmin, 4B.6) --}}
        @if (auth()->user()->canApproveDeletion())
            @php $pendingDeletions = rescue(fn () => \App\Models\DeletionRequest::pendingCount(), 0, false); @endphp
            @if ($pendingDeletions > 0)
                <div class="callout callout-warning" style="margin-bottom:15px;">
                    <h4><i class="fa fa-trash-o"></i> {{ $pendingDeletions }} permintaan hapus menunggu persetujuan</h4>
                    <p>Admin mengajukan penghapusan data. <a href="{{ route('deletion-requests.index') }}"><strong>Tinjau sekarang <i class="fa fa-arrow-circle-right"></i></strong></a></p>
                </div>
            @endif
        @endif

        {{-- 1. Penjualan: semua role kecuali admin-gudang (K8); staff-outlet/kasir hanya outlet sendiri (K23) --}}
        @if($showSales ?? false)
            @include('dashboard._sales')
        @endif

        {{-- 2. Kartu permintaan ke gudang khusus staff-outlet --}}
        @if($isStaffOutletDashboard ?? false)
        <div class="row">
            <div class="col-md-6">
                <div class="small-box bg-aqua">
                    <div class="inner">
                        <h3>{{ number_format($outletRequestTotal ?? 0) }}</h3>
                        <p>Total Jumlah Data Outlet Minta Gudang</p>
                    </div>
                    <div class="icon"><i class="fa fa-list-alt"></i></div>
                    <a href="{{ route('request-orders.index') }}" class="small-box-footer">
                        Lihat Detail <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="small-box bg-yellow">
                    <div class="inner">
                        <h3>{{ number_format($outletRequestPending ?? 0) }}</h3>
                        <p>Total Outlet Minta Gudang Status Pending</p>
                    </div>
                    <div class="icon"><i class="fa fa-clock-o"></i></div>
                    <a href="{{ route('request-orders.index') }}" class="small-box-footer">
                        Lihat Detail <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>
        @endif

        {{-- 3. Gudang: dipindah ke bawah. Role penjualan membukanya manual; admin-gudang langsung tampil --}}
        @if($showGudang ?? false)
            @if($showSales ?? false)
            <div class="box box-solid" style="margin-top:5px;">
                <div class="box-body" style="padding:10px 15px;">
                    <button type="button" id="btnToggleGudang" class="btn btn-default btn-sm">
                        <i class="fa fa-cubes"></i> <span>Tampilkan Info Gudang</span>
                        <i class="fa fa-chevron-down" style="margin-left:6px;"></i>
                    </button>
                    <span class="text-muted" style="margin-left:10px;">Stok, pending order, stok kurang, deadline PO supplier</span>
                </div>
            </div>
            @endif
            <div id="blokGudang" style="{{ ($gudangOpen ?? false) ? '' : 'display:none;' }}">
                @include('dashboard._gudang')
            </div>
        @endif
    </section>
@endsection

@section('page-script')
    @if(($showSales ?? false) || ($showGudang ?? false))
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highcharts/10.3.3/highcharts.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highcharts/10.3.3/modules/exporting.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highcharts/10.3.3/modules/accessibility.min.js"></script>
    @endif

    @if($showSales ?? false)
        @include('dashboard._sales-script')
    @endif

    @if($showGudang ?? false)
    <script>
        // Grafik gudang dibuat saat blok pertama kali tampil (Highcharts butuh container terlihat untuk ukuran yang benar).
        var gudangChartsReady = false;
        function initGudangCharts() {
            if (gudangChartsReady) { Highcharts.charts.forEach(function (c) { if (c) { c.reflow(); } }); return; }
            gudangChartsReady = true;
        // Inventory bar chart
        Highcharts.chart('chartInventory', {
            chart: { type: 'column', backgroundColor: 'transparent' },
            title: { text: null },
            credits: { enabled: false },
            xAxis: {
                categories: {!! json_encode($inventoryChart->map(fn($i) => $i->product?->name ?? '—')->toArray()) !!},
                labels: { style: { fontSize: '11px' } }
            },
            yAxis: { min: 0, title: { text: 'Qty Gudang' } },
            plotOptions: {
                column: {
                    colorByPoint: true,
                    dataLabels: { enabled: true }
                }
            },
            tooltip: {
                formatter: function() {
                    return '<b>' + this.x + '</b><br/>Qty: <b>' + Highcharts.numberFormat(this.y, 0) + '</b>';
                }
            },
            series: [{
                name: 'Stok Gudang',
                data: {!! json_encode($inventoryChart->map(fn($i) => (int)$i->total_qty)->toArray()) !!}
            }],
            legend: { enabled: false }
        });

        // Top products pie chart
        Highcharts.chart('chartTopProducts', {
            chart: { type: 'pie', backgroundColor: 'transparent' },
            title: { text: null },
            credits: { enabled: false },
            tooltip: {
                pointFormat: '{series.name}: <b>{point.y} unit</b> ({point.percentage:.1f}%)'
            },
            plotOptions: {
                pie: {
                    allowPointSelect: true,
                    cursor: 'pointer',
                    innerSize: '40%',
                    dataLabels: {
                        enabled: true,
                        format: '<b>{point.name}</b>: {point.percentage:.1f}%',
                        style: { fontSize: '11px' }
                    }
                }
            },
            series: [{
                name: 'Unit Terkirim',
                colorByPoint: true,
                data: {!! json_encode(
                    $topProducts->map(fn($i) => [
                        'name' => $i->product?->name ?? '—',
                        'y'    => (int)$i->total_qty,
                    ])->toArray()
                ) !!}
            }]
        });
        }

        function showGudang() {
            $('#blokGudang').show();
            $('#btnToggleGudang span').text('Sembunyikan Info Gudang');
            $('#btnToggleGudang .fa-chevron-down').removeClass('fa-chevron-down').addClass('fa-chevron-up');
            initGudangCharts();
        }
        function hideGudang() {
            $('#blokGudang').hide();
            $('#btnToggleGudang span').text('Tampilkan Info Gudang');
            $('#btnToggleGudang .fa-chevron-up').removeClass('fa-chevron-up').addClass('fa-chevron-down');
        }

        $(function () {
            $('#btnToggleGudang').on('click', function () {
                $('#blokGudang').is(':visible') ? hideGudang() : showGudang();
            });

            // Kartu "Stok Kurang" mengarah ke widget di dalam blok gudang: buka dulu bila tersembunyi.
            $(document).on('click', 'a[href="#widgetLowStock"]', function (e) {
                if (!$('#blokGudang').is(':visible')) { showGudang(); }
                var t = document.getElementById('widgetLowStock');
                if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth' }); }
            });

            @if($gudangOpen ?? false)
                initGudangCharts();
            @endif
        });
    </script>
    @endif
@endsection