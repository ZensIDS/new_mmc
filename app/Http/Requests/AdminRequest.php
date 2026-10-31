<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required',
            'username' => 'required',
            'outlet_id' => 'required_if:role,staff-outlet|required_if:role,admin-gudang',
            // Allow-list: admin tidak boleh membuat akun superadmin.
            'role' => ['required', Rule::in($this->user()?->assignableRoles() ?? [])],
            'status' => 'required',
            'email' => 'required|email',
            'password' => 'required|same:confirm-password',
        ];
    }

    public function messages()
    {
        return [
            // Name field messages
            'name.required' => 'Nama lengkap wajib diisi.',

            // Username field messages
            'username.required' => 'Username wajib diisi.',

            // Outlet ID field messages
            'outlet_id.required_if' => 'Outlet wajib dipilih untuk role :value.',

            // Role field messages
            'role.required' => 'Role pengguna wajib dipilih.',
            'role.in' => 'Role tersebut tidak boleh diberikan oleh akun Anda.',

            // Status field messages
            'status.required' => 'Status akun wajib dipilih.',

            // Email field messages
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid. Contoh: nama@domain.com',

            // Password field messages
            'password.required' => 'Password wajib diisi.',
            'password.same' => 'Password dan konfirmasi password harus sama.',
        ];
    }
}