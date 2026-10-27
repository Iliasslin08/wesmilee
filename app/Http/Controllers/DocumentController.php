<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(Document::TYPES))],
            'fichiers' => ['required', 'array', 'max:10'],
            'fichiers.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,pdf'],
        ]);

        foreach ($data['fichiers'] as $f) {
            $patient->documents()->create([
                'type' => $data['type'],
                'chemin' => $f->store("patients/{$patient->id}", 'local'),
                'nom_original' => $f->getClientOriginalName(),
                'ajoute_par' => $request->user()->id,
            ]);
        }
        $this->ok(count($data['fichiers']).' document(s) ajouté(s).');

        return back();
    }

    /** Les fichiers ne sont jamais publics : ils passent par ce contrôleur. */
    public function show(Document $document): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($document->chemin), 404);

        return Storage::disk('local')->response($document->chemin, $document->nom_original);
    }

    public function destroy(Document $document): RedirectResponse
    {
        Storage::disk('local')->delete($document->chemin);
        $document->delete();
        $this->ok('Document supprimé.');

        return back();
    }
}
