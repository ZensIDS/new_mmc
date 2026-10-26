<script>
$(function () {
    var FMT_VIEW = 'DD/MM/YYYY';
    var FMT_API  = 'YYYY-MM-DD';

    var LOCALE = {
        format: FMT_VIEW, separator: ' - ',
        applyLabel: 'Terapkan', cancelLabel: 'Batal', customRangeLabel: 'Custom',
        fromLabel: 'Dari', toLabel: 'Sampai', firstDay: 1,
        daysOfWeek: ['Mg', 'Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb'],
        monthNames: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
    };

    function presetRanges() {
        return {
            'Hari Ini': [moment(), moment()],
            'Kemarin': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            '7 Hari Terakhir': [moment().subtract(6, 'days'), moment()],
            '30 Hari Terakhir': [moment().subtract(29, 'days'), moment()],
            'Bulan Ini': [moment().startOf('month'), moment()],
            'Bulan Lalu': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
            'Tahun Ini': [moment().startOf('year'), moment()]
        };
    }

    /* ---------- Pencarian & filter kategori ----------
       Catatan perbaikan bug: dulu grup dihitung dengan `.rpt-item:visible`.
       Item di dalam grup yang sedang disembunyikan selalu dianggap tidak
       terlihat, sehingga grup itu tidak pernah muncul lagi saat pindah tab.
       Sekarang jumlah item per grup dihitung dari hasil filter itu sendiri. */
    var activeCat = 'all';

    function applyFilter() {
        var q = $.trim($('#rpt-search').val()).toLowerCase();
        var any = false;

        $('.rpt-group').each(function () {
            var $group = $(this);
            var shown = 0;

            $group.find('.rpt-item').each(function () {
                var $item = $(this);
                var okCat = (activeCat === 'all' || String($item.data('cat')) === activeCat);
                var okTxt = (!q || String($item.data('search')).indexOf(q) > -1);
                var show = okCat && okTxt;
                $item.toggleClass('is-hidden', !show);
                if (show) { shown++; }
            });

            $group.toggleClass('is-hidden', shown === 0);
            if (shown > 0) { any = true; }
        });

        $('#rpt-empty').toggleClass('is-hidden', any);
    }

    function setCategory(cat) {
        activeCat = String(cat);
        $('.rpt-chip').removeClass('active').filter(function () {
            return String($(this).data('cat')) === activeCat;
        }).addClass('active');
        applyFilter();
    }

    $('#rpt-search').on('input', applyFilter);
    $(document).on('click', '.rpt-chip', function () { setCategory($(this).data('cat')); });
    $(document).on('click', '#rpt-reset', function () { $('#rpt-search').val(''); setCategory('all'); });

    applyFilter();

    /* ---------- Daterangepicker di setiap modal ---------- */
    $('.rpt-range').each(function () {
        var $el = $(this);
        var $modal = $el.closest('.rpt-modal');
        var start = moment($el.data('start'), FMT_API);
        var end = moment($el.data('end'), FMT_API);

        $el.daterangepicker({
            startDate: start,
            endDate: end,
            ranges: presetRanges(),
            autoUpdateInput: false,
            parentEl: '#' + $modal.attr('id') + ' .modal-body',
            opens: 'center',
            locale: LOCALE
        }).on('apply.daterangepicker', function (ev, picker) {
            $(this).val(picker.startDate.format(FMT_VIEW) + ' - ' + picker.endDate.format(FMT_VIEW));
            hideError($modal);
        });

        $el.val(start.format(FMT_VIEW) + ' - ' + end.format(FMT_VIEW));
    });

    $('.rpt-modal').on('hidden.bs.modal', function () {
        var picker = $(this).find('.rpt-range').data('daterangepicker');
        if (picker) { picker.hide(); }
        hideError($(this));
    });

    /* ---------- Helper ---------- */
    function showError($modal, msg) { $modal.find('.rpt-error').text(msg).show(); }
    function hideError($modal) { $modal.find('.rpt-error').hide().text(''); }

    function collectParams($modal) {
        var id = $modal.data('report-id');
        var p = {};
        var $range = $modal.find('.rpt-range');
        if ($range.length) {
            var dp = $range.data('daterangepicker');
            p.tanggal_mulai = dp.startDate.format(FMT_API);
            p.tanggal_selesai = dp.endDate.format(FMT_API);
        }
        var outlet = $('#outlet_' + id).val();
        var kasir = $('#kasir_' + id).val();
        var promo = $('#promotion_' + id).val();
        if (outlet) { p.outlet_id = outlet; }
        if (kasir) { p.kasir_id = kasir; }
        if (promo) { p.promotion_id = promo; }
        return p;
    }

    function validate($modal) {
        var $range = $modal.find('.rpt-range');
        if (!$range.length) { return ''; }
        var dp = $range.data('daterangepicker');
        if (dp.endDate.isBefore(dp.startDate)) {
            return 'Tanggal mulai tidak boleh setelah tanggal selesai.';
        }
        var max = parseInt($modal.data('max-days'), 10);
        if (max) {
            var days = dp.endDate.clone().startOf('day').diff(dp.startDate.clone().startOf('day'), 'days') + 1;
            if (days > max) {
                return 'Rentang tanggal terlalu panjang (' + days + ' hari). Maksimal ' + max + ' hari per laporan.';
            }
        }
        return '';
    }

    function buildUrl(base, params) {
        var q = $.param(params);
        return q ? base + (base.indexOf('?') > -1 ? '&' : '?') + q : base;
    }

    function parseFileName(cd) {
        var m = /filename\*=UTF-8''([^;]+)/i.exec(cd);
        if (m) { try { return decodeURIComponent(m[1]); } catch (e) {} }
        m = /filename="?([^";]+)"?/i.exec(cd);
        return m ? m[1] : null;
    }

    function setLoading($modal, loading) {
        var $btn = $modal.find('.rpt-xls');
        $btn.prop('disabled', loading);
        $btn.find('i').attr('class', loading ? 'fa fa-spinner fa-spin' : 'fa fa-file-excel-o');
        $btn.find('span').text(loading ? 'Memproses...' : 'Unduh Excel');
        $modal.find('.rpt-loading-note').toggle(loading);
    }

    function errorMessage(status) {
        if (status === 401 || status === 419) { return 'Sesi login berakhir. Muat ulang halaman lalu login kembali.'; }
        if (status === 403) { return 'Anda tidak memiliki akses ke laporan ini.'; }
        if (status === 404) { return 'Laporan tidak ditemukan.'; }
        return 'Laporan gagal dibuat. Coba perkecil rentang tanggal atau ulangi beberapa saat lagi.';
    }

    function saveBlob(blob, name) {
        if (window.navigator && window.navigator.msSaveBlob) { window.navigator.msSaveBlob(blob, name); return; }
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = name;
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { window.URL.revokeObjectURL(url); document.body.removeChild(a); }, 1500);
    }

    /* ---------- Unduh Excel (dengan indikator loading) ---------- */
    $(document).on('click', '.rpt-xls', function () {
        var $btn = $(this);
        var $modal = $btn.closest('.rpt-modal');
        if ($btn.prop('disabled')) { return; }

        hideError($modal);
        var invalid = validate($modal);
        if (invalid) { showError($modal, invalid); return; }

        var url = buildUrl($btn.data('url'), collectParams($modal));

        // Browser lama tanpa fetch: unduh biasa.
        if (!window.fetch) { window.location.href = url; return; }

        setLoading($modal, true);

        window.fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            var cd = res.headers.get('Content-Disposition') || '';
            if (!res.ok) { throw { status: res.status }; }
            if (!cd) { throw { status: 500 }; }
            return res.blob().then(function (blob) {
                return { blob: blob, name: parseFileName(cd) || ($btn.data('title') + '.xlsx') };
            });
        }).then(function (r) {
            saveBlob(r.blob, r.name);
            setLoading($modal, false);
        }).catch(function (err) {
            setLoading($modal, false);
            showError($modal, errorMessage(err && err.status));
        });
    });

    /* ---------- Preview PDF (hanya muncul jika LAPORAN_SHOW_PDF=true) ---------- */
    $(document).on('click', '.rpt-pdf', function () {
        var $modal = $(this).closest('.rpt-modal');
        hideError($modal);
        var invalid = validate($modal);
        if (invalid) { showError($modal, invalid); return; }
        window.open(buildUrl($(this).data('url'), collectParams($modal)), '_blank');
    });
});
</script>