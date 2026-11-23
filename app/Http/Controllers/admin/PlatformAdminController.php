<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlatformAdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Index', [
            'companies' => DB::table('companies')->select('id', 'name', 'is_active')->orderBy('name')->get(),
            'roles' => DB::table('roles')->select('id', 'name')->orderBy('name')->get(),
            'users' => DB::table('auth_users as u')
                ->leftJoin('companies as c', 'c.id', '=', 'u.company_id')
                ->leftJoin('roles as r', 'r.id', '=', 'u.role_id')
                ->where('u.is_platform_admin', false)
                ->select('u.id', 'u.name', 'u.email', 'u.company_id', 'u.role_id', 'u.password_set_at', 'c.name as company_name', 'r.name as role_name')
                ->orderBy('u.name')->get(),
        ]);
    }
}
