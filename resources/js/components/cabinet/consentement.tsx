import { router } from '@inertiajs/react';
import { FileSignature, ShieldCheck, Tablet } from 'lucide-react';
import { Pill } from '@/components/cabinet/ui';

/** Consentement loi 09-08 (CNDP) : donné sur la tablette, ou sur la fiche papier signée. */
export function Consentement({ patientId, le, mode, bouton = false }: { patientId: number; le?: string | null; mode?: string | null; bouton?: boolean }) {
    if (le) {
        return (
            <Pill className="bg-green-100 text-green-800">
                <ShieldCheck className="size-3" /> Consentement donné le {new Date(le).toLocaleDateString('fr-FR')}
                {mode ? ` (${mode})` : ''}
            </Pill>
        );
    }

    return (
        <>
            <Pill className="bg-red-100 text-red-700">Consentement manquant</Pill>
            {bouton && (
                <>
                    <button
                        type="button"
                        onClick={() => router.post(`/patients/${patientId}/tablette`, {}, { preserveScroll: true })}
                        className="inline-flex items-center gap-1 rounded-md border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-800 hover:bg-red-100"
                    >
                        <Tablet className="size-3.5" /> Envoyer à la tablette
                    </button>
                    <button
                        type="button"
                        onClick={() =>
                            confirm('Le patient a-t-il signé la fiche de consentement papier ?') &&
                            router.post(`/patients/${patientId}/consentement`, {}, { preserveScroll: true })
                        }
                        className="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-semibold text-muted-foreground hover:bg-muted"
                    >
                        <FileSignature className="size-3.5" /> Fiche papier signée
                    </button>
                </>
            )}
        </>
    );
}
