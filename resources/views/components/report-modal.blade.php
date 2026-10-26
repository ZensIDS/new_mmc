@props([
    'report',
    'outlets' => [],
    'cashiers' => [],
    'rafaksis' => [],
    'showPdf' => false,
    'defaultStart' => null,
    'defaultEnd' => null,
])

@php
    $id     = $report['id'];
    $fields = $report['fields'];
    $hasPdf = $showPdf && ! empty($report['pdf_url']);
@endphp

<div id="modal_{{ $id }}" class="modal fade rpt-modal rpt-{{ $report['color'] ?? 'purple' }}" role="dialog"
     data-report-id="{{ $id }}"
     data-max-days="{{ $report['max_days'] ?? '' }}">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <span class="rpt-mico"><i class="fa {{ $report['icon'] ?? 'fa-file-text-o' }}"></i></span>
                <h4 class="modal-title">{{ $report['title'] }}
                    @if (! empty($report['group']))<small>{{ $report['group'] }}</small>@endif
                </h4>
            </div>
            <div class="modal-body">
                <div class="rpt-desc">{{ $report['desc'] }}</div>

                <div class="alert alert-danger rpt-error"></div>

                @if ($report['has_date'])
                    <div class="form-group">
                        <label>Periode</label>
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                            <input type="text" id="range_{{ $id }}" class="form-control rpt-range" readonly
                                   data-start="{{ $defaultStart }}" data-end="{{ $defaultEnd }}">
                        </div>
                        @if (! empty($report['max_days']))
                            <p class="help-block">Maksimal {{ $report['max_days'] }} hari per laporan.</p>
                        @endif
                    </div>
                @else
                    <p class="rpt-nodate"><i class="fa fa-info-circle"></i> Laporan ini tidak memerlukan rentang tanggal.</p>
                @endif

                @if (in_array('outlet', $fields, true))
                    <div class="form-group">
                        <label>Outlet</label>
                        <select id="outlet_{{ $id }}" class="form-control">
                            <option value="">Semua Outlet</option>
                            @foreach ($outlets as $outlet)
                                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if (in_array('kasir', $fields, true))
                    <div class="form-group">
                        <label>Akun Kasir</label>
                        <select id="kasir_{{ $id }}" class="form-control">
                            <option value="">Semua Kasir</option>
                            @foreach ($cashiers as $cashier)
                                <option value="{{ $cashier->id }}">{{ $cashier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if (in_array('rafaksi', $fields, true))
                    <div class="form-group">
                        <label>Periode Rafaksi</label>
                        <select id="promotion_{{ $id }}" class="form-control">
                            <option value="">Semua Rafaksi</option>
                            @foreach ($rafaksis as $rafaksi)
                                <option value="{{ $rafaksi->id }}">{{ $rafaksi->name }} ({{ optional($rafaksi->start_at)->format('d/m/Y') ?? '-' }} - {{ optional($rafaksi->end_at)->format('d/m/Y') ?? '-' }})</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="rpt-loading-note"><i class="fa fa-info-circle"></i> Laporan sedang dibuat. Mohon tunggu dan jangan tutup halaman ini.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                @if ($hasPdf)
                    <button type="button" class="btn btn-success rpt-pdf" data-url="{{ $report['pdf_url'] }}">
                        <i class="fa fa-eye"></i> Preview PDF
                    </button>
                @endif
                <button type="button" class="btn btn-primary rpt-xls" data-url="{{ $report['xls_url'] }}" data-title="{{ $report['title'] }}">
                    <i class="fa fa-file-excel-o"></i> <span>Unduh Excel</span>
                </button>
            </div>
        </div>
    </div>
</div>