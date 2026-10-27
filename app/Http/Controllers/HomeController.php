<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** Après connexion, chacun arrive sur sa page. */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        return $user ? redirect()->route($user->role->home()) : redirect()->route('login');
    }
}
