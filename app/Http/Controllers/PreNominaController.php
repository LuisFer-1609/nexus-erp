<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PreNominaController extends Controller
{
    public function empleados(): Response
    {
        return Inertia::render('preNomina/Empleados');
    }

    public function asistencias(): Response
    {
        return Inertia::render('preNomina/Asistencias');
    }

    public function index(): Response
    {
        return Inertia::render('preNomina/PreNomina');
    }
}
