<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * Akun: ubah nama, email, password.
 * @2026-Alifiandra
 */
class AccountController extends Controller
{
    public function edit(Request $request)
    {
        return view('account', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email,'.$user->id],
            'current_password' => ['required_with:password', 'nullable', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], [], ['current_password' => 'password lama', 'password' => 'password baru']);

        $user->name = $data['name'];
        $user->email = $data['email'];

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
        AuditLog::record('account.update', 'Ubah akun'.(! empty($data['password']) ? ' + password' : ''));

        return back()->with('success', 'Akun diperbarui.');
    }
}
