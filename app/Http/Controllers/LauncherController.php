<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class LauncherController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('accesos');

        return view('dashboard', ['subsistemas' => $user->subsistemasAccesibles()]);
    }
}
