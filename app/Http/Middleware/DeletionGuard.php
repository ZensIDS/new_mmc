<?php

namespace App\Http\Middleware;

use App\Services\DeletionRequestService;
use Closure;
use Illuminate\Http\Request;

/**
 * Tahap 4B.3 — dipasang otomatis ke semua rute DELETE (+ rute di config/deletion.php).
 *
 *  - superadmin / role lain  : lanjut normal (controller & middleware `role:` tidak berubah)
 *  - admin (pusat)           : controller TIDAK dipanggil; dibuat permintaan hapus
 *
 * Penegakan di server, bukan hanya menyembunyikan tombol: admin yang menembak
 * endpoint DELETE langsung tetap hanya membuat permintaan.
 */
class DeletionGuard
{
    public function __construct(private DeletionRequestService $service)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user->mustRequestDeletion()) {
            return $next($request);
        }

        $route = $request->route();
        $name = (string) $route->getName();

        if (in_array($name, config('deletion.exempt_routes', []), true)) {
            return $next($request);
        }

        if (in_array($name, config('deletion.forbidden_routes', []), true)) {
            abort(403, 'Aksi ini tidak diizinkan untuk role Anda.');
        }

        // Middleware ini bisa jalan sebelum SubstituteBindings; pastikan model sudah ter-resolve.
        app('router')->substituteBindings($route);
        app('router')->substituteImplicitBindings($route);

        $reason = trim((string) $request->input('deletion_reason', ''));
        if (config('deletion.require_reason') && $reason === '') {
            return $this->respond($request, false, 'Alasan penghapusan wajib diisi.', 422);
        }

        [$deletion, $created] = $this->service->createFromRoute($user, $route, $reason !== '' ? $reason : null);

        $message = $created
            ? 'Permintaan hapus dikirim ke superadmin. Data tetap ada sampai disetujui.'
            : 'Sudah ada permintaan hapus untuk data ini yang menunggu persetujuan.';

        return $this->respond($request, true, $message, 202, $deletion->id);
    }

    private function respond(Request $request, bool $ok, string $message, int $status, ?int $id = null)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => $ok,
                'pending' => $ok,
                'message' => $message,
                'deletion_request_id' => $id,
            ], $status);
        }

        return redirect()->back()->with($ok ? 'toast_success' : 'toast_error', $message);
    }
}