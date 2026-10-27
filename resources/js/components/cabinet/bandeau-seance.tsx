import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle, Timer } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

type MaSeance = { id: number; debut: string; dossier: { id: number; numero: string; patient: { nom_complet: string } } } | null;

/** Rappel permanent pour le docteur : séance en cours, et alerte si elle est ouverte depuis trop longtemps. */
export function BandeauSeance() {
    const { maSeance, reglages } = usePage().props as unknown as { maSeance: MaSeance; reglages?: { seanceMaxMinutes: number } };
    const [maintenant, setMaintenant] = useState(() => Date.now());
    useEffect(() => {
        const t = setInterval(() => setMaintenant(Date.now()), 30000);
        return () => clearInterval(t);
    }, []);
    if (!maSeance) return null;

    const minutes = Math.max(0, Math.round((maintenant - new Date(maSeance.debut).getTime()) / 60000));
    const trop = minutes >= (reglages?.seanceMaxMinutes ?? 120);
    const duree = minutes >= 60 ? `${Math.floor(minutes / 60)} h ${String(minutes % 60).padStart(2, '0')}` : `${minutes} min`;
    const surLeDossier = typeof window !== 'undefined' && window.location.pathname === `/dossiers/${maSeance.dossier.id}`;

    return (
        <div
            className={cn(
                'mx-4 mt-3 flex flex-wrap items-center gap-3 rounded-xl border px-4 py-2.5 text-sm font-medium md:mx-6',
                trop ? 'border-red-300 bg-red-50 text-red-900 dark:bg-red-950/40 dark:text-red-100' : 'border-amber-300 bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-100',
            )}
        >
            {trop ? <AlertTriangle className="size-5" /> : <Timer className="size-5" />}
            <span className="flex-1">
                {trop ? 'Séance ouverte depuis ' : 'Séance en cours depuis '}
                <b>{duree}</b> avec <b>{maSeance.dossier.patient.nom_complet}</b>
                {trop && ' : avez-vous oublié de la terminer ?'}
            </span>
            {!surLeDossier && (
                <Link href={`/dossiers/${maSeance.dossier.id}?terminer=1`} className="rounded-md bg-red-600 px-3 py-1.5 text-white hover:bg-red-700">
                    Terminer la séance
                </Link>
            )}
        </div>
    );
}
