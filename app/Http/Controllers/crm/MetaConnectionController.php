<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Models\Nexus\MetaConnection;
use App\Services\Crm\Meta\MetaAccess;
use App\Services\Crm\Meta\MetaOAuth;
use App\Services\Crm\Meta\MetaReadService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MetaConnectionController extends Controller
{
    public function redirect(Request $request, MetaAccess $access, MetaOAuth $oauth): RedirectResponse
    {
        $access->assertManage($request);
        $state = bin2hex(random_bytes(32));
        $request->session()->put('meta_oauth', [
            'state' => $state,
            'user_id' => $request->user()->id,
            'company_id' => $access->companyId($request),
            'created_at' => now()->timestamp,
        ]);

        try {
            return redirect()->away($oauth->authorizationUrl($state));
        } catch (RuntimeException $exception) {
            return redirect()->route('crm.configuracion')->withErrors(['meta' => $exception->getMessage()]);
        }
    }

    public function callback(Request $request, MetaAccess $access, MetaOAuth $oauth, MetaReadService $meta): RedirectResponse
    {
        $access->assertManage($request);
        $pending = $request->session()->pull('meta_oauth');
        abort_unless(is_array($pending)
            && is_string($request->query('state'))
            && hash_equals((string) ($pending['state'] ?? ''), $request->query('state'))
            && (int) ($pending['user_id'] ?? 0) === (int) $request->user()->id
            && (int) ($pending['company_id'] ?? 0) === $access->companyId($request)
            && (int) ($pending['created_at'] ?? 0) > now()->subMinutes(10)->timestamp, 403);

        if ($request->has('error')) {
            return redirect()->route('crm.configuracion')->withErrors(['meta' => 'Se canceló la autorización de Meta.']);
        }
        $code = $request->query('code');
        abort_unless(is_string($code) && $code !== '', 400);

        try {
            $credentials = $oauth->exchange($code);
            $meta->businesses($credentials['access_token']);
        } catch (Throwable) {
            return redirect()->route('crm.configuracion')->withErrors(['meta' => 'No se pudo completar la conexión. Revisa los permisos de la app en Meta.']);
        }

        $companyId = $access->companyId($request);
        $fingerprint = hash('sha256', $credentials['access_token']);
        if (MetaConnection::query()->where('token_fingerprint', $fingerprint)->where('company_id', '!=', $companyId)->exists()) {
            return redirect()->route('crm.configuracion')->withErrors(['meta' => 'Esta cuenta de Meta ya está conectada a otra empresa.']);
        }
        if (MetaConnection::query()->where('token_fingerprint', $fingerprint)->where('auth_user_id', '!=', $request->user()->id)->exists()) {
            return redirect()->route('crm.configuracion')->withErrors(['meta' => 'Esta cuenta de Meta ya está conectada por otro administrador.']);
        }

        DB::transaction(function () use ($request, $companyId, $credentials, $fingerprint) {
            $existing = MetaConnection::query()->where('auth_user_id', $request->user()->id)->first();
            if ($existing) {
                DB::table('meta_ad_accounts')->where('meta_connection_id', $existing->id)->delete();
                DB::table('meta_pages')->where('meta_connection_id', $existing->id)->delete();
            }
            MetaConnection::query()->updateOrCreate(
                ['auth_user_id' => $request->user()->id],
                [
                    'company_id' => $companyId,
                    'access_token' => $credentials['access_token'],
                    'token_fingerprint' => $fingerprint,
                    'meta_user_id' => $credentials['meta_user_id'],
                    'business_id' => null,
                    'business_name' => null,
                    'expires_at' => $credentials['expires_in'] > 0 ? now()->addSeconds($credentials['expires_in']) : null,
                ],
            );
        });

        return redirect()->route('crm.configuracion')->with('success', 'Meta autorizó tu cuenta. Elige tu Business Manager.');
    }

    public function businesses(Request $request, MetaAccess $access, MetaReadService $meta): JsonResponse
    {
        $access->assertManage($request);
        $connection = $access->connection($request);
        abort_unless($connection !== null, 409);

        try {
            return response()->json(['data' => $meta->businesses($connection->access_token)]);
        } catch (RequestException|ConnectionException) {
            return response()->json(['message' => 'No se pudieron consultar los negocios de Meta.'], 502);
        }
    }

    public function selectBusiness(Request $request, MetaAccess $access, MetaReadService $meta): RedirectResponse
    {
        $access->assertManage($request);
        $data = $request->validate(['business_id' => ['required', 'regex:/^\d+$/']]);
        $connection = $access->connection($request);
        abort_unless($connection !== null, 409);

        try {
            $business = collect($meta->businesses($connection->access_token))
                ->first(fn (array $item) => (string) $item['id'] === $data['business_id']);
            if (! $business) {
                throw ValidationException::withMessages(['business_id' => 'No tienes acceso a ese Business Manager.']);
            }
        } catch (RequestException|ConnectionException) {
            return back()->withErrors(['meta' => 'Meta no permitió consultar ese Business Manager.']);
        }

        DB::transaction(function () use ($connection, $business, $data) {
            if ($connection->business_id !== $data['business_id']) {
                DB::table('meta_pages')->where('meta_connection_id', $connection->id)->delete();
            }
            $connection->update(['business_id' => $data['business_id'], 'business_name' => (string) $business['name']]);
        });

        return redirect()->route('crm.campanas')->with('success', 'Business Manager conectado. Vincula las cuentas publicitarias que quieras consultar.');
    }
}
