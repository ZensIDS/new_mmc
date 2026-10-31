@extends('layouts.master')

@section('title', 'Permintaan Hapus')

@section('container')
    <section class="content-header">
        <h1>Permintaan Hapus <small>menunggu persetujuan superadmin</small></h1>
    </section>

    <section class="content">
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                @foreach (['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'failed' => 'Gagal'] as $key => $label)
                    <li class="{{ $tab === $key ? 'active' : '' }}">
                        <a href="{{ route('deletion-requests.index', ['tab' => $key]) }}">
                            {{ $label }}
                            <span class="label {{ $key === 'pending' ? 'label-warning' : ($key === 'failed' ? 'label-danger' : 'label-default') }}">{{ $counts[$key] ?? 0 }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content">
                <form method="GET" class="form-inline" style="margin-bottom:12px;">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <select name="module" class="form-control input-sm">
                        <option value="">Semua modul</option>
                        @foreach ($modules as $m)
                            <option value="{{ $m }}" {{ request('module') === $m ? 'selected' : '' }}>{{ $m }}</option>
                        @endforeach
                    </select>
                    <select name="requested_by" class="form-control input-sm">
                        <option value="">Semua pemohon</option>
                        @foreach ($requesters as $u)
                            <option value="{{ $u->id }}" {{ (string) request('requested_by') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="from" value="{{ request('from') }}" class="form-control input-sm">
                    <input type="date" name="to" value="{{ request('to') }}" class="form-control input-sm">
                    <button class="btn btn-sm btn-primary"><i class="fa fa-filter"></i> Terapkan</button>
                    <a href="{{ route('deletion-requests.index', ['tab' => $tab]) }}" class="btn btn-sm btn-default">Reset</a>
                </form>

                @php $actionable = in_array($tab, ['pending', 'failed'], true); @endphp

                @if ($actionable && $requests->count())
                    <form id="bulkForm" method="POST" action="{{ route('deletion-requests.bulk-approve') }}"
                          onsubmit="return confirm('Setujui dan HAPUS semua data yang dicentang? Tindakan ini tidak bisa dibatalkan.');">
                        @csrf
                        <div style="margin-bottom:10px;">
                            <input type="text" name="review_note" class="form-control input-sm" style="display:inline-block;width:260px;" placeholder="Catatan (opsional)">
                            <button class="btn btn-sm btn-success"><i class="fa fa-check"></i> Setujui yang dicentang</button>
                        </div>
                    </form>
                @endif

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                @if ($actionable)<th style="width:30px;"><input type="checkbox" id="checkAll"></th>@endif
                                <th>Diajukan</th>
                                <th>Modul</th>
                                <th>Data yang dihapus</th>
                                <th>Pemohon &amp; alasan</th>
                                <th>{{ $tab === 'pending' ? 'Aksi' : 'Keputusan' }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($requests as $r)
                            @php
                                $changed = $actionable && $r->targetChangedSinceRequest();
                                $gone = $actionable && $r->target_type && ! $r->currentTarget();
                            @endphp
                            <tr>
                                @if ($actionable)
                                    <td><input type="checkbox" class="row-check" name="ids[]" value="{{ $r->id }}" form="bulkForm"></td>
                                @endif
                                <td>{{ $r->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $r->module }}</td>
                                <td>
                                    <strong>{{ $r->target_label }}</strong>
                                    @if ($changed)
                                        <div class="text-warning"><i class="fa fa-warning"></i> Data berubah sejak diajukan — periksa dulu sebelum menyetujui.</div>
                                    @endif
                                    @if ($gone)
                                        <div class="text-danger"><i class="fa fa-warning"></i> Data sudah tidak ada (kemungkinan sudah terhapus).</div>
                                    @endif
                                    @if (! empty($r->target_snapshot))
                                        <details>
                                            <summary class="text-muted">Detail</summary>
                                            <table class="table table-condensed" style="margin:5px 0;font-size:12px;">
                                                @foreach ($r->target_snapshot as $k => $v)
                                                    @continue(str_starts_with($k, '_') || $v === null || $v === '')
                                                    <tr><td class="text-muted" style="width:35%;">{{ $k }}</td><td>{{ $v }}</td></tr>
                                                @endforeach
                                            </table>
                                        </details>
                                    @endif
                                </td>
                                <td>
                                    {{ $r->requester?->name ?? '-' }}<br>
                                    <small class="text-muted">{{ $r->reason ?: 'Tanpa alasan' }}</small>
                                </td>
                                <td style="min-width:230px;">
                                    @if ($actionable)
                                        @if ($r->status === 'failed')
                                            <div class="text-danger" style="margin-bottom:6px;"><i class="fa fa-times-circle"></i> Gagal: {{ $r->error_message }}</div>
                                        @endif
                                        <form method="POST" action="{{ route('deletion-requests.approve', $r->id) }}" style="display:inline"
                                              onsubmit="return confirm('Setujui dan HAPUS data ini?');">
                                            @csrf
                                            <input type="text" name="review_note" class="form-control input-sm" placeholder="Catatan (opsional)" style="margin-bottom:5px;">
                                            <button class="btn btn-xs btn-success"><i class="fa fa-check"></i> {{ $r->status === 'failed' ? 'Coba lagi' : 'Setujui' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('deletion-requests.reject', $r->id) }}" style="display:inline"
                                              onsubmit="return confirm('Tolak permintaan ini? Data tetap ada.');">
                                            @csrf
                                            <button class="btn btn-xs btn-danger"><i class="fa fa-times"></i> Tolak</button>
                                        </form>
                                    @else
                                        {{ $r->reviewer?->name ?? '-' }}
                                        <small class="text-muted">{{ optional($r->reviewed_at)->format('d/m/Y H:i') }}</small>
                                        @if ($r->review_note)<br><small>{{ $r->review_note }}</small>@endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted" style="padding:25px;">Tidak ada data.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $requests->links() }}
            </div>
        </div>
    </section>
@endsection

@section('page-script')
<script>
    (function () {
        var all = document.getElementById('checkAll');
        if (!all) return;
        all.addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(function (c) { c.checked = all.checked; });
        });
    })();
</script>
@endsection