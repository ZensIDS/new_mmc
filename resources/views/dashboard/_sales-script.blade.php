{{-- Script Dashboard Penjualan (Tahap 3). Highcharts harus sudah dimuat sebelum partial ini. --}}
<script>
$(function () {
    var URL_DATA = @json(route('dashboard.sales-data'));
    var charts = {};
    var FMT_VIEW = 'DD/MM/YYYY', FMT_API = 'YYYY-MM-DD';
    var COLORS = ['#3c8dbc', '#00a65a', '#f39c12', '#dd4b39', '#605ca8', '#39cccc', '#d81b60', '#ff851b', '#001f3f', '#7f8c8d'];

    Highcharts.setOptions({ lang: { thousandsSep: '.', decimalPoint: ',' }, colors: COLORS });

    function esc(v) {
        return String(v === null || v === undefined ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function rp(n) { return 'Rp ' + Math.round(Number(n) || 0).toLocaleString('id-ID'); }
    function num(n) { return (Number(n) || 0).toLocaleString('id-ID'); }
    function delta(d, label) {
        if (d === null || d === undefined) { return '<span class="ds-delta ds-flat">— ' + esc(label) + '</span>'; }
        var up = d >= 0;
        return '<span class="ds-delta ' + (up ? 'ds-up' : 'ds-down') + '"><i class="fa fa-caret-' + (up ? 'up' : 'down') + '"></i> '
            + Math.abs(d).toLocaleString('id-ID') + '% <span class="ds-flat">' + esc(label) + '</span></span>';
    }
    function draw(id, opts) {
        if (charts[id]) { charts[id].destroy(); }
        charts[id] = Highcharts.chart(id, $.extend(true, {
            chart: { backgroundColor: 'transparent' },
            title: { text: null }, credits: { enabled: false }
        }, opts));
    }
    function rows(html, cols) {
        return html || '<tr><td colspan="' + cols + '" class="ds-empty">Belum ada data pada periode ini</td></tr>';
    }

    /* ---------- Filter periode ---------- */
    var start = moment().startOf('month'), end = moment();
    var $range = $('#dsRange');
    $range.daterangepicker({
        startDate: start, endDate: end, maxDate: moment(), opens: 'left',
        ranges: {
            'Hari Ini': [moment(), moment()],
            'Kemarin': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            '7 Hari Terakhir': [moment().subtract(6, 'days'), moment()],
            '30 Hari Terakhir': [moment().subtract(29, 'days'), moment()],
            'Bulan Ini': [moment().startOf('month'), moment()],
            'Bulan Lalu': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
            'Tahun Ini': [moment().startOf('year'), moment()]
        },
        locale: {
            format: FMT_VIEW, separator: ' - ', applyLabel: 'Terapkan', cancelLabel: 'Batal', customRangeLabel: 'Custom',
            fromLabel: 'Dari', toLabel: 'Sampai', firstDay: 1,
            daysOfWeek: ['Mg', 'Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb'],
            monthNames: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
        }
    }, function (s, e) { start = s; end = e; load(false); });
    $range.val(start.format(FMT_VIEW) + ' - ' + end.format(FMT_VIEW));

    $('#dsOutlet').on('change', function () { load(false); });
    $('#dsRefresh').on('click', function () { load(true); });

    /* ---------- Muat data ---------- */
    var seq = 0;
    function load(fresh) {
        var my = ++seq;
        $('#dsBody').addClass('is-loading');
        $('#dsError').hide();

        $.ajax({
            url: URL_DATA, method: 'GET', dataType: 'json',
            data: {
                tanggal_mulai: start.format(FMT_API),
                tanggal_selesai: end.format(FMT_API),
                outlet_id: $('#dsOutlet').val() || '',
                refresh: fresh ? 1 : 0
            }
        }).done(function (d) {
            if (my !== seq) { return; }   // abaikan respons lama
            render(d);
        }).fail(function (xhr) {
            if (my !== seq) { return; }
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Data penjualan gagal dimuat.';
            $('#dsError').html('<i class="fa fa-warning"></i> ' + esc(msg)
                + ' <a href="#" id="dsRetry" class="alert-link">Coba lagi</a>').show();
        }).always(function () {
            if (my === seq) { $('#dsBody').removeClass('is-loading'); }
        });
    }
    $(document).on('click', '#dsRetry', function (e) { e.preventDefault(); load(true); });

    /* ---------- Render ---------- */
    function render(d) {
        var c = d.cards;

        $('#dsPeriodInfo').text('Periode ' + d.periode.label + ' · diperbarui ' + d.dibuat);

        $('#cTodayOmzet').text(rp(c.hari_ini.omzet));
        $('#cTodayDelta').html(delta(c.hari_ini.delta, 'vs kemarin (' + rp(c.hari_ini.kemarin) + ')'));
        $('#cTodayTrx').text(num(c.hari_ini.transaksi));

        $('#cPeriodOmzet').text(rp(c.periode.omzet));
        $('#cPeriodDelta').html(delta(c.periode.delta, 'vs periode sebelumnya'));
        $('#cPeriodTrx').text(num(c.periode.transaksi));
        $('#cPeriodTrxDelta').html(delta(c.periode.delta_trx, 'vs periode sebelumnya'));
        $('#cAvg').text(rp(c.periode.rata_rata));
        $('#cPotongan').text(rp(c.periode.potongan));
        $('#cRetur').text('Retur dikurangkan ' + rp(c.periode.retur) + ' · pengganti ' + rp(c.periode.pengganti));

        // Tren
        $('#dsTrenInfo').text(d.periode.per_bulan ? '(per bulan)' : '(per hari)');
        draw('chartTren', {
            chart: { zoomType: 'x' },
            xAxis: { categories: d.tren.labels, tickInterval: Math.max(1, Math.ceil(d.tren.labels.length / 12)) },
            yAxis: [
                { title: { text: 'Omzet (Rp)' }, min: 0, labels: { formatter: function () { return Highcharts.numberFormat(this.value, 0); } } },
                { title: { text: 'Transaksi' }, min: 0, opposite: true, allowDecimals: false }
            ],
            tooltip: { shared: true },
            series: [
                { name: 'Omzet', type: 'areaspline', data: d.tren.omzet, color: '#3c8dbc', fillOpacity: .2,
                  tooltip: { valuePrefix: 'Rp ', valueDecimals: 0 } },
                { name: 'Transaksi', type: 'column', yAxis: 1, data: d.tren.transaksi, color: '#c8d6e5' }
            ],
            legend: { enabled: true }
        });

        // Metode bayar
        draw('chartMetode', {
            chart: { type: 'pie' },
            tooltip: { pointFormat: '<b>Rp {point.y:,.0f}</b> ({point.percentage:.1f}%)' },
            plotOptions: { pie: { innerSize: '55%', dataLabels: { enabled: true, format: '{point.name}: {point.percentage:.0f}%', style: { fontSize: '11px' } } } },
            series: [{ name: 'Metode', colorByPoint: true,
                data: $.map(d.metode, function (m) { return { name: m.nama, y: m.omzet }; }) }]
        });

        // Outlet (disembunyikan bila hanya satu outlet)
        var showOutlet = d.outlet.length > 1;
        $('#boxOutlet').toggle(showOutlet);
        $('#boxJam').toggleClass('col-md-6', showOutlet).toggleClass('col-md-12', !showOutlet);
        if (showOutlet) {
            draw('chartOutlet', {
                chart: { type: 'bar', height: Math.max(280, d.outlet.length * 34 + 60) },
                xAxis: { categories: $.map(d.outlet, function (o) { return o.nama; }) },
                yAxis: { min: 0, title: { text: 'Omzet (Rp)' } },
                tooltip: { formatter: function () {
                    var o = d.outlet[this.point.index];
                    return '<b>' + esc(o.nama) + '</b><br/>Omzet: Rp ' + Highcharts.numberFormat(o.omzet, 0) + '<br/>Transaksi: ' + o.transaksi;
                } },
                plotOptions: { bar: { colorByPoint: true, dataLabels: { enabled: true, formatter: function () { return Highcharts.numberFormat(this.y, 0); } } } },
                series: [{ name: 'Omzet', data: $.map(d.outlet, function (o) { return o.omzet; }) }],
                legend: { enabled: false }
            });
        }

        // Jam ramai
        draw('chartJam', {
            xAxis: { categories: d.jam.labels, tickInterval: 2 },
            yAxis: [
                { title: { text: 'Transaksi' }, min: 0, allowDecimals: false },
                { title: { text: 'Omzet (Rp)' }, min: 0, opposite: true, labels: { formatter: function () { return Highcharts.numberFormat(this.value, 0); } } }
            ],
            tooltip: { shared: true },
            series: [
                { name: 'Transaksi', type: 'column', data: d.jam.transaksi, color: '#f39c12' },
                { name: 'Omzet', type: 'spline', yAxis: 1, data: d.jam.omzet, color: '#605ca8', tooltip: { valuePrefix: 'Rp ', valueDecimals: 0 } }
            ]
        });

        // Ranking
        var h = '';
        $.each(d.top_produk, function (i, p) {
            h += '<tr><td>' + (i + 1) + '</td><td><strong>' + esc(p.nama) + '</strong>'
                + (p.kode ? '<br><small class="text-muted">' + esc(p.kode) + '</small>' : '') + '</td>'
                + '<td class="ds-num">' + num(p.qty) + '</td><td class="ds-num">' + rp(p.omzet) + '</td></tr>';
        });
        $('#tbProduk').html(rows(h, 4));

        h = '';
        $.each(d.top_kasir, function (i, k) {
            h += '<tr><td>' + (i + 1) + '</td><td>' + esc(k.nama) + '</td>'
                + '<td class="ds-num">' + num(k.transaksi) + '</td><td class="ds-num">' + rp(k.omzet) + '</td></tr>';
        });
        $('#tbKasir').html(rows(h, 4));

        h = '';
        $.each(d.terbaru, function (i, t) {
            h += '<tr><td>' + esc(t.waktu) + '</td><td><strong>' + esc(t.kode) + '</strong></td><td>' + esc(t.outlet)
                + '</td><td>' + esc(t.kasir) + '</td><td><span class="label label-default">' + esc(t.metode) + '</span></td>'
                + '<td class="ds-num">' + rp(t.total) + '</td></tr>';
        });
        $('#tbTerbaru').html(rows(h, 6));
    }

    load(false);
});
</script>