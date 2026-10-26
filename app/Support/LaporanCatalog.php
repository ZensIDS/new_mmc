<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Membaca config/laporan.php dan memfilternya sesuai role user.
 */
class LaporanCatalog
{
    /**
     * Grup laporan (beserta isinya) yang boleh dilihat user.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function groups(string $kind, ?User $user = null): array
    {
        $user ??= auth()->user();
        $role = $user?->role;

        $result = [];

        foreach (config("laporan.groups.$kind", []) as $key => $group) {
            $reports = [];

            foreach ($group['reports'] as $id => $report) {
                if (! in_array($role, $report['roles'] ?? [], true)) {
                    continue;
                }
                if (! Route::has($report['xls'])) {
                    continue;
                }

                $report['id']      = $kind.'_'.$id;
                $report['kind']    = $kind;
                $report['color']   = $group['color'] ?? 'blue';
                $report['group']   = $group['title'];
                $report['fields']  = $report['fields'] ?? [];
                $report['has_date'] = in_array('date', $report['fields'], true);
                $report['xls_url'] = route($report['xls']);
                $report['pdf_url'] = (! empty($report['pdf']) && Route::has($report['pdf']))
                    ? route($report['pdf'])
                    : null;
                $report['max_days'] = array_key_exists('max_days', $report)
                    ? $report['max_days']
                    : config('laporan.max_range_days');

                $reports[] = $report;
            }

            if ($reports) {
                $result[] = [
                    'key'     => $key,
                    'title'   => $group['title'],
                    'icon'    => $group['icon'] ?? 'fa-file-text-o',
                    'color'   => $group['color'] ?? 'blue',
                    'reports' => $reports,
                ];
            }
        }

        return $result;
    }

    /** Jumlah laporan yang tersedia untuk user pada satu jenis (gudang/outlet). */
    public static function count(string $kind, ?User $user = null): int
    {
        return collect(self::groups($kind, $user))->sum(fn ($group) => count($group['reports']));
    }

    /** Apakah tombol PDF ditampilkan. */
    public static function showPdf(): bool
    {
        return (bool) config('laporan.show_pdf', false);
    }
}