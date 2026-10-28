@inject('carbon', 'Carbon\Carbon')

@extends('layouts.master')

@section('title', 'Products')

@section('container')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>
            Data Products
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <a href="{{ route('product.create') }}" class="btn btn-sm bg-light-blue"><i class="fa fa-plus"></i>Tambah</a>
                        {{-- <a href="{{ route('product.export') }}" class="btn btn-sm bg-green">
                            <i class="fa fa-download"></i> Export
                        </a> --}}
                        {{-- <a href="{{ route('product.export.template') }}" class="btn btn-sm bg-gray">
                            <i class="fa fa-file-excel-o"></i> Template
                        </a>
                        <button class="btn btn-sm bg-yellow" data-toggle="modal" data-target="#modalImport">
                            <i class="fa fa-upload"></i> Import
                        </button>
                        <hr /> --}}
                        {{-- <a href="{{ route('product.min-stock.export') }}" class="btn btn-sm bg-teal">
                            <i class="fa fa-download"></i> Export Min Stock
                        </a> --}}
                        {{-- <a href="{{ route('product.min-stock.export.template') }}" class="btn btn-sm bg-gray">
                            <i class="fa fa-file-excel-o"></i> Template Min Stock
                        </a> --}}
                        {{-- <button class="btn btn-sm bg-orange" data-toggle="modal" data-target="#modalImportMinStock">
                            <i class="fa fa-upload"></i> Import Min Stock
                        </button> --}}
                        {{-- @if($canAdjustMinStock ?? false)
                            <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#modalMinStockAdj">
                                <i class="fa fa-sliders"></i> Pengaturan Min Stok
                            </button>
                        @endif --}}
                        <form method="GET" action="{{ route('product.index') }}" class="row" style="margin-top:10px;">
                            <div class="col-xs-12" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                <input type="text" name="search" value="{{ $search }}" class="form-control input-sm"
                                    style="width:220px;" placeholder="Cari kode, nama, brand, model">
                                <select name="status_produk" id="filterStatusProduk" class="form-control input-sm"
                                    style="width:auto; min-width:180px;">
                                    <option value="all" {{ $selectedStatusProduk === 'all' ? 'selected' : '' }}>Filter Status Produk: Semua</option>
                                    @foreach ($statusProdukOptions as $statusValue => $statusLabel)
                                        <option value="{{ $statusValue }}" {{ $selectedStatusProduk === $statusValue ? 'selected' : '' }}>
                                            Filter Status Produk: {{ $statusLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                <select name="category_id" id="filterKategori" class="form-control input-sm select2"
                                    style="width:auto; min-width:180px;">
                                    <option value="">Semua Kategori</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ (string) $selectedCategoryId === (string) $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <select name="lokasi" id="filterLokasi" class="form-control input-sm select2"
                                    style="width:auto; min-width:160px;">
                                    <option value="">Semua Lokasi</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location }}" {{ $selectedLokasi === $location ? 'selected' : '' }}>
                                            {{ $location }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fa fa-search"></i> Filter
                                </button>
                                <a href="{{ route('product.index') }}" class="btn btn-sm btn-default">Reset</a>
                            </div>
                        </form>
                    </div><!-- /.box-header -->

                    {{-- Modal Import --}}
                    <div class="modal fade" id="modalImport" tabindex="-1">
                        <div class="modal-dialog modal-sm">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    <h4 class="modal-title">Import Product</h4>
                                </div>
                                <form action="{{ route('product.import') }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label>File Excel</label>
                                            <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                                            <p class="help-block">
                                                Download <a href="{{ route('product.export.template') }}">template</a> terlebih dahulu.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fa fa-upload"></i> Import
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Modal Import Min Stock --}}
                    <div class="modal fade" id="modalImportMinStock" tabindex="-1">
                        <div class="modal-dialog modal-sm">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    <h4 class="modal-title">Import Min Stock</h4>
                                </div>
                                <form action="{{ route('product.min-stock.import') }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label>File Excel</label>
                                            <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                                            <p class="help-block">
                                                Download <a href="{{ route('product.min-stock.export.template') }}">template</a> terlebih dahulu.
                                                Kolom: kode, nama, min_stock.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fa fa-upload"></i> Import
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    {{-- <div class="box-body">
                        <h4 style="margin-top:0;">Progress Import Produk</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>File</th>
                                        <th>Status</th>
                                        <th>Progress</th>
                                        <th>Rows</th>
                                        <th>Success</th>
                                        <th>Failed</th>
                                        <th>Chunks</th>
                                        <th>Updated</th>
                                    </tr>
                                </thead>
                                <tbody id="product-import-status-body">
                                    @forelse ($recentImports as $import)
                                        <tr>
                                            <td>#{{ $import['id'] }}</td>
                                            <td>{{ $import['original_file_name'] }}</td>
                                            <td>{{ $import['status_label'] }}</td>
                                            <td>{{ $import['progress'] }}%</td>
                                            <td>{{ $import['processed_rows'] }}/{{ $import['total_rows'] }}</td>
                                            <td>{{ $import['successful_rows'] }}</td>
                                            <td>
                                                {{ $import['failed_rows'] }}
                                                @if ($import['failed_jobs'] > 0)
                                                    <span class="label label-danger">{{ $import['failed_jobs'] }} job</span>
                                                @endif
                                            </td>
                                            <td>{{ $import['processed_chunks'] }}/{{ $import['total_chunks'] }}</td>
                                            <td>{{ $import['finished_at'] ?? $import['started_at'] ?? $import['created_at'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">Belum ada import produk.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <p class="help-block" style="margin-bottom:0;">
                            Chunk size import produk sekarang 100 baris. Baris gagal akan diskip, dan progress batch diperbarui otomatis.
                        </p>
                    </div> --}}
                    <div class="box-body table-responsive text-nowrap">
                        <table id="products-table" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <td>No</td>
                                    <td>Barcode</td>
                                    {{-- <td>Nama Outlet</td> --}}
                                    <td>Nama</td>
                                    <td>Kategori</td>
                                    <td>Stock Owner</td>
                                    <td>Stock Reserverd</td>
                                    <td>Stock Warehouse</td>
                                    <td>Stock INBOUND</td>
                                    <td>Stock Minimum</td>
                                    <td>Satuan</td>
                                    <td>Satuan Besar</td>
                                    <td>Konversi</td>
                                    <td>Harga Beli</td>
                                    {{-- <td>Harga Jual</td> --}}
                                    <td>Notif</td>
                                    {{-- <td>Serialized</td> --}}
                                    <td>Aksi</td>
                                    <td style="display:none;">Lokasi</td>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($products as $value)
                                    <tr>
                                        <td>{{ ($products->firstItem() ?? 1) + $loop->index }}</td>
                                        <td>{{ $value->code }}</td>
                                        {{-- <td>{{ $value->outlet?->name }}</td> --}}
                                        <td>{{ $value->name }}</td>
                                        <td>{{ $value->category?->name }}</td>
                                        @php
                                            $ownerQty = (int) ($value->owner_stock_qty ?? 0);
                                            $reservedQty = (int) ($value->reserved_stock_qty ?? 0);
                                            $stockQty = (int) ($value->stock_qty ?? 0); // stok fisik gudang = SUM(stocks.qty)
                                            $pembelianQty = (int) ($value->approved_stock_pembelians_qty ?? 0);
                                        @endphp

                                        <td>
                                            {{ $ownerQty }} {{ $value->satuan ?? '-' }}/<span class="label label-info">{{ $ownerQty > 0 ? $value->konversiDisplay($ownerQty) : '-' }}</span>
                                        </td>
                                        <td>
                                            {{ $reservedQty }} {{ $value->satuan ?? '-' }}/<span class="label label-info">{{ $reservedQty > 0 ? $value->konversiDisplay($reservedQty) : '-' }}</span>
                                        </td>
                                        <td>
                                            {{ $stockQty }} {{ $value->satuan ?? '-' }}/<span class="label label-info">{{ $stockQty > 0 ? $value->konversiDisplay($stockQty) : '-' }}</span>
                                        </td>
                                        <td>
                                            {{ $pembelianQty }} {{ $value->satuan ?? '-' }}/<span class="label label-info">{{ $pembelianQty > 0 ? $value->konversiDisplay($pembelianQty) : '-' }}</span>
                                        </td>
                                        <td>{{ $value->min_stock }}</td>
                                        <td>{{ $value->satuan ?? '-' }}</td>
                                        <td>{{ $value->satuan_besar ?? '-' }}</td>
                                        <td>{{ $value->konversi_string }}</td>
                                        <td>
                                            <button type="button" class="btn btn-xs btn-info btn-price-history"
                                                data-toggle="modal" data-target="#priceHistoryModal"
                                                data-id="{{ $value->id }}">
                                                @currency($value->harga_beli)
                                            </button>
                                        </td>
                                        {{-- <td>@currency($value->harga_jual)</td> --}}
                                        <td>
                                            @if ($value->min_stock > 0 && $stockQty <= $value->effective_min_stock)
                                            habis, stock tinggal {{ $stockQty }}
                                            @else
                                            aman
                                            @endif
                                        </td>
                                        {{-- <td>{{ $value->is_serialized ? 'Yes' : 'No' }}</td> --}}
                                        <td>
                                            <a class="btn btn-warning"
                                                href="{{ route('product.edit', $value->id) }}">Edit</a>
                                            <form action="{{ route('product.destroy', $value->id) }}" method="post"
                                                style="display: inline;">
                                                @method('delete')
                                                @csrf
                                                <button class="border-0 btn btn-danger"
                                                    onclick="return confirm('Are you sure?')">Hapus</button>
                                            </form>
                                        </td>
                                        <td style="display:none;">{{ $value->lokasi ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <!-- Price History Modal -->
                        <div class="modal fade" id="priceHistoryModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        <h4 class="modal-title">Price History (Harga Beli)</h4>
                                    </div>
                                    <div class="modal-body">
                                        <table class="table table-bordered table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>User</th>
                                                    <th>Change</th>
                                                </tr>
                                            </thead>
                                            <tbody id="priceHistoryBody">
                                                <tr>
                                                    <td colspan="3" class="text-center">Loading...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 15px;">
                            {{ $products->links() }}
                        </div>
                    </div><!-- /.box-body -->
                </div><!-- /.box -->
            </div><!-- /.col -->
        </div><!-- /.row -->
    </section><!-- /.content -->

    @if($canAdjustMinStock ?? false)
    <!-- STOCK ADJUSTMENT MODAL (dipindah dari dashboard) -->
    <div class="modal fade" id="modalMinStockAdj" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">
                        <i class="fa fa-sliders"></i> Pengaturan Perubahan Minimal Stok Produk
                    </h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Persentase Kenaikan (%)</label>
                                <input type="number" class="form-control" id="adjPercentage"
                                    min="1" max="500" placeholder="Contoh: 20 untuk +20%">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Aktif Dari</label>
                                <input type="date" class="form-control" id="adjActiveFrom"
                                    value="{{ now()->toDateString() }}">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Aktif Sampai <small class="text-muted">(kosongkan = selamanya)</small></label>
                                <input type="date" class="form-control" id="adjActiveUntil">
                            </div>
                        </div>
                    </div>
                    <table id="tableAdjProducts" class="table table-bordered table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th width="30"><input type="checkbox" id="adjCheckAll"></th>
                                <th>Kode</th>
                                <th>Nama Produk</th>
                                <th>Stok Saat Ini</th>
                                <th>Min Stok</th>
                                <th>Min Efektif (sekarang)</th>
                                <th>Min Efektif (tanggal)</th>
                            </tr>
                        </thead>
                        <tbody id="adjProductBody">
                            @foreach($adjustmentProducts as $p)
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" class="adj-product-check" value="{{ $p->id }}">
                                    </td>
                                    <td>{{ $p->code }}</td>
                                    <td>{{ $p->name }}</td>
                                    <td class="text-center">{{ $p->current_stock }}</td>
                                    <td class="text-center">{{ $p->min_stock }}</td>
                                    <td class="text-center">
                                        {{ $p->effective_min }}
                                        @if($p->effective_min > $p->min_stock)
                                            <span class="label label-info">+{{ $p->effective_min - $p->min_stock }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $p->active_from?->format('d M Y') }} - {{ $p->active_until?->format('d M Y') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" id="btnSimpanAdj">
                        <i class="fa fa-save"></i> Simpan Adjustment
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- END STOCK ADJUSTMENT MODAL -->
    @endif
@endsection
@section('page-script')
    <script>
        $(document).ready(function() {
            $('#filterKategori, #filterLokasi').select2({
                allowClear: true,
                width: 'style'
            });

            function renderImportRows(imports) {
                if (!imports.length) {
                    return '<tr><td colspan="9" class="text-center text-muted">Belum ada import produk.</td></tr>';
                }

                return imports.map(function(item) {
                    var failedJobs = item.failed_jobs > 0
                        ? ' <span class="label label-danger">' + item.failed_jobs + ' job</span>'
                        : '';
                    var updatedAt = item.finished_at || item.started_at || item.created_at || '-';

                    return '<tr>' +
                        '<td>#' + item.id + '</td>' +
                        '<td>' + item.original_file_name + '</td>' +
                        '<td>' + item.status_label + '</td>' +
                        '<td>' + item.progress + '%</td>' +
                        '<td>' + item.processed_rows + '/' + item.total_rows + '</td>' +
                        '<td>' + item.successful_rows + '</td>' +
                        '<td>' + item.failed_rows + failedJobs + '</td>' +
                        '<td>' + item.processed_chunks + '/' + item.total_chunks + '</td>' +
                        '<td>' + updatedAt + '</td>' +
                        '</tr>';
                }).join('');
            }

            function refreshImportStatuses() {
                $.ajax({
                    url: '{{ route('product.import-statuses') }}',
                    method: 'GET',
                    success: function(res) {
                        $('#product-import-status-body').html(renderImportRows(res.data || []));
                    }
                });
            }

            refreshImportStatuses();
            setInterval(refreshImportStatuses, 10000);

            // Price history modal (unchanged)
            $('#priceHistoryModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var modal = $(this);
                modal.find('#priceHistoryBody').html(
                    '<tr><td colspan="3" class="text-center">Loading...</td></tr>');

                $.ajax({
                    url: '/product/' + id + '/price-history',
                    method: 'GET',
                    success: function(res) {
                        var rows = '';
                        if (res.data && res.data.length) {
                            res.data.forEach(function(item) {
                                var change = item.event === 'created' ?
                                    'Created → ' + Number(item.new).toLocaleString() :
                                    Number(item.old).toLocaleString() + ' → ' + Number(
                                        item.new).toLocaleString();
                                rows += '<tr><td>' + item.date + '</td><td>' + item
                                    .user + '</td><td>' + change + '</td></tr>';
                            });
                        } else {
                            rows =
                                '<tr><td colspan="3" class="text-center">No changes found.</td></tr>';
                        }
                        modal.find('#priceHistoryBody').html(rows);
                    },
                    error: function() {
                        modal.find('#priceHistoryBody').html(
                            '<tr><td colspan="3" class="text-center text-danger">Error loading data.</td></tr>'
                            );
                    }
                });
            });

        });
    </script>

    @if($canAdjustMinStock ?? false)
    <script>
        // Pengaturan Min Stok (dipindah dari dashboard)
        let adjTable = null;

        $('#modalMinStockAdj').on('shown.bs.modal', function () {
            if (!adjTable) {
                adjTable = $('#tableAdjProducts').DataTable({
                    pageLength: 10,
                    order: [[2, 'asc']],
                    columnDefs: [{ orderable: false, targets: [0] }],
                    language: {
                        search: "Cari:",
                        lengthMenu: "Tampilkan _MENU_ baris",
                        info: "Menampilkan _START_-_END_ dari _TOTAL_ produk",
                        paginate: { previous: "Prev", next: "Next" },
                        zeroRecords: "Tidak ada produk"
                    }
                });
            }
        });

        $(document).on('change', '#adjCheckAll', function () {
            const checked = $(this).prop('checked');
            if (adjTable) {
                adjTable.rows().nodes().each(function (node) {
                    $(node).find('.adj-product-check').prop('checked', checked);
                });
            }
        });

        $('#btnSimpanAdj').on('click', function () {
            const productIds = [];
            if (adjTable) {
                adjTable.rows().nodes().each(function (node) {
                    const $cb = $(node).find('.adj-product-check:checked');
                    if ($cb.length) productIds.push($cb.val());
                });
            }

            const pct        = parseInt($('#adjPercentage').val()) || 0;
            const activeFrom = $('#adjActiveFrom').val();
            const activeUntil = $('#adjActiveUntil').val();

            if (productIds.length === 0) { alert('Pilih minimal satu produk.'); return; }
            if (pct < 1)                  { alert('Persentase kenaikan minimal 1%.'); return; }
            if (!activeFrom)              { alert('Isi tanggal aktif dari.'); return; }

            $('#btnSimpanAdj').prop('disabled', true).text('Menyimpan...');

            $.ajax({
                url: '{{ route("product.minimum-adjustment.store") }}',
                method: 'POST',
                data: {
                    _token:                '{{ csrf_token() }}',
                    product_ids:           productIds,
                    adjustment_percentage: pct,
                    active_from:           activeFrom,
                    active_until:          activeUntil || null,
                },
                success: function (res) {
                    alert(res.message);
                    $('#modalMinStockAdj').modal('hide');
                    location.reload();
                },
                error: function (xhr) {
                    const msg = xhr.responseJSON?.message || 'Terjadi kesalahan.';
                    alert('Gagal: ' + msg);
                },
                complete: function () {
                    $('#btnSimpanAdj').prop('disabled', false).text('Simpan Adjustment');
                }
            });
        });
    </script>
    @endif
@endsection