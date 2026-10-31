<?php

namespace App\Services;

use App\Models\DeletionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionMethod;
use Throwable;

/**
 * Tahap 4B.2 — membuat, menyetujui, menolak, dan mengeksekusi permintaan hapus.
 *
 * Eksekusi MEMANGGIL ACTION CONTROLLER YANG SAMA dengan hapus normal (destroy),
 * sebagai superadmin, sehingga efek samping yang sudah ada ikut jalan dan logika
 * hapus tidak diduplikasi. Pembatalan hanya lewat TOLAK oleh superadmin (K6).
 */
class DeletionRequestService
{
    /* ------------------------------------------------------------------ */
    /*  Membuat permintaan (dipanggil middleware untuk role `admin`)       */
    /* ------------------------------------------------------------------ */

    /** @return array{0: DeletionRequest, 1: bool} [request, baruDibuat] */
    public function createFromRoute(User $user, \Illuminate\Routing\Route $route, ?string $reason): array
    {
        [$params, $target] = $this->collectParams($route);

        $routeName = (string) $route->getName();
        $key = sha1($routeName.'|'.json_encode($params));

        $existing = DeletionRequest::where('pending_key', $key)->first();
        if ($existing) {
            return [$existing, false];
        }

        $snapshot = $target ? $this->snapshot($target) : [];

        try {
            $req = DeletionRequest::create([
                'requested_by' => $user->id,
                'module' => $this->moduleFor($routeName),
                'target_type' => $target ? get_class($target) : null,
                'target_id' => $target ? (string) $target->getKey() : null,
                'target_label' => $this->label($target, $routeName),
                'target_snapshot' => $snapshot,
                'reason' => $reason,
                'status' => DeletionRequest::PENDING,
                'pending_key' => $key,
                'route_name' => $routeName,
                'route_params' => $params,
            ]);
        } catch (QueryException $e) {
            // Dua klik bersamaan: unique pending_key menolak yang kedua.
            $dup = DeletionRequest::where('pending_key', $key)->first();
            if ($dup) {
                return [$dup, false];
            }
            throw $e;
        }

        $this->log('deletion_request.created', $req, $user);

        return [$req, true];
    }

    /* ------------------------------------------------------------------ */
    /*  Keputusan superadmin                                               */
    /* ------------------------------------------------------------------ */

    public function approve(DeletionRequest $req, User $approver, ?string $note = null): DeletionRequest
    {
        abort_unless($approver->canApproveDeletion(), 403);

        // Kunci baris supaya dua superadmin tidak mengeksekusi dua kali.
        return DB::transaction(function () use ($req, $approver, $note) {
            $req = DeletionRequest::whereKey($req->id)->lockForUpdate()->firstOrFail();

            if (! $req->isActionable()) {
                return $req;
            }

            $this->execute($req, $approver);

            $req->update([
                'status' => DeletionRequest::APPROVED,
                'pending_key' => null,
                'reviewed_by' => $approver->id,
                'reviewed_at' => now(),
                'review_note' => $note,
                'executed_at' => now(),
                'error_message' => null,
            ]);
            $this->log('deletion_request.approved', $req, $approver);

            return $req;
        });
    }

    /**
     * Versi aman untuk dipanggil controller: gagal -> status `failed` + pesan,
     * perubahan data hasil eksekusi yang gagal sudah di-rollback.
     */
    public function approveSafely(DeletionRequest $req, User $approver, ?string $note = null): DeletionRequest
    {
        try {
            return $this->approve($req, $approver, $note);
        } catch (Throwable $e) {
            report($e);

            $req = DeletionRequest::findOrFail($req->id);
            $req->update([
                'status' => DeletionRequest::FAILED,
                'reviewed_by' => $approver->id,
                'reviewed_at' => now(),
                'review_note' => $note,
                'error_message' => Str::limit($e->getMessage() ?: class_basename($e), 1000),
            ]);
            $this->log('deletion_request.failed', $req, $approver, ['error' => $req->error_message]);

            return $req;
        }
    }

