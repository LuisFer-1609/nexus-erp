<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CrmController extends Controller
{
    public function leads(): Response
    {
        return Inertia::render('crm/Leads');
    }

    public function clientes(): Response
    {
        return Inertia::render('crm/Clientes');
    }

    public function cliente(string $cliente): Response
    {
        return Inertia::render('crm/Cliente', [
            'clienteId' => $cliente,
        ]);
    }

    public function asistente(): Response
    {
        return Inertia::render('crm/Asistente');
    }

    public function configuracion(): Response
    {
        return Inertia::render('crm/Configuracion');
    }
}
