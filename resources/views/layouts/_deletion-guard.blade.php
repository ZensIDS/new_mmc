{{--
    Tahap 4B.4 — penjaga tombol Hapus di sisi tampilan (penegakan sebenarnya tetap di middleware `deletion.guard`).

    Untuk SEMUA <form> hapus (method DELETE) di halaman mana pun, tanpa mengubah tiap view:
      - role `admin`  : muncul modal "Ajukan Penghapusan" dengan kolom ALASAN (wajib) -> dikirim sebagai
                        `deletion_reason`. Data tetap ada sampai superadmin menyetujui. Tombol diganti badge
                        "Menunggu persetujuan" bila sudah ada permintaan untuk data itu (admin TIDAK punya
                        tombol batalkan; pembatalan = ditolak superadmin, K6).
      - superadmin    : hapus langsung seperti biasa (form lama yang punya confirm() sendiri tidak berubah;
                        form bertanda data-delete-label memakai konfirmasi standar).
      - hapus lewat AJAX (item PO/RO, catatan): bila server membalas "pending", halaman dimuat ulang supaya
                        baris yang belum benar-benar terhapus tampil lagi.
    Form yang TIDAK ingin dicegat: beri atribut data-no-delete-guard.
--}}
@php
    $dgUser = auth()->user();
    $dgMustRequest = (bool) ($dgUser && $dgUser->mustRequestDeletion());
@endphp
@if ($dgUser && ($dgMustRequest || $dgUser->canDeleteDirectly()))
    @if ($dgMustRequest)
        <div class="modal fade" id="deletionReasonModal" tabindex="-1" role="dialog" aria-labelledby="deletionReasonTitle">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-yellow" style="border-radius:3px 3px 0 0;">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="deletionReasonTitle"><i class="fa fa-hourglass-half"></i> Ajukan Penghapusan</h4>
                    </div>
                    <div class="modal-body">
                        <p>Penghapusan <b id="deletionReasonTarget">data ini</b> <b>tidak langsung dijalankan</b>.
                            Permintaan dikirim ke superadmin; data tetap ada sampai disetujui.</p>
                        <div class="form-group" id="deletionReasonGroup">
                            <label for="deletionReasonInput">Alasan penghapusan <span class="text-red">*</span></label>
                            <textarea id="deletionReasonInput" class="form-control" rows="3" maxlength="500"
                                placeholder="Contoh: salah input, data dobel, sudah tidak dipakai"></textarea>
                            <span class="help-block text-red" id="deletionReasonError" style="display:none;">Alasan wajib diisi.</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-warning" id="deletionReasonSubmit"><i class="fa fa-paper-plane"></i> Kirim Permintaan Hapus</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <script>
        (function ($) {
            var MUST_REQUEST = @json($dgMustRequest);
            var PENDING_URL = @json($dgMustRequest ? route('deletion-requests.pending-actions', [], false) : null);
            var pendingForm = null;

            function isDeleteForm(form) {
                if (!form || form.hasAttribute('data-no-delete-guard')) return false;
                var m = form.querySelector('input[name="_method"]');
                return !!m && String(m.value).toUpperCase() === 'DELETE';
            }

            function targetLabel(form) {
                return form.getAttribute('data-delete-label') || 'data ini';
            }

            /* ---- klik/submit form hapus ---- */
            $(document).on('submit', 'form', function (e) {
                var form = this;
                if (!isDeleteForm(form) || form.__dgConfirmed) return;

                if (MUST_REQUEST) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    pendingForm = form;
                    $('#deletionReasonTarget').text(targetLabel(form));
                    $('#deletionReasonInput').val('');
                    $('#deletionReasonError').hide();
                    $('#deletionReasonGroup').removeClass('has-error');
                    $('#deletionReasonModal').modal('show');
                    return;
                }

                // superadmin: form baru (tanpa confirm() bawaan) minta konfirmasi standar
                if (form.hasAttribute('data-delete-label') && !window.confirm('Yakin menghapus ' + targetLabel(form) + '?')) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                }
            });

            if (MUST_REQUEST) {
                $('#deletionReasonModal').on('shown.bs.modal', function () { $('#deletionReasonInput').trigger('focus'); });

                $('#deletionReasonSubmit').on('click', function () {
                    var reason = $.trim($('#deletionReasonInput').val());
                    if (!reason) {
                        $('#deletionReasonGroup').addClass('has-error');
                        $('#deletionReasonError').show();
                        return;
                    }
                    if (!pendingForm) return;

                    var form = pendingForm;
                    $(form).find('input[name="deletion_reason"]').remove();
                    $('<input type="hidden" name="deletion_reason">').val(reason).appendTo(form);
                    form.__dgConfirmed = true;
                    $(this).prop('disabled', true);
                    $('#deletionReasonModal').modal('hide');
                    form.submit();
                });

                /* ---- badge "menunggu persetujuan" menggantikan tombol Hapus ---- */
                function markPending() {
                    $.getJSON(PENDING_URL).done(function (res) {
                        var paths = {};
                        (res.paths || []).forEach(function (p) { paths[p] = true; });
                        $('form').each(function () {
                            if (!isDeleteForm(this) || this.__dgBadged) return;
                            var path;
                            try { path = new URL(this.action, window.location.origin).pathname; } catch (err) { return; }
                            if (paths[path]) {
                                this.__dgBadged = true;
                                $(this).replaceWith('<span class="label label-warning" title="Menunggu persetujuan superadmin" style="display:inline-block;padding:5px 8px;"><i class="fa fa-hourglass-half"></i> Menunggu persetujuan hapus</span>');
                            }
                        });
                    });
                }
                $(markPending);
                // Tabel DataTables server-side menggambar baris setelah halaman siap.
                $(document).on('draw.dt', function () { setTimeout(markPending, 0); });

                /* ---- hapus via AJAX (item PO/RO, catatan): server membalas pending, bukan terhapus ---- */
                $(document).ajaxSuccess(function (event, xhr) {
                    var body = xhr && xhr.responseJSON;
                    if (body && body.pending === true) {
                        window.alert(body.message || 'Permintaan hapus dikirim ke superadmin.');
                        window.location.reload();
                    }
                });
            }
        })(window.jQuery);
    </script>
@endif