<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Penjualan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function getCustomer($penjualan_id)
    {
        $penjualan = Penjualan::find($penjualan_id);
        $customer = $penjualan->customer;

        return response()->json($customer);
    }

    /**
     * Daftar customer; "Customer Umum" selalu di urutan pertama.
     * Customer Umum otomatis dibuat bila belum ada (default penjualan).
     */
    protected function customersQuery()
    {
        User::defaultCustomer();

        return User::where('role', 'customer')
            ->orderByRaw('CASE WHEN username = ? THEN 0 ELSE 1 END', [User::DEFAULT_CUSTOMER_USERNAME])
            ->orderBy('name');
    }

    public function index(Request $request)
    {
        $customers = $this->customersQuery()->get();

        if ($request->wantsJson()) {
            return response($customers);
        }

        // Jumlah transaksi & total rupiah per customer dalam satu query.
        // Nilai transaksi sama dengan yang dipakai di daftar penjualan:
        // grand_total, atau (total - discount) untuk data lama.
        $stats = Penjualan::query()
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, COUNT(*) as jumlah_transaksi, SUM(COALESCE(grand_total, total - COALESCE(discount, 0), 0)) as total_transaksi')
            ->groupBy('customer_id')
            ->get()
            ->keyBy(fn ($row) => (string) $row->customer_id);

        return view('customers.index', [
            'users' => $customers,
            'stats' => $stats,
        ]);
    }

    public function create()
    {

        return view('customers.create', []);
    }

    /**
     * Tambah customer cepat dari popup kasir (JSON). Hanya Nama yang wajib.
     * No. Telp / Alamat opsional; kalau No. Telp diisi harus unik supaya
     * riwayat belanja satu customer tidak terpecah.
     */
    protected function storeFromCashier(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'no_telp' => [
                'nullable', 'string', 'max:50',
                Rule::unique('users', 'no_telp')->where('role', 'customer')->whereNull('deleted_at'),
            ],
            'alamat' => 'nullable|string|max:255',
        ], [
            'no_telp.unique' => 'No. Telp sudah terdaftar sebagai customer lain.',
        ]);

        $noTelp = filled($data['no_telp'] ?? null) ? trim($data['no_telp']) : null;

        $customer = User::create([
            'name' => trim($data['name']),
            // Customer tidak login: username hanya pengisi kolom wajib, password acak tak terpakai.
            'username' => $noTelp ?? 'cust-'.Str::lower(Str::random(8)),
            'role' => 'customer',
            'status' => 'active',
            'alamat' => filled($data['alamat'] ?? null) ? trim($data['alamat']) : null,
            'no_telp' => $noTelp,
            'password' => Hash::make(Str::random(40)),
        ]);

        return response()->json($customer, 201);
    }

    public function store(Request $request)
    {
        if ($request->wantsJson()) {
            return $this->storeFromCashier($request);
        }

        return $this->storeFromForm(app(CustomerRequest::class));
    }

    protected function storeFromForm(CustomerRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['no_telp']);

        User::create($data);

        return redirect(route('customer.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function show(User $customer)
    {
        abort_unless($customer->role === 'customer', 404);

        $penjualan = Penjualan::where('customer_id', $customer->id)
            ->with([
                'outlet',
                'kasir',
                'cashierShift',
                'items.product' => fn ($q) => $q->withTrashed(),
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('customers.show', [
            'customer' => $customer,
            'penjualan' => $penjualan,
            'totalTransaksi' => $penjualan->sum(fn ($row) => $row->final_total),
        ]);
    }

    public function edit(User $customer)
    {
        if ($customer->isDefaultCustomer()) {
            return redirect(route('customer.index'))->with('toast_error', 'Customer Umum adalah customer bawaan dan tidak dapat diubah.');
        }

        return view('customers.edit', [
            'customer' => $customer,
        ]);
    }

    public function update(Request $request, User $customer)
    {
        if ($customer->isDefaultCustomer()) {
            return redirect(route('customer.index'))->with('toast_error', 'Customer Umum adalah customer bawaan dan tidak dapat diubah.');
        }

        $this->validate($request, [
            'name' => 'required',
            'username' => 'nullable',
            'alamat' => 'required',
            'no_telp' => 'required',
            // 'email' => 'required|email|unique:users,email,'.$customer->id,
            'password' => 'same:confirm-password',
        ]);

        $data = $request->all();
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            $data = Arr::except($data, ['password']);
        }

        $customer->update($data);

        return redirect(route('customer.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function destroy(User $customer)
    {
        if ($customer->isDefaultCustomer()) {
            return redirect(route('customer.index'))->with('toast_error', 'Customer Umum adalah customer bawaan dan tidak dapat dihapus.');
        }

        $customer->delete();

        return redirect(route('customer.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }
}