import { router } from '@inertiajs/react';
import { Tablet } from 'lucide-react';
import { Pill } from '@/components/cabinet/ui';

/** Dossier pris au téléphone : « à confirmer » tant que le patient n'a pas rempli sa fiche. */
export function FicheACompleter({ patientId, bouton = false }: { patientId: number; bouton?: boolean }) {
    return (
        <>
            <Pill className="bg-amber-100 text-amber-800">À confirmer · fiche non remplie</Pill>
            {bouton && (
                <button
                    type="button"
                    onClick={() => router.post(`/patients/${patientId}/tablette`, {}, { preserveScroll: true })}
                    className="inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-900 hover:bg-amber-100"
                >
                    <Tablet className="size-3.5" /> Envoyer à la tablette
                </button>
            )}
        </>
    );
}
