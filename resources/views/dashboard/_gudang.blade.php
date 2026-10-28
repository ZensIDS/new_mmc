{{-- Blok Gudang (stok, pending order, stok kurang, deadline PO). Dipisah dari dashboard utama (Tahap 3.5). --}}
        <!-- Baris 1: STAT CARDS -->
        <div class="row">
            <div class="col-md-6">
                <div class="small-box bg-aqua">
                    <div class="inner">
                        <h3>{{ number_format($totalStock) }}</h3>
                        <p>Total Stok Gudang</p>
                    </div>
                    <div class="icon"><i class="fa fa-cubes"></i></div>
                    <a href="{{ route('stock.index') }}" class="small-box-footer">
                        Lihat Detail <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="small-box bg-yellow">
                    <div class="inner">
                        <h3>{{ number_format($pendingOrdersCount) }}</h3>
                        <p>Pending Order</p>
                    </div>
                    <div class="icon"><i class="fa fa-clock-o"></i></div>
                    <a href="{{ route('request-orders.index') }}" class="small-box-footer">
                        Lihat Detail <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="small-box bg-green">
                    <div class="inner">
                        <h3>{{ number_format($deliveredCount) }}</h3>
                        <p>Order Terkirim</p>
                    </div>
                    <div class="icon"><i class="fa fa-truck"></i></div>
                    <a href="{{ route('delivery-orders.index') }}" class="small-box-footer">
                        Lihat Detail <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="small-box bg-red">
                    <div class="inner">
                        <h3>{{ number_format($lowStockCount) }}</h3>
                        <p>Stok Kurang</p>
                    </div>
                    <div class="icon"><i class="fa fa-warning"></i></div>
                    <a href="#widgetLowStock" class="small-box-footer">
                        Lihat Detail <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- END Baris 1 -->

        <!-- Baris 2: INVENTORY + PRODUK TERLARIS -->
        <div class="row">
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-bar-chart"></i> Inventory (Top 5 Stok Terbanyak)</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                        </div>
                    </div>
                    <div class="box-body">
                        <div id="chartInventory" style="min-height:260px"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-star"></i> Top 5 Barang Dikirim ke Outlet</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                        </div>
                    </div>
                    <div class="box-body">
                        <div id="chartTopProducts" style="min-height:260px"></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END Baris 2 -->

        <!-- Baris 3: PESANAN TERBARU + SLOW MOVING -->
        <div class="row">
            <div class="col-md-12">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-list-alt"></i> Pesanan Terbaru</h3>
                        <div class="box-tools pull-right">
                            <a href="{{ route('request-orders.index') }}" class="btn btn-xs btn-default">Lihat Semua</a>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID Pesanan</th>
                                    <th>Outlet</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentOrders as $order)
                                    <tr>
                                        <td><strong>#{{ $order->code ?? $order->id }}</strong></td>
                                        <td>{{ $order->owner?->name ?? '—' }}</td>
                                        <td>
                                            @php
                                                $statusMap = [
                                                    'pending'  => ['label-warning', 'Pending'],
                                                    'approved' => ['label-success', 'Disetujui'],
                                                    'partial'  => ['label-info', 'Sebagian'],
                                                    'rejected' => ['label-danger', 'Ditolak'],
                                                ];
                                                [$cls, $lbl] = $statusMap[$order->status] ?? ['label-default', $order->status];
                                            @endphp
                                            <span class="label {{ $cls }}">{{ $lbl }}</span>
                                        </td>
                                        <td>{{ $order->created_at->format('d M Y') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">Belum ada pesanan</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="box box-default" id="widgetSlowMoving">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-hourglass-half text-muted"></i> Produk Slow Moving</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Produk</th>
                                    <th class="text-center">Stok Gudang</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($slowMovingProducts as $p)
                                    <tr>
                                        <td><small>{{ $p->code }}</small></td>
                                        <td>{{ $p->name }}</td>
                                        <td class="text-center">{{ (int)($p->stocks_sum_qty ?? 0) }}</td>
                                        <td class="text-center"><span class="label label-default"><i class="fa fa-minus-circle"></i> Tidak Aktif</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted" style="padding:20px">Tidak ada produk slow moving</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer text-muted">
                        <small><i class="fa fa-info-circle"></i> Produk yang tidak ada pengiriman dalam 90 hari terakhir.</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            <i class="fa fa-calendar-times-o"></i> Deadline PO Supplier
                            @if($urgentSuppliers->isNotEmpty())
                                <span class="badge bg-red">{{ $urgentSuppliers->count() }}</span>
                            @endif
                        </h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                        </div>
                    </div>
                    <div class="box-body" style="max-height:300px;overflow-y:auto">
                        @if($urgentSuppliers->isNotEmpty())
                            @foreach($urgentSuppliers as $s)
                                @php $days = \Carbon\Carbon::today()->diffInDays($s->next_deadline, false); @endphp
                                <div class="callout {{ $days === 0 ? 'callout-danger' : 'callout-warning' }}">
                                    <p>
                                        <strong>{{ $s->name }}</strong><br>
                                        <small>{{ $s->next_deadline->isoFormat('DD MMM YYYY') }}</small>
                                    </p>
                                    <div>
                                        @if($days === 0)
                                            <span class="label label-danger">HARI INI</span>
                                        @else
                                            <span class="label label-warning">H-{{ $days }}</span>
                                        @endif
                                        <a href="{{ route('pembelian.create', ['supplier_id' => $s->id]) }}" class="btn btn-xs btn-primary pull-right">
                                            <i class="fa fa-plus"></i> Buat PO
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-center text-muted" style="padding:30px">
                                <i class="fa fa-check-circle fa-3x"></i>
                                <p>Tidak ada deadline PO mendesak</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6" id="widgetLowStock">
                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            <i class="fa fa-exclamation-triangle"></i> Produk di Bawah Min Stok
                            <span class="badge bg-red">{{ $lowVelocityProducts->count() }}</span>
                        </h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding" style="max-height:300px;overflow-y:auto">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th class="text-center">Stok</th>
                                    <th class="text-center">Min</th>
                                    <th class="text-center">Defisit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lowVelocityProducts as $p)
                                    <tr class="{{ $p->current_stock === 0 ? 'danger' : 'warning' }}">
                                        <td>
                                            <strong>{{ $p->name }}</strong>
                                            <br><small class="text-muted">{{ $p->code }}</small>
                                        </td>
                                        <td class="text-center">{{ $p->current_stock }}</td>
                                        <td class="text-center">
                                            {{ $p->effective_min }}
                                            @if($p->adjustment_percentage > 0)
                                                <br><small class="text-info">+{{ $p->adjustment_percentage }}%</small>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="label label-danger">-{{ $p->deficit }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-success" style="padding:20px"><i class="fa fa-check"></i> Semua stok mencukupi</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer text-muted">
                        <small><i class="fa fa-info-circle"></i> Produk dengan penyesuaian aktif menggunakan min stok efektif.</small>
                    </div>
                </div>
            </div>
            <div class="col-xs-12">
                <div class="box box-warning collapsed-box">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            <i class="fa fa-clock-o"></i>
                            Stok Mendekati Expired
                            <span class="badge bg-red">{{ $nearExpiryStocks->count() }}</span>
                        </h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse">
                                <i class="fa fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="box-body table-responsive" style="max-height:300px;overflow-y:auto">
                        <table class="table table-bordered table-condensed table-hover">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Kode</th>
                                    <th>Batch / SKU</th>
                                    <th>Qty Gudang</th>
                                    <th>Tanggal Expired</th>
                                    <th>Sisa Hari</th>
                                </tr>
                            </thead>
                            <tbody>
                            @if($nearExpiryStocks->isNotEmpty())
                                @foreach($nearExpiryStocks as $stock)
                                    @php
                                        $daysLeft = (int) \Carbon\Carbon::today()->diffInDays($stock->expired_at, false);
                                    @endphp
                                    <tr class="{{ $daysLeft <= 7 ? 'danger' : ($daysLeft <= 14 ? 'warning' : '') }}">
                                        <td>{{ $stock->product?->name ?? '—' }}</td>
                                        <td>{{ $stock->product?->code ?? '—' }}</td>
                                        <td>{{ $stock->batch_number ?? $stock->sku ?? '—' }}</td>
                                        <td class="text-center">{{ $stock->qty }}</td>
                                        <td>{{ \Carbon\Carbon::parse($stock->expired_at)->format('d M Y') }}</td>
                                        <td class="text-center">
                                            @if($daysLeft <= 7)
                                                <span class="label label-danger">{{ $daysLeft }} hari</span>
                                            @elseif($daysLeft <= 14)
                                                <span class="label label-warning">{{ $daysLeft }} hari</span>
                                            @else
                                                <span class="label label-default">{{ $daysLeft }} hari</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Tidak ada stok mendekati expired</td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6" style="display:none;">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-check-square-o"></i> PO Perlu ACC Owner</h3>
                        <div class="box-tools pull-right">
                            <span class="badge bg-blue">{{ $pendingOwnerApprovalCount ?? 0 }}</span>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Kode PO</th>
                                    <th>Supplier</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingOwnerApprovals as $po)
                                    <tr>
                                        <td><strong>{{ $po->code }}</strong></td>
                                        <td>{{ $po->supplier?->name ?? '—' }}</td>
                                        <td>{{ $po->created_at->format('d M Y') }}</td>
                                        <td>
                                            <a href="{{ $po->canBeEditedBy(auth()->user()) ? route('pembelian.edit', $po->id) : route('pembelian.show', $po->id) }}" class="btn btn-xs btn-primary">
                                                {{ $po->canBeEditedBy(auth()->user()) ? 'Buka PO' : 'Lihat PO' }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">Tidak ada PO yang menunggu ACC owner</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- END Baris 3 -->