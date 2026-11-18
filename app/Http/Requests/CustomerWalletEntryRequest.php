<?php

namespace App\Http\Requests;

use App\Support\IndonesianNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Input Top Up / Pengurangan saldo customer. Hanya superadmin.
 */
class CustomerWalletEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() === true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            // Token sekali pakai untuk mencegah submit ganda (refresh / klik dua kali).
            'token' => ['required', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Nominal wajib diisi dengan angka lebih dari 0.',
            'amount.numeric' => 'Nominal wajib diisi dengan angka lebih dari 0.',
            'amount.gt' => 'Nominal harus lebih dari 0.',
            'amount.max' => 'Nominal terlalu besar.',
            'tanggal.required' => 'Tanggal mutasi wajib diisi.',
            'tanggal.date' => 'Tanggal mutasi tidak valid.',
            'tanggal.before_or_equal' => 'Tanggal mutasi tidak boleh di masa depan.',
            'note.max' => 'Catatan maksimal 500 karakter.',
            'token.required' => 'Form kedaluwarsa. Muat ulang halaman lalu coba lagi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => IndonesianNumber::parse($this->input('amount')),
            'note' => trim((string) $this->input('note', '')),
        ]);
    }
}