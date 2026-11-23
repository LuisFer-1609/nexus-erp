<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\CompanyInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyUserController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:auth_users,email'],
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('is_active', true)],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $user = DB::transaction(function () use ($data): User {
            $erpUserId = DB::table('users')->insertGetId([
                'company_id' => $data['company_id'],
                'role_id' => $data['role_id'],
                'name' => trim($data['name']),
                'passwd' => Hash::make(Str::random(64)),
                'is_active' => true,
                'is_admin' => DB::table('roles')->where('id', $data['role_id'])->value('name') === 'Administrador',
                'created_at' => now(),
            ]);

            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => $data['email'],
                'password' => Hash::make(Str::random(64)),
            ]);
            $user->forceFill([
                'company_id' => $data['company_id'],
                'role_id' => $data['role_id'],
                'erp_user_id' => $erpUserId,
            ])->save();

            return $user;
        });

        $this->invite($user);

        return back()->with('success', 'Usuario creado. Se procesó el correo de invitación.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is_platform_admin, 403);

        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('is_active', true)],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        if ($user->password_set_at !== null && (int) $user->company_id !== (int) $data['company_id']) {
            throw ValidationException::withMessages([
                'company_id' => 'No se puede cambiar de empresa a un usuario activo con historial en el CRM.',
            ]);
        }

        DB::transaction(function () use ($user, $data): void {
            $user->forceFill($data)->save();
            if ($user->erp_user_id) {
                DB::table('users')->where('id', $user->erp_user_id)->update([
                    ...$data,
                    'is_admin' => DB::table('roles')->where('id', $data['role_id'])->value('name') === 'Administrador',
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'Empresa y rol actualizados.');
    }

    public function resend(User $user): RedirectResponse
    {
        abort_if($user->is_platform_admin || $user->password_set_at !== null, 403);

        $this->invite($user);

        return back()->with('success', 'Se procesó un nuevo correo de invitación.');
    }

    private function invite(User $user): void
    {
        $token = Password::broker()->createToken($user);
        $user->notify(new CompanyInvitation($token));
    }
}
