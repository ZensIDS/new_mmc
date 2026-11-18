<?php

namespace App\Services;

use App\Models\CustomerWallet;
use App\Models\CustomerWalletEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pencatatan saldo customer (top up & pengurangan manual oleh superadmin).
 *
 * - Setiap perubahan = satu entri mutasi + update saldo dalam SATU transaksi.
 * - Baris wallet dikunci (lockForUpdate) supaya dua input bersamaan tidak
 *   membuat saldo salah.
 * - Saldo tidak boleh minus.
 * - Service ini TIDAK dipakai oleh kasir/penjualan; saldo hanya catatan.
 *
 * Pemeriksaan hak akses (hanya superadmin) dilakukan di route/controller;
 * service tetap memverifikasi $by sebagai lapisan kedua.
 */
class CustomerWalletService
{
    public function topUp(User $customer, float|int|string $amount, string $note, User $by, Carbon|string|null $tanggal = null): CustomerWalletEntry
    {
        return $this->record($customer, CustomerWalletEntry::TYPE_TOPUP, $amount, $note, $by, $tanggal);
    }

    public function kurangi(User $customer, float|int|string $amount, string $note, User $by, Carbon|string|null $tanggal = null): CustomerWalletEntry
    {
        return $this->record($customer, CustomerWalletEntry::TYPE_PENGURANGAN, $amount, $note, $by, $tanggal);
    }

    /** Saldo terkini (0 bila customer belum pernah punya catatan saldo). */
    public function balanceOf(User $customer): float
    {
        return (float) (CustomerWallet::where('customer_id', $customer->id)->value('balance') ?? 0);
    }

    /**
     * Mutasi satu customer untuk satu periode, format seperti mutasi rekening.
     *
     * Saldo berjalan dihitung dari jumlah nominal bertanda (top up +, pengurangan -)
     * menurut urutan tanggal lalu id, bukan dari balance_before/after yang mengikuti
     * urutan input. Hasilnya tetap cocok walau ada mutasi yang tanggalnya diundur,
     * dan saldo akhir periode yang mencakup mutasi terakhir selalu sama dengan saldo terkini.
     *
     * @param  string|null  $mulai    Y-m-d; null = dari awal (saldo awal 0)
     * @param  string|null  $selesai  Y-m-d; null = sampai mutasi terakhir
     * @return array{mulai:?string,selesai:?string,saldo_awal:float,saldo_akhir:float,total_topup:float,total_pengurangan:float,rows:array<int,array>}
     */
    public function statement(User $customer, ?string $mulai = null, ?string $selesai = null): array
    {
        $signed = "CASE WHEN type = '".CustomerWalletEntry::TYPE_TOPUP."' THEN amount ELSE -amount END";

        $saldoAwal = 0.0;
        if ($mulai) {
            $saldoAwal = (float) CustomerWalletEntry::where('customer_id', $customer->id)
                ->whereDate('tanggal', '<', $mulai)
                ->sum(DB::raw($signed));
        }

        $entries = CustomerWalletEntry::with('creator')
            ->where('customer_id', $customer->id)
            ->when($mulai, fn ($q) => $q->whereDate('tanggal', '>=', $mulai))
            ->when($selesai, fn ($q) => $q->whereDate('tanggal', '<=', $selesai))
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        $saldo = $saldoAwal;
        $totalTopup = 0.0;
        $totalKurang = 0.0;
        $rows = [];

        foreach ($entries as $e) {
            $saldo += $e->signed_amount;
            $e->isTopup() ? $totalTopup += $e->amount : $totalKurang += $e->amount;

            $rows[] = [
                'tanggal' => $e->tanggal,
                'note' => (string) $e->note,
                'topup' => $e->isTopup() ? (float) $e->amount : null,
                'pengurangan' => $e->isTopup() ? null : (float) $e->amount,
                'saldo' => round($saldo, 2),
                'petugas' => optional($e->creator)->name,
            ];
        }

        return [
            'mulai' => $mulai,
            'selesai' => $selesai,
            'saldo_awal' => round($saldoAwal, 2),
            'saldo_akhir' => round($saldo, 2),
            'total_topup' => round($totalTopup, 2),
            'total_pengurangan' => round($totalKurang, 2),
            'rows' => $rows,
        ];
    }

    private function record(User $customer, string $type, float|int|string $amount, string $note, User $by, Carbon|string|null $tanggal): CustomerWalletEntry
    {
        if (! $by->isSuperadmin()) {
            throw new RuntimeException('Hanya superadmin yang boleh mengubah saldo customer.');
        }

        if (! $customer->canHaveWallet()) {
            throw new RuntimeException('Customer ini tidak dapat memiliki saldo.');
        }

        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            throw new RuntimeException('Nominal harus lebih dari 0.');
        }

        // Catatan boleh kosong (disimpan sebagai string kosong).
        $note = trim($note);

        $date = $tanggal ? Carbon::parse($tanggal)->startOfDay() : Carbon::today();
        if ($date->isFuture()) {
            throw new RuntimeException('Tanggal mutasi tidak boleh di masa depan.');
        }

        return DB::transaction(function () use ($customer, $type, $amount, $note, $by, $date) {
            $wallet = $this->lockedWallet($customer);

            $before = round((float) $wallet->balance, 2);
            $after = $type === CustomerWalletEntry::TYPE_TOPUP
                ? round($before + $amount, 2)
                : round($before - $amount, 2);

            if ($after < 0) {
                throw new RuntimeException(
                    'Saldo tidak cukup. Saldo saat ini Rp '.number_format($before, 0, ',', '.').'.'
                );
            }

            $entry = CustomerWalletEntry::create([
                'wallet_id' => $wallet->id,
                'customer_id' => $customer->id,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'tanggal' => $date->toDateString(),
                'note' => $note,
                'created_by' => $by->id,
            ]);

            $wallet->balance = $after;
            $wallet->save();

            return $entry;
        });
    }

    /** Ambil wallet customer dengan kunci baris; dibuat dulu bila belum ada. */
    private function lockedWallet(User $customer): CustomerWallet
    {
        $wallet = CustomerWallet::where('customer_id', $customer->id)->lockForUpdate()->first();
        if ($wallet) {
            return $wallet;
        }

        try {
            CustomerWallet::create(['customer_id' => $customer->id, 'balance' => 0]);
        } catch (QueryException $e) {
            // Dibuat bersamaan oleh proses lain (unique customer_id): lanjut ambil yang sudah ada.
        }

        return CustomerWallet::where('customer_id', $customer->id)->lockForUpdate()->firstOrFail();
    }
}