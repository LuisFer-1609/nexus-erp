<?php

namespace App\Http\Middleware;

use App\Models\Nexus\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user->is_platform_admin, 403);
        abort_unless(
            $user->company_id && $user->role_id && Company::query()
                ->whereKey($user->company_id)->where('is_active', true)->exists(),
            403,
        );

        return $next($request);
    }
}
