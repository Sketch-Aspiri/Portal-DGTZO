<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    /**
     * Solo el nombre. El correo es la identidad del ecosistema: su cambio exige
     * contraseña y confirmación por correo y llegará en una fase posterior.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        activity('auth')->performedOn($user)->causedBy($user)->event('profile_updated')->log('profile_updated');

        return redirect()->route('profile.edit')->with('status', 'profile-updated');
    }
}
