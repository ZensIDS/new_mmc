<?php

namespace App\Http\Controllers;

use App\Models\DeletionRequest;
use App\Services\DeletionRequestService;
use Illuminate\Http\Request;

/** Tahap 4B.5 — halaman Permintaan Hapus (hanya superadmin; rute di belakang role:superadmin). */
class DeletionRequestController extends Controller
{
    public function __construct(private DeletionRequestService $service)
    {
    }

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['pending', 'approved', 'rejected', 'failed'], true)
            ? $request->query('tab') : 'pending';

        $query = DeletionRequest::with(['requester', 'reviewer'])
            ->where('status', $tab)
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->query('module')))
            ->when($request->filled('requested_by'), fn ($q) => $q->where('requested_by', $request->query('requested_by')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->query('from').' 00:00:00'))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<', \Illuminate\Support\Carbon::parse($request->query('to'))->addDay()->startOfDay()))
            ->orderByDesc('created_at');

        $requests = $query->paginate(25)->withQueryString();

        return view('deletion-requests.index', [
            'requests' => $requests,
            'tab' => $tab,
            'counts' => DeletionRequest::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'modules' => DeletionRequest::select('module')->distinct()->orderBy('module')->pluck('module'),
            'requesters' => \App\Models\User::whereIn('id', DeletionRequest::select('requested_by')->distinct())->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Status pengajuan hapus milik admin sendiri: menunggu / disetujui / ditolak (+ catatan
     * superadmin) / gagal (+ penyebab). Hanya melihat, tanpa aksi.
     */
    public function mine(Request $request)
    {
        $tab = in_array($request->query('tab'), ['all', 'pending', 'approved', 'rejected', 'failed'], true)
            ? $request->query('tab') : 'all';
        $uid = $request->user()->id;

        $requests = DeletionRequest::with('reviewer')
            ->where('requested_by', $uid)
            ->when($tab !== 'all', fn ($q) => $q->where('status', $tab))
            ->when($request->filled('q'), fn ($q) => $q->where('target_label', 'like', '%'.$request->query('q').'%'))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('deletion-requests.mine', [
            'requests' => $requests,
            'tab' => $tab,
            'counts' => DeletionRequest::where('requested_by', $uid)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    /**
     * Tahap 4B.4 — daftar URL hapus yang sedang menunggu persetujuan, dipakai skrip
     * layouts/_deletion-guard untuk mengganti tombol Hapus dengan badge "Menunggu persetujuan".
     * Hanya path (tanpa data sensitif); rute di belakang role superadmin|admin.
     */
    public function pendingActions(Request $request)
    {
        $paths = DeletionRequest::query()
            ->where('status', DeletionRequest::PENDING)
            ->limit(500)
            ->get(['route_name', 'route_params'])
            ->map(function (DeletionRequest $r) {
                try {
                    $params = collect($r->route_params ?? [])->map(fn ($p) => $p['value'] ?? null)->all();

                    return route($r->route_name, $params, false);
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->unique()
            ->values();

        return response()->json(['paths' => $paths]);
    }

    public function approve(Request $request, DeletionRequest $deletionRequest)
    {
        $data = $request->validate(['review_note' => 'nullable|string|max:1000']);

        $result = $this->service->approveSafely($deletionRequest, $request->user(), $data['review_note'] ?? null);

        return $this->flash($result);
    }

    public function reject(Request $request, DeletionRequest $deletionRequest)
    {
        $data = $request->validate(['review_note' => 'nullable|string|max:1000']);

        $this->service->reject($deletionRequest, $request->user(), $data['review_note'] ?? null);

        return redirect()->back()->with('toast_success', 'Permintaan hapus ditolak. Data tetap ada.');
    }

    public function bulkApprove(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'integer',
            'review_note' => 'nullable|string|max:1000',
        ]);

        $ok = 0;
        $failed = 0;
        foreach (DeletionRequest::whereIn('id', $data['ids'])->orderBy('id')->get() as $req) {
            $res = $this->service->approveSafely($req, $request->user(), $data['review_note'] ?? null);
            $res->status === DeletionRequest::APPROVED ? $ok++ : $failed++;
        }

        return redirect()->back()->with(
            $failed ? 'toast_error' : 'toast_success',
            "{$ok} permintaan disetujui dan dihapus".($failed ? ", {$failed} gagal (lihat tab Gagal)." : '.')
        );
    }

    private function flash(DeletionRequest $r)
    {
        return match ($r->status) {
            DeletionRequest::APPROVED => redirect()->back()->with('toast_success', 'Disetujui — data sudah dihapus.'),
            DeletionRequest::FAILED => redirect()->back()->with('toast_error', 'Gagal menghapus: '.$r->error_message),
            default => redirect()->back()->with('toast_error', 'Permintaan ini sudah diproses.'),
        };
    }
}