    public function reject(DeletionRequest $req, User $approver, ?string $note = null): DeletionRequest
    {
        abort_unless($approver->canApproveDeletion(), 403);

        if (! $req->isActionable()) {
            return $req;
        }

        $req->update([
            'status' => DeletionRequest::REJECTED,
            'pending_key' => null,
            'reviewed_by' => $approver->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
        $this->log('deletion_request.rejected', $req, $approver);

        return $req;
    }

    /* ------------------------------------------------------------------ */
    /*  Eksekusi                                                           */
    /* ------------------------------------------------------------------ */

    private function execute(DeletionRequest $req, User $approver): void
    {
        $route = Route::getRoutes()->getByName($req->route_name);
        if (! $route) {
            throw new \RuntimeException("Rute {$req->route_name} sudah tidak ada.");
        }

        $action = $route->getActionName();
        if (! str_contains($action, '@')) {
            throw new \RuntimeException('Rute hapus ini tidak memakai controller biasa.');
        }
        [$class, $method] = explode('@', $action);

        // Resolusi parameter dari data tersimpan (kelas diambil server saat diajukan, bukan input user).
        $args = [];
        foreach (($req->route_params ?? []) as $name => $p) {
            $modelClass = $p['class'] ?? null;
            if ($modelClass) {
                if (! is_subclass_of($modelClass, Model::class)) {
                    throw new \RuntimeException('Tipe data target tidak valid.');
                }
                $args[$name] = $modelClass::query()->find($p['value']);
                if (! $args[$name]) {
                    throw new \RuntimeException('Data target sudah tidak ada / sudah terhapus.');
                }
            } else {
                $args[$name] = $p['value'];
            }
        }

        // Pastikan pelaksana = approver (superadmin), bukan admin pemohon.
        auth()->setUser($approver);

        session()->forget(['toast_error', 'toast_success']);

        $controller = app()->make($class);
        $response = app()->call([$controller, $method], $args);

        if ($response instanceof RedirectResponse && session()->has('toast_error')) {
            $msg = (string) session('toast_error');
            session()->forget('toast_error');
            throw new \RuntimeException($msg);
        }
        if (($response instanceof JsonResponse || $response instanceof HttpResponse) && $response->getStatusCode() >= 400) {
            throw new \RuntimeException('Hapus ditolak oleh modul (HTTP '.$response->getStatusCode().').');
        }

        session()->forget('toast_success'); // pesan milik hapus bawaan; pesan persetujuan dibuat controller kita
    }

    /* ------------------------------------------------------------------ */
    /*  Helper                                                             */
    /* ------------------------------------------------------------------ */

    /** @return array{0: array, 1: ?Model} [route_params, modelTargetTerakhir] */
    private function collectParams(\Illuminate\Routing\Route $route): array
    {
        $params = [];
        $target = null;

        foreach ($route->parameters() as $name => $value) {
            if ($value instanceof Model) {
                $params[$name] = ['class' => get_class($value), 'value' => $value->getKey()];
                $target = $value; // model terakhir = yang dihapus (mis. items/{item})
            } else {
                $params[$name] = ['class' => $this->guessClass($route, $name), 'value' => $value];
            }
        }

        // Parameter skalar yang ternyata model (binding belum terjadi): ambil dari type-hint.
        if (! $target) {
            foreach ($params as $name => $p) {
                if ($p['class']) {
                    $found = $p['class']::query()->find($p['value']);
                    if ($found) {
                        $target = $found;
                    }
                }
            }
        }

        return [$params, $target];
    }

    private function guessClass(\Illuminate\Routing\Route $route, string $name): ?string
    {
        $action = $route->getActionName();
        if (! str_contains($action, '@')) {
            return null;
        }
        [$class, $method] = explode('@', $action);

        try {
            foreach ((new ReflectionMethod($class, $method))->getParameters() as $param) {
                $type = $param->getType();
                if ($param->getName() === $name && $type && ! $type->isBuiltin() && is_subclass_of($type->getName(), Model::class)) {
                    return $type->getName();
                }
            }
        } catch (Throwable) {
        }

        return null;
    }

    private function moduleFor(string $routeName): string
    {
        $modules = config('deletion.modules', []);
        $first = Str::before($routeName, '.');

        return $modules[$first] ?? Str::headline($first ?: 'Lainnya');
    }

    private function label(?Model $target, string $routeName): string
    {
        $module = $this->moduleFor($routeName);

        if (! $target) {
            return $module.': '.$routeName;
        }

        $parts = [];
        foreach (config('deletion.label_fields', []) as $field) {
            $v = $target->getAttribute($field);
            if (is_scalar($v) && $v !== '' && ! in_array((string) $v, $parts, true)) {
                $parts[] = (string) $v;
            }
            if (count($parts) >= 2) {
                break;
            }
        }

        return $module.': '.($parts ? implode(' — ', $parts) : class_basename($target).' #'.$target->getKey());
    }

    private function snapshot(Model $target): array
    {
        $hidden = ['password', 'remember_token', 'return_pin_hash'];
        $attrs = collect($target->getAttributes())
            ->except($hidden)
            ->filter(fn ($v) => is_scalar($v) || is_null($v))
            ->map(fn ($v) => is_string($v) ? Str::limit($v, 120) : $v)
            ->take(25)
            ->all();

        $attrs['_updated_at'] = optional($target->updated_at)->toDateTimeString();

        return $attrs;
    }

    private function log(string $event, DeletionRequest $req, User $by, array $extra = []): void
    {
        if (! function_exists('activity')) {
            return;
        }

        try {
            activity()
                ->causedBy($by)
                ->performedOn($req)
                ->withProperties(['label' => $req->target_label, 'module' => $req->module, 'route' => $req->route_name] + $extra)
                ->log($event);
        } catch (Throwable $e) {
            report($e); // jejak audit tidak boleh menggagalkan proses utama
        }
    }
}