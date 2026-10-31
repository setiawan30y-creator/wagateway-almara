<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use App\Support\License;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Panel super-admin: kelola penyewa (akun usaha), paket, masa sewa, pembayaran, dan akses.
 * @2026-Alifiandra
 */
class TenantController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $filter = (string) $request->query('filter', 'all');

        $tenants = Tenant::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('contact_name', 'like', "%{$q}%")->orWhere('contact_phone', 'like', "%{$q}%")))
            ->when($filter === 'expiring', fn ($w) => $w->where('plan', '!=', 'free')->whereNotNull('expires_at')->whereDate('expires_at', '<=', now()->addDays(7)))
            ->when($filter === 'suspended', fn ($w) => $w->where('status', 'suspended'))
            ->when($filter === 'pro', fn ($w) => $w->where('plan', '!=', 'free'))
            ->when($filter === 'free', fn ($w) => $w->where('plan', 'free'))
            ->orderBy('id')->paginate(30)->withQueryString();

        $ids = $tenants->pluck('id');
        $count = fn (string $table, ?callable $extra = null) => DB::table($table)->whereIn('tenant_id', $ids)
            ->when($extra, $extra)->selectRaw('tenant_id, count(*) c')->groupBy('tenant_id')->pluck('c', 'tenant_id');

        return view('admin.tenants.index', [
            'tenants' => $tenants,
            'filter' => $filter,
            'devices' => $count('devices', fn ($w) => $w->where('provider', '!=', 'log')),
            'users' => $count('users'),
            'messagesToday' => $count('messages', fn ($w) => $w->where('direction', 'out')->where('created_at', '>=', now()->startOfDay())),
            'summary' => [
                'total' => Tenant::count(),
                'pro' => Tenant::where('plan', '!=', 'free')->where('status', 'active')->count(),
                'expiring' => Tenant::where('plan', '!=', 'free')->whereNotNull('expires_at')->whereDate('expires_at', '<=', now()->addDays(7))->count(),
                'revenue_month' => (int) TenantPayment::where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.tenants.form', ['tenant' => new Tenant(['plan' => 'pro', 'expires_at' => now()->addDays(30)])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request) + $request->validate([
            'owner_name' => ['required', 'string', 'max:100'],
            'owner_email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'owner_password' => ['required', 'string', 'min:8', 'max:100'],
        ], [], ['owner_name' => 'nama pemilik', 'owner_email' => 'email pemilik', 'owner_password' => 'password pemilik']);

        $tenant = DB::transaction(function () use ($data) {
            $tenant = Tenant::create(collect($data)->except(['owner_name', 'owner_email', 'owner_password'])->all());

            $user = new User(['name' => $data['owner_name'], 'email' => $data['owner_email'], 'password' => Hash::make($data['owner_password'])]);
            $user->forceFill(['tenant_id' => $tenant->id, 'role' => 'owner', 'is_active' => true])->save();

            // perangkat simulasi agar penyewa bisa langsung mencoba
            Tenancy::run($tenant, fn () => Device::create(['name' => 'Simulasi', 'provider' => 'log', 'is_active' => true, 'is_default' => true, 'credentials' => []]));

            return $tenant;
        });

        AuditLog::record('tenant.create', 'Buat penyewa '.$tenant->name);

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Penyewa dibuat. Kirim email & password login ke pemiliknya.');
    }

    public function show(Tenant $tenant)
    {
        $tenant->load('payments.recorder');

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'users' => User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('id')->get(),
            'devices' => Device::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('id')->get(),
            'messagesToday' => Message::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('direction', 'out')->where('created_at', '>=', now()->startOfDay())->count(),
            'messagesMonth' => Message::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('direction', 'out')->where('created_at', '>=', now()->startOfMonth())->count(),
            'plan' => Tenancy::run($tenant, fn () => License::current()),
        ]);
    }

    public function edit(Tenant $tenant)
    {
        return view('admin.tenants.form', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $tenant->update($this->validated($request) + ['last_reminder' => null]);
        License::flush();
        AuditLog::record('tenant.update', 'Ubah penyewa '.$tenant->name);

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Data penyewa diperbarui.');
    }

    /** Catat pembayaran & perpanjang masa sewa. */
    public function pay(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'days' => ['required', 'integer', 'min:1', 'max:3660'],
            'plan' => ['required', Rule::in(array_keys(config('license.plans')))],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['amount' => 'nominal', 'days' => 'jumlah hari']);

        $base = $tenant->expires_at && $tenant->expires_at->isFuture() && $tenant->plan === $data['plan'] ? $tenant->expires_at->copy() : now()->startOfDay();
        $until = $base->addDays((int) $data['days']);

        DB::transaction(function () use ($tenant, $data, $until, $request) {
            $tenant->forceFill(['plan' => $data['plan'], 'expires_at' => $until, 'status' => 'active', 'last_reminder' => null])->save();
            TenantPayment::create([
                'tenant_id' => $tenant->id, 'amount' => $data['amount'], 'days' => $data['days'], 'plan' => $data['plan'],
                'period_until' => $until, 'note' => $data['note'] ?? null, 'recorded_by' => $request->user()->id, 'paid_at' => now(),
            ]);
        });

        License::flush();
        AuditLog::record('tenant.pay', 'Pembayaran '.$tenant->name.' Rp'.number_format($data['amount'], 0, ',', '.').' +'.$data['days'].' hari');

        return back()->with('success', 'Pembayaran dicatat. Masa sewa sampai '.$until->format('d/m/Y').'.');
    }

    public function toggle(Tenant $tenant)
    {
        abort_if($tenant->id === Tenant::defaultId(), 422, 'Akun usaha utama tidak bisa ditangguhkan.');

        $tenant->update(['status' => $tenant->isActive() ? 'suspended' : 'active']);
        AuditLog::record('tenant.status', ($tenant->isActive() ? 'Aktifkan' : 'Tangguhkan').' penyewa '.$tenant->name);

        return back()->with('success', $tenant->isActive() ? 'Penyewa diaktifkan kembali.' : 'Penyewa ditangguhkan: pengguna tidak bisa login, API & pengiriman berhenti.');
    }

    public function destroy(Tenant $tenant)
    {
        abort_if($tenant->id === Tenant::defaultId(), 422, 'Akun usaha utama tidak bisa dihapus.');
        abort_if($tenant->isActive(), 422, 'Tangguhkan penyewa terlebih dahulu sebelum menghapus.');

        AuditLog::record('tenant.delete', 'Hapus penyewa '.$tenant->name);
        $tenant->delete();

        return redirect()->route('admin.tenants.index')->with('success', 'Penyewa beserta seluruh datanya dihapus.');
    }

    /** Masuk ke akun usaha penyewa (untuk bantuan / pengecekan). */
    public function impersonate(Request $request, Tenant $tenant)
    {
        $request->session()->put('tenant_override', $tenant->id);
        AuditLog::record('tenant.impersonate', 'Super-admin masuk ke akun '.$tenant->name);

        return redirect()->route('dashboard')->with('success', 'Anda sekarang melihat akun usaha "'.$tenant->name.'".');
    }

    public function leave(Request $request)
    {
        $id = $request->session()->pull('tenant_override');

        return $id ? redirect()->route('admin.tenants.show', $id) : redirect()->route('admin.tenants.index');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'plan' => ['required', Rule::in(array_keys(config('license.plans')))],
            'max_devices' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'daily_messages' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'expires_at' => ['nullable', 'date'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['name' => 'nama usaha', 'max_devices' => 'maks nomor', 'daily_messages' => 'maks pesan/hari', 'expires_at' => 'berlaku sampai']);

        $data['contact_phone'] = ! empty($data['contact_phone']) ? \App\Support\PhoneNumber::normalize($data['contact_phone']) : null;

        return $data;
    }
}
