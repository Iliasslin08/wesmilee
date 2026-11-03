<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Patient;
use App\Support\Photo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'type' => [
                'required',
                'in:'.implode(',', array_keys(Document::TYPES)),
            ],
            'fichiers' => ['required', 'array', 'max:10'],
            'fichiers.*' => [
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp,heic,pdf',
            ],
        ]);

        foreach ($data['fichiers'] as $fichier) {
            // La photo de carte conserve son format et sa transparence.
            $reduite = $data['type'] === 'photo_carte'
                ? null
                : Photo::reduire($fichier->getRealPath());

            if ($reduite !== null) {
                $chemin = "patients/{$patient->id}/".Str::random(40).'.jpg';

                Storage::disk('local')->put($chemin, $reduite);
            } else {
                $chemin = $fichier->store(
                    "patients/{$patient->id}",
                    'local'
                );
            }

            $patient->documents()->create([
                'type' => $data['type'],
                'chemin' => $chemin,
                'nom_original' => $fichier->getClientOriginalName(),
                'ajoute_par' => $request->user()->id,
            ]);
        }

        $this->ok(count($data['fichiers']).' document(s) ajouté(s).');

        return back();
    }

    /** Les fichiers restent privés et passent par ce contrôleur. */
    public function show(Document $document): StreamedResponse
    {
        $disk = Storage::disk('local');
        $chemin = $document->chemin;

        abort_unless($chemin && $disk->exists($chemin), 404);

        $stream = $disk->readStream($chemin);

        abort_unless(is_resource($stream), 404);

        // Utiliser le format réel du fichier stocké.
        $extension = strtolower(
            pathinfo($chemin, PATHINFO_EXTENSION)
        );

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'heic' => 'image/heic',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };

        return response()->stream(function () use ($stream) {
            try {
                fpassthru($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Document $document): RedirectResponse
    {
        Storage::disk('local')->delete($document->chemin);

        $document->delete();

        $this->ok('Document supprimé.');

        return back();
    }
}