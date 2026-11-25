<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Tutoriales\CatalogoGuias;
use App\Services\Tutoriales\Guia;
use App\Services\Tutoriales\RenderizadorGuia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Tutoriales: las guías públicas se ven sin sesión; las demás según el sistema y el rol de la cuenta. */
class TutorialController extends Controller
{
    public function __construct(private readonly CatalogoGuias $catalogo) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('tutoriales.index', [
            'completa' => $this->catalogo->sesionCompleta($user),
            'grupos' => $this->catalogo->visiblesPara($user)->groupBy('sistema'),
            'sistemas' => config('guias.sistemas'),
        ]);
    }

    public function show(Request $request, RenderizadorGuia $renderizador, string $slug): View|RedirectResponse
    {
        $guia = $this->catalogo->buscar($slug);
        abort_if($guia === null, 404);

        $visibles = $this->catalogo->visiblesPara($request->user());

        if (! $visibles->has($slug)) {
            return $this->sinPermiso($request->user());
        }

        // Anterior y siguiente dentro del mismo sistema, entre las que la persona puede ver.
        $hermanas = $visibles->where('sistema', $guia->sistema)->values();
        $posicion = $hermanas->search(fn (Guia $g): bool => $g->slug === $slug);

        return view('tutoriales.show', [
            'completa' => $this->catalogo->sesionCompleta($request->user()),
            'guia' => $guia,
            'sistema' => config('guias.sistemas.'.$guia->sistema),
            'anterior' => $hermanas->get($posicion - 1),
            'siguiente' => $hermanas->get($posicion + 1),
        ] + $renderizador->renderizar($guia));
    }

    public function image(Request $request, string $slug, string $archivo): BinaryFileResponse|RedirectResponse
    {
        $guia = $this->catalogo->buscar($slug);
        abort_if($guia === null, 404);

        if (! $this->catalogo->visiblesPara($request->user())->has($slug)) {
            return $this->sinPermiso($request->user());
        }

        // La ruta solo admite nombres simples terminados en .png: no hay forma de salir de la carpeta.
        $ruta = $guia->carpetaImagenes().DIRECTORY_SEPARATOR.$archivo;
        abort_unless(is_file($ruta), 404);

        $respuesta = response()->file($ruta, [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $respuesta->headers->set('Cache-Control', ($guia->publica ? 'public' : 'private').', max-age=86400');

        return $respuesta;
    }

    /** Sin sesión completa se manda a completarla; con ella, una guía ajena no existe (404). */
    private function sinPermiso(?User $user): RedirectResponse
    {
        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        if (! $user->isActive()) {
            return redirect()->route('account.status');
        }

        if (! $this->catalogo->sesionCompleta($user)) {
            return redirect()->route($user->hasTwoFactorEnabled() ? 'two-factor.challenge' : 'two-factor.setup');
        }

        abort(404);
    }
}
