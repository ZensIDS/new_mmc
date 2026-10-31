{{--
    Tahap 4B.4 — tombol Hapus standar.

    <x-delete-button :action="route('category.destroy', $category->id)" label="kategori {{ $category->name }}" />

    - superadmin : konfirmasi lalu hapus langsung.
    - admin      : modal alasan (layouts/_deletion-guard) -> permintaan hapus; bila sudah diajukan tampil badge
                   "Menunggu persetujuan hapus". Admin TIDAK punya tombol batalkan (K6).
    - role lain  : tombol tidak ditampilkan (server tetap menegakkan lewat `role:` rute / deletion.guard).
--}}
@props([
    'action',
    'label' => 'data ini',
    'text' => 'Hapus',
    'size' => 'btn-xs',
    'icon' => 'fa-trash',
])
@php($u = auth()->user())
@if ($u && ($u->canDeleteDirectly() || $u->mustRequestDeletion()))
    <form action="{{ $action }}" method="post" style="display:inline;" data-delete-label="{{ $label }}">
        @csrf
        @method('delete')
        <button type="submit" {{ $attributes->merge(['class' => 'btn '.$size.' btn-danger']) }}>
            @if ($icon)<i class="fa {{ $icon }}"></i>@endif {{ $text }}
        </button>
    </form>
@endif