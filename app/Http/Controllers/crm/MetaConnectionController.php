<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Models\Nexus\MetaConnection;
use App\Services\Crm\Meta\MetaAccess;
use App\Services\Crm\Meta\MetaReadService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MetaConnectionController extends Controller
{
    public function store(Request $request, MetaAccess $access, MetaReadService $meta): RedirectResponse
    {
        $access->assertManage($request);
        $data = $request->validate(['access_token' => ['required', 'string', 'max:4096']]);
        $token = trim($data['access_token']);
        $fingerprint = hash('sha256', $token);

        if (MetaConnection::query()->where('token_fingerprint', $fingerprint)
            ->where('company_id', '!=', $access->companyId($request))->exists()) {
            throw ValidationException::withMessages(['access_token' => 'Este token ya está asignado a otra empresa.']);
        }

        try {
            $meta->adAccounts($token);
        } catch (RequestException) {
            throw ValidationException::withMessages(['access_token' => 'Meta rechazó el token o no permite leer cuentas publicitarias.']);
        }

        MetaConnection::query()->updateOrCreate(
            ['company_id' => $access->companyId($request)],
            ['access_token' => $token, 'token_fingerprint' => $fingerprint],
        );

        return back()->with('success', 'Conexión de Meta guardada.');
    }
}
