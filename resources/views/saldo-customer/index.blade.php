@extends('layouts.master')

@section('title', 'Saldo Customer')

@section('container')
    <section class="content-header">
        <h1>Saldo Customer <small>catatan mutasi</small></h1>
    </section>

    <section class="content">
        <div class="callout callout-info">
            Saldo di menu ini hanya <b>catatan</b>. Tidak dipakai untuk transaksi di kasir dan tidak masuk laporan penjualan.
            Top up dan pengurangan dicatat manual oleh superadmin beserta catatannya.
        </div>

        <div class="row">
            <div class="col-md-6 col-sm-6 col-xs-12">
                <div class="info-box">
                    <span class="info-box-icon bg-green"><i class="fa fa-money"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Saldo Semua Customer</span>
                        <span class="info-box-number">@currency($totalBalance)</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-sm-6 col-xs-12">
                <div class="info-box">
                    <span class="info-box-icon bg-aqua"><i class="fa fa-users"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Customer yang Punya Saldo</span>
                        <span class="info-box-number">{{ number_format($walletCount, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="box">
            <div class="box-header with-border">
                <form method="GET" class="form-inline">
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cari nama / no. telp / alamat">
                    <select name="saldo" class="form-control">
                        <option value="semua" {{ $filter === 'semua' ? 'selected' : '' }}>Semua customer</option>
                        <option value="ada" {{ $filter === 'ada' ? 'selected' : '' }}>Hanya yang punya saldo</option>
                    </select>
                    <button class="btn btn-primary"><i class="fa fa-search"></i> Cari</button>
                    @if ($q !== '' || $filter !== 'semua')
                        <a href="{{ route('saldo-customer.index') }}" class="btn btn-default">Reset</a>
                    @endif
                </form>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-bordered table-striped table-hover" style="margin:0">
                    <thead>
                        <tr>
                            <th style="width:50px">No</th>
                            <th>Nama</th>
                            <th>No. Telp</th>
                            <th>Alamat</th>
                            <th class="text-right">Saldo</th>
                            <th style="width:260px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            @php $saldo = (float) ($customer->wallet?->balance ?? 0); @endphp
                            <tr>
                                <td>{{ $customers->firstItem() + $loop->index }}</td>
                                <td><b>{{ $customer->name }}</b></td>
                                <td>{{ $customer->no_telp ?: '—' }}</td>
                                <td>{{ $customer->alamat ?: '—' }}</td>
                                <td class="text-right" data-order="{{ $saldo }}">
                                    <b class="{{ $saldo > 0 ? 'text-green' : 'text-muted' }}">@currency($saldo)</b>
                                </td>
                                <td>
                                    @foreach ([['topup', 'success', 'plus', 'Top Up'], ['pengurangan', 'warning', 'minus', 'Kurangi']] as [$tp, $warna, $ikon, $label])
                                        <button type="button" class="btn btn-xs btn-{{ $warna }}" data-wl-open
                                            data-type="{{ $tp }}"
                                            data-id="{{ $customer->id }}"
                                            data-name="{{ $customer->name }}"
                                            data-phone="{{ $customer->no_telp }}"
                                            data-saldo="{{ $saldo }}"
                                            data-action="{{ route($tp === 'topup' ? 'saldo-customer.topup.store' : 'saldo-customer.kurangi.store', $customer) }}">
                                            <i class="fa fa-{{ $ikon }}"></i> {{ $label }}
                                        </button>
                                    @endforeach
                                    @if (Route::has('saldo-customer.mutasi'))
                                        <a href="{{ route('saldo-customer.mutasi', $customer) }}" class="btn btn-xs btn-info"><i class="fa fa-list"></i> Mutasi</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted" style="padding:30px">Tidak ada customer yang cocok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="box-footer">{{ $customers->links() }}</div>
        </div>
    </section>

    {{-- ============ POPUP TOP UP / PENGURANGAN ============ --}}
    <style>
        #modalSaldo .modal-dialog { max-width: 440px; margin-top: 60px; }
        #modalSaldo .modal-content { border: 0; border-radius: 14px; overflow: hidden; box-shadow: 0 18px 50px rgba(0,0,0,.28); }
        #modalSaldo .wl-head { padding: 20px 22px 18px; color: #fff; position: relative; }
        #modalSaldo .wl-head.is-topup { background: linear-gradient(135deg, #00a65a, #1ec98b); }
        #modalSaldo .wl-head.is-pengurangan { background: linear-gradient(135deg, #e08e0b, #f6b94c); }
        #modalSaldo .wl-close { position: absolute; top: 12px; right: 16px; color: #fff; opacity: .8; font-size: 24px; line-height: 1; background: none; border: 0; }
        #modalSaldo .wl-close:hover { opacity: 1; }
        #modalSaldo .wl-ico { width: 44px; height: 44px; border-radius: 50%; background: rgba(255,255,255,.22); display: inline-block; text-align: center; line-height: 44px; font-size: 20px; margin-right: 12px; float: left; }
        #modalSaldo .wl-title { margin: 0; font-size: 18px; font-weight: 700; }
        #modalSaldo .wl-sub { margin: 2px 0 0; opacity: .92; font-size: 13px; }
        #modalSaldo .wl-saldo { background: rgba(255,255,255,.18); border-radius: 10px; padding: 8px 14px; margin-top: 14px; display: flex; justify-content: space-between; align-items: center; font-size: 12px; }
        #modalSaldo .wl-saldo b { font-size: 18px; }
        #modalSaldo .modal-body { padding: 20px 22px 6px; }
        #modalSaldo .wl-amount .input-group-addon { font-weight: 700; background: #f4f6f9; border-radius: 10px 0 0 10px; }
        #modalSaldo .wl-amount .form-control { font-size: 26px; font-weight: 700; height: 56px; border-radius: 0 10px 10px 0; }
        #modalSaldo .wl-chips { margin: 8px 0 4px; }
        #modalSaldo .wl-chip { border: 1px solid #d9dee5; background: #fff; border-radius: 20px; padding: 2px 11px; font-size: 12px; margin: 0 4px 4px 0; cursor: pointer; color: #444; }
        #modalSaldo .wl-chip:hover { background: #f1f4f8; border-color: #b9c1cc; }
        #modalSaldo .wl-preview { min-height: 22px; font-size: 13px; font-weight: 600; margin: 2px 0 12px; }
        #modalSaldo .form-control { border-radius: 8px; }
        #modalSaldo .modal-footer { border: 0; padding: 8px 22px 20px; }
        #modalSaldo .modal-footer .btn { border-radius: 8px; padding: 8px 18px; font-weight: 600; }
        #modalSaldo .wl-summary { background: #f7f9fb; border-radius: 12px; padding: 14px 16px; margin-bottom: 12px; }
        #modalSaldo .wl-summary .row-s { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; }
        #modalSaldo .wl-summary .row-s span:first-child { color: #7a8594; }
        #modalSaldo .wl-big { font-size: 22px; font-weight: 800; text-align: center; margin: 4px 0 10px; }
        #modalSaldo .wl-warn { font-size: 12px; color: #8a6d3b; background: #fcf8e3; border-radius: 8px; padding: 8px 12px; margin-bottom: 12px; }
    </style>

    <div class="modal fade" id="modalSaldo" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="wl-form" method="POST" action="#" autocomplete="off" novalidate>
                    @csrf
                    <input type="hidden" name="token" id="wl-token">
                    <input type="hidden" name="_open" id="wl-open">

                    <div class="wl-head" id="wl-head">
                        <button type="button" class="wl-close" data-dismiss="modal" aria-label="Tutup">&times;</button>
                        <span class="wl-ico"><i class="fa" id="wl-icon"></i></span>
                        <h4 class="wl-title" id="wl-title"></h4>
                        <p class="wl-sub" id="wl-customer"></p>
                        <div class="wl-saldo"><span>Saldo saat ini</span><b id="wl-saldo"></b></div>
                    </div>

                    {{-- Langkah 1: isi data --}}
                    <div id="wl-step1">
                        <div class="modal-body">
                            <div class="alert alert-danger" id="wl-error" style="display:none;border-radius:8px"></div>

                            <div class="form-group" id="g-amount">
                                <label for="wl-amount">Nominal <span class="text-danger">*</span></label>
                                <div class="input-group wl-amount">
                                    <span class="input-group-addon">Rp</span>
                                    <input type="text" id="wl-amount" name="amount" class="form-control" inputmode="numeric" placeholder="0">
                                </div>
                                <div class="wl-chips" id="wl-chips"></div>
                                <div class="wl-preview" id="wl-preview"></div>
                                <span class="help-block" id="e-amount" style="margin-top:-8px"></span>
                            </div>

                            <div class="row">
                                <div class="col-xs-12 col-sm-5">
                                    <div class="form-group" id="g-tanggal">
                                        <label for="wl-tanggal">Tanggal <span class="text-danger">*</span></label>
                                        <input type="date" id="wl-tanggal" name="tanggal" class="form-control" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
                                        <span class="help-block" id="e-tanggal"></span>
                                    </div>
                                </div>
                                <div class="col-xs-12 col-sm-7">
                                    <div class="form-group" id="g-note">
                                        <label for="wl-note">Catatan <small class="text-muted">(opsional)</small></label>
                                        <input type="text" id="wl-note" name="note" class="form-control" maxlength="500" placeholder="Boleh dikosongkan">
                                        <span class="help-block" id="e-note"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn" id="wl-next">Lanjut <i class="fa fa-arrow-right"></i></button>
                        </div>
                    </div>

                    {{-- Langkah 2: konfirmasi --}}
                    <div id="wl-step2" style="display:none">
                        <div class="modal-body">
                            <div class="wl-big" id="c-amount"></div>
                            <div class="wl-summary">
                                <div class="row-s"><span>Saldo sebelum</span><b id="c-before"></b></div>
                                <div class="row-s"><span>Saldo sesudah</span><b id="c-after"></b></div>
                                <div class="row-s"><span>Tanggal</span><b id="c-tanggal"></b></div>
                                <div class="row-s"><span>Catatan</span><b id="c-note" style="text-align:right;max-width:60%"></b></div>
                            </div>
                            <div class="wl-warn"><i class="fa fa-info-circle"></i> Mutasi yang sudah tersimpan tidak bisa diubah atau dihapus. Kalau salah input, catat entri koreksi.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" id="wl-back"><i class="fa fa-arrow-left"></i> Kembali</button>
                            <button type="submit" class="btn" id="wl-save"><i class="fa fa-check"></i> Ya, catat</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('page-script')
    <script>
        (function () {
            var $modal = $('#modalSaldo');
            var $form = $('#wl-form');
            var $amount = $('#wl-amount');
            var ctx = null;          // {type, id, name, phone, saldo, action}
            var step = 1;
            var submitted = false;

            var chipsTopup = [50000, 100000, 200000, 500000, 1000000];

            function rp(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }
            function isTopup() { return ctx && ctx.type === 'topup'; }

            function uuid() {
                if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                    var r = Math.random() * 16 | 0;
                    return (c === 'x' ? r : (r & 3 | 8)).toString(16);
                });
            }

            function nominal() {
                var d = String($amount.val() || '').replace(/\D/g, '');
                return d === '' ? 0 : parseInt(d, 10);
            }

            function setAmount(n) {
                $amount.val(n > 0 ? n.toLocaleString('id-ID') : '');
                refresh();
            }

            function fieldError(name, msg) {
                $('#g-' + name).toggleClass('has-error', !!msg);
                $('#e-' + name).text(msg || '');
            }

            function clearErrors() {
                ['amount', 'tanggal', 'note'].forEach(function (n) { fieldError(n, ''); });
                $('#wl-error').hide().text('');
            }

            function refresh() {
                var d = String($amount.val() || '').replace(/\D/g, '').replace(/^0+(?=\d)/, '');
                $amount.val(d === '' ? '' : parseInt(d, 10).toLocaleString('id-ID'));

                var n = nominal(), $p = $('#wl-preview');
                if (n <= 0) { $p.text('').css('color', ''); return; }
                var after = isTopup() ? ctx.saldo + n : ctx.saldo - n;
                if (after < 0) {
                    $p.text('Saldo tidak cukup untuk pengurangan ini.').css('color', '#dd4b39');
                } else {
                    $p.text('Saldo setelahnya: ' + rp(after)).css('color', '#00a65a');
                }
            }

            function showStep(n) {
                step = n;
                $('#wl-step1').toggle(n === 1);
                $('#wl-step2').toggle(n === 2);
            }

            function open(c, old) {
                ctx = c;
                submitted = false;
                var top = isTopup();

                $form.attr('action', c.action);
                $('#wl-token').val((old && old.token) || uuid());
                $('#wl-open').val(c.type + ':' + c.id);

                $('#wl-head').removeClass('is-topup is-pengurangan').addClass(top ? 'is-topup' : 'is-pengurangan');
                $('#wl-icon').attr('class', 'fa fa-' + (top ? 'plus' : 'minus'));
                $('#wl-title').text(top ? 'Top Up Saldo' : 'Pengurangan Saldo');
                $('#wl-customer').text(c.name + (c.phone ? ' · ' + c.phone : ''));
                $('#wl-saldo').text(rp(c.saldo));

                $('#wl-next').attr('class', 'btn ' + (top ? 'btn-success' : 'btn-warning'));
                $('#wl-save').attr('class', 'btn ' + (top ? 'btn-success' : 'btn-warning'))
                    .html('<i class="fa fa-check"></i> Ya, catat ' + (top ? 'top up' : 'pengurangan'));

                // chip nominal cepat
                var $chips = $('#wl-chips').empty();
                chipsTopup.forEach(function (v) {
                    $('<button type="button" class="wl-chip"></button>')
                        .text(v >= 1000000 ? (v / 1000000) + ' jt' : (v / 1000) + ' rb')
                        .on('click', function () { setAmount(v); $amount.focus(); })
                        .appendTo($chips);
                });
                if (!top && c.saldo > 0) {
                    $('<button type="button" class="wl-chip">Semua saldo</button>')
                        .on('click', function () { setAmount(Math.round(c.saldo)); $amount.focus(); })
                        .appendTo($chips);
                }

                clearErrors();
                $('#wl-save').prop('disabled', false);
                showStep(1);

                var today = $('#wl-tanggal').attr('max');
                $('#wl-tanggal').val((old && old.tanggal) || today);
                $('#wl-note').val((old && old.note) || '');
                var oldAmount = old && old.amount ? Math.round(parseFloat(old.amount)) : 0;
                setAmount(isFinite(oldAmount) ? oldAmount : 0);

                $modal.modal({ backdrop: 'static', keyboard: true, show: true });
            }

            $modal.on('shown.bs.modal', function () { $amount.focus(); });

            // Tombol di daftar customer
            $(document).on('click', '[data-wl-open]', function () {
                var d = $(this).data();
                open({
                    type: String(d.type), id: d.id, name: String(d.name), phone: d.phone ? String(d.phone) : '',
                    saldo: parseFloat(d.saldo) || 0, action: String($(this).attr('data-action'))
                });
            });

            $amount.on('input', refresh);

            $('#wl-back').on('click', function () { showStep(1); $amount.focus(); });

            $form.on('submit', function (e) {
                if (submitted) { e.preventDefault(); return; }

                if (step === 1) {
                    e.preventDefault();
                    clearErrors();
                    var n = nominal(), ok = true;
                    var tgl = $('#wl-tanggal').val(), note = $.trim($('#wl-note').val());

                    if (n <= 0) { fieldError('amount', 'Nominal harus lebih dari 0.'); ok = false; }
                    else if (!isTopup() && n > ctx.saldo) { fieldError('amount', 'Melebihi saldo saat ini (' + rp(ctx.saldo) + ').'); ok = false; }
                    if (!tgl) { fieldError('tanggal', 'Wajib diisi.'); ok = false; }
                    else if (tgl > $('#wl-tanggal').attr('max')) { fieldError('tanggal', 'Tidak boleh masa depan.'); ok = false; }
                    if (!ok) { return; }

                    var after = isTopup() ? ctx.saldo + n : ctx.saldo - n;
                    $('#c-amount').text((isTopup() ? '+ ' : '− ') + rp(n))
                        .css('color', isTopup() ? '#00a65a' : '#e08e0b');
                    $('#c-before').text(rp(ctx.saldo));
                    $('#c-after').text(rp(after));
                    $('#c-tanggal').text(tgl.split('-').reverse().join('/'));
                    $('#c-note').text(note || '—');
                    showStep(2);
                    return;
                }

                // Langkah 2: kirim sungguhan (proteksi klik ganda)
                submitted = true;
                $('#wl-save').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Menyimpan...');
            });

            @if ($autoOpen)
                (function () {
                    var c = {
                        type: @json($autoOpen['type']),
                        id: @json($autoOpen['customer']->id),
                        name: @json($autoOpen['customer']->name),
                        phone: @json($autoOpen['customer']->no_telp ?: ''),
                        saldo: @json($autoOpen['saldo']),
                        action: @json(route($autoOpen['type'] === 'topup' ? 'saldo-customer.topup.store' : 'saldo-customer.kurangi.store', $autoOpen['customer']))
                    };
                    var old = {
                        token: @json(old('token')),
                        amount: @json(old('amount')),
                        tanggal: @json(old('tanggal')),
                        note: @json(old('note'))
                    };
                    $(function () {
                        open(c, old);
                        @if ($errors->any())
                            @foreach (['amount', 'tanggal', 'note'] as $f)
                                @if ($errors->has($f)) fieldError(@json($f), @json($errors->first($f))); @endif
                            @endforeach
                            @if ($errors->has('saldo')) $('#wl-error').text(@json($errors->first('saldo'))).show(); @endif
                            @if ($errors->has('token')) $('#wl-error').text(@json($errors->first('token'))).show(); @endif
                        @endif
                    });
                })();
            @endif
        })();
    </script>
@endsection