<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Manajemen pengguna & role (owner/admin/agent).
 * @2026-Alifiandra
 */
class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::orderBy('id')->get()]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => 'agent', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create($data);
        AuditLog::record('user.create', 'Tambah pengguna '.$user->email, ['role' => $user->role]);

        return redirect()->route('users.index')->with('success', 'Pengguna ditambahkan.');
    }

    public function edit(User $user)
    {
        return view('users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);

        if ($user->id === $request->user()->id) {
            unset($data['role'], $data['is_active']); // tidak boleh menurunkan / menonaktifkan diri sendiri
        }

        if ($user->isOwner() && ! $request->user()->isOwner()) {
            abort(403, 'Hanya owner yang dapat mengubah akun owner.');
        }

        $user->update($data);
        AuditLog::record('user.update', 'Ubah pengguna '.$user->email, ['role' => $user->role, 'active' => $user->is_active]);

        return redirect()->route('users.index')->with('success', 'Pengguna diperbarui.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $roles = $request->user()->isOwner() ? array_keys(User::ROLES) : ['admin', 'agent'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in($roles)],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
        ], [], ['password' => 'password']);

        $data['is_active'] = $request->boolean('is_active');

        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
