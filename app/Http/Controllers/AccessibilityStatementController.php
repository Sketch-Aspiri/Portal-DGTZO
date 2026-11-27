<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Tutoriales\CatalogoGuias;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Declaración de accesibilidad: pública; con sesión completa usa el marco normal del portal. */
class AccessibilityStatementController extends Controller
{
    public function __invoke(Request $request, CatalogoGuias $catalogo): View
    {
        return view('accesibilidad.index', ['completa' => $catalogo->sesionCompleta($request->user())]);
    }
}
