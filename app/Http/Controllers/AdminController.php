<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminRequest;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /** Role yang tampil di dropdown form (urutan tampil). Kasir tetap boleh lewat validasi. */
    private const FORM_ROLES = ['superadmin', 'admin', 'admin-gudang', 'staff-outlet', 'owner'];

    public function index()
    {
        $actor = auth()->user();

        $users = User::whereNot('role', 'customer')
            // Admin (pusat) tidak melihat/mengelola akun superadmin.
            ->when(! $actor->isSuperadmin(), fn ($q) => $q->where('role', '!=', 'superadmin'))
            ->get();

        return view('admins.index', [
            'users' => $users,
            'currentUserId' => $actor->id,
        ]);
    }

    public function create()
    {
        return view('admins.create', [
            'roles' => $this->formRoles(),
            'roleLabels' => config('roles.labels', []),
            'outlets' => Outlet::get(),
        ]);
    }

    public function store(AdminRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        User::create($data);

        return redirect(route('admin.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function show(User $user)
    {
        // Sebelumnya dd($user) (membocorkan data user ke browser). Tidak ada halaman detail.
        return redirect()->route('admin.edit', $user->id);
    }

    public function edit(User $admin)
    {
        $this->authorizeTarget($admin);

        return view('admins.edit', [
            'admin' => $admin,
            'roles' => $this->formRoles($admin->role),
            'roleLabels' => config('roles.labels', []),
            'outlets' => Outlet::get(),
        ]);
    }

    public function update(Request $request, User $admin)
    {
        $actor = auth()->user();
        $this->authorizeTarget($admin);

        $data = $this->validate($request, [
            'name' => 'required',
            'username' => 'required',
            'outlet_id' => 'required_if:role,staff-outlet|required_if:role,admin-gudang',
            // Allow-list role: admin tidak bisa menaikkan siapa pun (termasuk dirinya) ke superadmin.
            'role' => ['required', Rule::in($actor->assignableRoles())],
            'status' => 'required',
            'email' => 'required|email|unique:users,email,'.$admin->id,
            'password' => 'nullable|same:confirm-password',
        ]);

        // Hanya field tervalidasi yang dipakai (sebelumnya $request->all()).
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $admin->update($data);

        return redirect(route('admin.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function destroy(User $admin)
    {
        $this->authorizeTarget($admin);

        // Cegah menghapus akun sendiri (akun yang sedang login).
        if ($admin->id === auth()->id()) {
            return redirect(route('admin.index'))->with('toast_error', 'Akun yang sedang dipakai tidak bisa dihapus.');
        }

        $admin->delete();

        return redirect(route('admin.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }

    /** Admin tidak boleh membuka/mengubah/menghapus akun superadmin (server-side, bukan hanya UI). */
    private function authorizeTarget(User $target): void
    {
        abort_unless(auth()->user()->canManageUser($target), 403, 'Tidak berhak mengelola akun ini.');
    }

    /** Role di dropdown = role yang boleh diberikan user login (+ role akun yang sedang diedit). */
    private function formRoles(?string $current = null): array
    {
        $assignable = auth()->user()->assignableRoles();
        $roles = array_values(array_filter(self::FORM_ROLES, fn ($r) => in_array($r, $assignable, true)));

        if ($current && in_array($current, $assignable, true) && ! in_array($current, $roles, true)) {
            $roles[] = $current; // mis. akun lama ber-role kasir tidak boleh hilang dari dropdown
        }

        return $roles;
    }
}