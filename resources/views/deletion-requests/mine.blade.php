@extends('layouts.master')

@section('title', 'Pengajuan Hapus Saya')

@section('container')
    <section class="content-header">
        <h1>Pengajuan Hapus Saya <small>pantau status & alasan keputusan superadmin</small></h1>
    </section>

    <section class="content">
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                @php $all = $counts->sum(); @endphp
                <li class="{{ $tab === 'all' ? 'active' : '' }}">
                    <a href="{{ route('deletion-requests.mine') }}">Semua <span class="label label-default">{{ $all }}</span></a>
                </li>
                @foreach (['pending' => ['Menunggu', 'label-warning'], 'approved' => ['Disetujui', 'label-success'], 'rejected' => ['Ditolak', 'label-danger'], 'failed' => ['Gagal', 'label-danger']] as $key => [$label, $cls])
                    <li class="{{ $tab === $key ? 'active' : '' }}">
                        <a href="{{ route('deletion-requests.mine', ['tab' => $key]) }}">{{ $label }} <span class="label {{ $cls }}">{{ $counts[$key] ?? 0 }}</span></a>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content">
                <form method="GET" class="form-inline" style="margin-bottom:12px;">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control input-sm" placeholder="Cari data (mis. nama produk)">
                    <button class="btn btn-sm btn-primary"><i class="fa fa-search"></i> Cari</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Diajukan</th>
                                <th>Modul / Data</th>
                                <th>Alasan Saya</th>
                                <th>Status</th>
                                <th>Keputusan Superadmin</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($requests as $r)
                                <tr>
                                    <td class="text-nowrap">{{ $r->created_at?->format('d/m/Y H:i') }}</td>
                                    <td><b>{{ $r->module }}</b><br>{{ $r->target_label }}</td>
                                    <td>{{ $r->reason ?: '-' }}</td>
                                    <td class="text-nowrap">
                                        @switch($r->status)
                                            @case('pending') <span class="label label-warning"><i class="fa fa-hourglass-half"></i> Menunggu</span> @break
                                            @case('approved') <span class="label label-success"><i class="fa fa-check"></i> Disetujui &amp; terhapus</span> @break
                                            @case('rejected') <span class="label label-danger"><i class="fa fa-times"></i> Ditolak</span> @break
                                            @default <span class="label label-danger"><i class="fa fa-exclamation-triangle"></i> Gagal dieksekusi</span>
                                        @endswitch
                                    </td>
                                    <td>
                                        @if ($r->status === 'pending')
                                            <span class="text-muted">Belum diproses. Data tetap ada.</span>
                                        @else
                                            <div class="text-muted" style="font-size:12px;">
                                                {{ $r->reviewer?->name ?? '-' }} · {{ $r->reviewed_at?->format('d/m/Y H:i') ?? '-' }}
                                            </div>
                                            @if ($r->review_note)
                                                <div><b>Catatan:</b> {{ $r->review_note }}</div>
                                            @elseif ($r->status === 'rejected')
                                                <div class="text-muted">Ditolak tanpa catatan.</div>
                                            @endif
                                            @if ($r->status === 'failed' && $r->error_message)
                                                <div class="text-red"><b>Penyebab gagal:</b> {{ $r->error_message }}</div>
                                                <div class="text-muted" style="font-size:12px;">Data tetap ada. Superadmin dapat mencoba lagi setelah masalahnya diselesaikan.</div>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">Belum ada pengajuan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $requests->links() }}
            </div>
        </div>
    </section>
@endsection