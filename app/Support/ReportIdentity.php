<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Identitas perusahaan untuk header Excel/laporan.
 * Sumber: menu Setting (settings.json di disk "public"), sesuai K21.
 * Semua nilai punya fallback sehingga export tidak pernah gagal karena setting kosong.
 */
class ReportIdentity
{
    /**
     * @return array{name:string,address:string,contact:string,logo:?string}
     */
    public static function get(): array
    {
        $settings = [];

        try {
            $disk = Storage::disk('public');
            if ($disk->exists('settings.json')) {
                $settings = json_decode((string) $disk->get('settings.json'), true) ?: [];
            }
        } catch (\Throwable $e) {
            $settings = [];
        }

        $contact = collect([
            $settings['telp'] ?? null,
            $settings['email'] ?? null,
            $settings['website'] ?? null,
        ])->filter()->implode('  |  ');

        return [
            'name'    => (string) ($settings['name'] ?? config('app.name', 'Perusahaan')),
            'address' => (string) ($settings['address'] ?? ''),
            'contact' => $contact,
            'logo'    => self::logoPath($settings['logo'] ?? null),
        ];
    }

    /** Path file logo di disk, atau null bila tidak ada (logo dilewati, export tetap jalan). */
    protected static function logoPath(?string $logo): ?string
    {
        try {
            if ($logo && Storage::disk('public')->exists($logo)) {
                return Storage::disk('public')->path($logo);
            }
        } catch (\Throwable $e) {
            // lanjut ke fallback
        }

        foreach (['img/logo.jpeg', 'img/logo.jpg', 'img/logo.png'] as $file) {
            if (is_file(public_path($file))) {
                return public_path($file);
            }
        }

        return null;
    }
}