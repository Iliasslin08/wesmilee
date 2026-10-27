<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

abstract class Controller
{
    protected function ok(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }

    protected function erreur(string $message): void
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);
    }
}
