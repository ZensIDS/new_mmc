{{--
    Layout bersama untuk halaman index Laporan Gudang & Laporan Outlet.
    Variabel: $kind, $groups, $notes (array teks info), $outlets, $cashiers, $rafaksis
--}}
@php
    $kindMeta   = config("laporan.kinds.$kind");
    $showPdf    = \App\Support\LaporanCatalog::showPdf();
    $totalCount = collect($groups)->sum(fn ($g) => count($g['reports']));
    $defStart   = now()->startOfMonth();
    $defEnd     = now();
    $outlets    = $outlets ?? collect();
    $cashiers   = $cashiers ?? collect();
    $rafaksis   = $rafaksis ?? collect();
@endphp

@include('laporan._styles')

<section class="content-header">
    <h1>{{ $kindMeta['title'] }} <small>{{ $kindMeta['subtitle'] }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('dashboard') }}"><i class="fa fa-dashboard"></i> Beranda</a></li>
        <li>Laporan</li>
        <li class="active">{{ $kindMeta['title'] }}</li>
    </ol>
</section>

<section class="content rpt-page">

    {{-- Hero: judul + pencarian + ringkasan --}}
    <div class="rpt-hero">
        <div class="rpt-hero-inner">
            <div class="rpt-hero-text">
                <span class="rpt-hero-eyebrow"><i class="fa {{ $kindMeta['icon'] ?? 'fa-bar-chart' }}"></i> Pusat Laporan</span>
                <h2>Pilih laporan, atur periode, unduh dalam hitungan detik.</h2>
                <p>{{ $kindMeta['subtitle'] }}</p>
                <div class="rpt-search">
                    <i class="fa fa-search"></i>
                    <input type="text" id="rpt-search" placeholder="Cari laporan, mis. penjualan, stok, retur..." autocomplete="off">
                </div>
            </div>
            <div class="rpt-hero-stats">
                <div class="rpt-hstat">
                    <small><i class="fa fa-file-text-o"></i> Laporan</small>
                    <strong>{{ $totalCount }} tersedia</strong>
                </div>
                <div class="rpt-hstat">
                    <small><i class="fa fa-calendar"></i> Periode awal</small>
                    <strong>{{ $defStart->format('d/m') }} - {{ $defEnd->format('d/m/Y') }}</strong>
                </div>
                <div class="rpt-hstat">
                    <small><i class="fa fa-file-excel-o"></i> Format</small>
                    <strong>{{ $showPdf ? 'Excel & PDF' : 'Excel (.xlsx)' }}</strong>
                </div>
            </div>
        </div>
    </div>

    @foreach (($notes ?? []) as $note)
        <div class="rpt-note"><i class="fa fa-info-circle"></i> <span>{{ $note }}</span></div>
    @endforeach

    {{-- Tab kategori --}}
    @if (count($groups) > 0)
        <div class="rpt-tabs" role="tablist">
            <span class="rpt-chip active" role="tab" data-cat="all"><i class="fa fa-th-large"></i> Semua <span class="rpt-count">{{ $totalCount }}</span></span>
            @foreach ($groups as $group)
                <span class="rpt-chip" role="tab" data-cat="{{ $group['key'] }}"><i class="fa {{ $group['icon'] }}"></i> {{ $group['title'] }} <span class="rpt-count">{{ count($group['reports']) }}</span></span>
            @endforeach
        </div>
    @endif

    @forelse ($groups as $group)
        <div class="rpt-group rpt-{{ $group['color'] }}" data-cat="{{ $group['key'] }}">
            <div class="rpt-group-head">
                <span class="rpt-group-ico"><i class="fa {{ $group['icon'] }}"></i></span>
                <h3>{{ $group['title'] }}</h3>
                <span class="rpt-group-count">{{ count($group['reports']) }} laporan</span>
            </div>
            <div class="rpt-grid">
                @foreach ($group['reports'] as $report)
                    <div class="rpt-item"
                         data-cat="{{ $group['key'] }}"
                         data-search="{{ \Illuminate\Support\Str::lower($report['title'].' '.$report['desc'].' '.$group['title']) }}">
                        <div class="rpt-card">
                            <div class="rpt-card-head">
                                <span class="rpt-ico"><i class="fa {{ $report['icon'] }}"></i></span>
                                <h4>{{ $report['title'] }}</h4>
                            </div>
                            <p>{{ $report['desc'] }}</p>
                            <div class="rpt-badges">
                                <span class="rpt-badge xls"><i class="fa fa-file-excel-o"></i> Excel</span>
                                @if ($showPdf && $report['pdf_url'])
                                    <span class="rpt-badge pdf"><i class="fa fa-file-pdf-o"></i> PDF</span>
                                @endif
                                @unless ($report['has_date'])
                                    <span class="rpt-badge">Tanpa filter tanggal</span>
                                @endunless
                            </div>
                            <button type="button" class="rpt-go" data-toggle="modal" data-target="#modal_{{ $report['id'] }}">
                                <i class="fa fa-download"></i> Buat Laporan
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="callout callout-warning">
            <h4>Belum ada laporan</h4>
            <p>Akun Anda belum memiliki akses ke laporan pada bagian ini.</p>
        </div>
    @endforelse

    <div id="rpt-empty" class="is-hidden">
        <i class="fa fa-search"></i>
        Tidak ada laporan yang cocok dengan pencarian Anda.<br>
        <a id="rpt-reset">Tampilkan semua laporan</a>
    </div>

    @foreach ($groups as $group)
        @foreach ($group['reports'] as $report)
            <x-report-modal :report="$report" :outlets="$outlets" :cashiers="$cashiers" :rafaksis="$rafaksis"
                            :show-pdf="$showPdf" :default-start="$defStart->format('Y-m-d')" :default-end="$defEnd->format('Y-m-d')" />
        @endforeach
    @endforeach
</section>