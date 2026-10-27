import { Head, Link } from '@inertiajs/react';
import { Box, Empty, Page, PageTitle } from '@/components/cabinet/ui';
import { dateHeure } from '@/lib/cabinet';
import { cn } from '@/lib/utils';

type Avis = {
    id: number;
    commentaire: string;
    lu_le: string | null;
    created_at: string;
    patient: { id: number; nom_complet: string; telephone: string | null; dossier: { id: number; numero: string } | null } | null;
};

export default function AvisPage({ avis, total }: { avis: Avis[]; total: number }) {
    return (
        <Page>
            <Head title="Commentaires des patients" />
            <PageTitle
                titre="Commentaires des patients"
                sousTitre={`${total} commentaire(s), laissés depuis la carte (QR code) ou « Mon espace ». Vous seul les voyez.`}
            />
            <Box>
                {avis.length === 0 ? (
                    <Empty>Aucun commentaire pour le moment.</Empty>
                ) : (
                    <ul className="divide-y">
                        {avis.map((a) => (
                            <li key={a.id} className={cn('flex flex-col gap-1 py-3', !a.lu_le && '-mx-3 rounded-lg bg-amber-50 px-3 dark:bg-amber-950/30')}>
                                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    {a.patient?.dossier ? (
                                        <Link className="font-semibold text-primary" href={`/dossiers/${a.patient.dossier.id}`}>
                                            {a.patient.nom_complet} · {a.patient.dossier.numero}
                                        </Link>
                                    ) : (
                                        <span className="font-semibold">{a.patient?.nom_complet ?? 'Patient supprimé'}</span>
                                    )}
                                    {a.patient?.telephone && (
                                        <a className="text-xs text-muted-foreground underline" href={`tel:${a.patient.telephone}`}>
                                            {a.patient.telephone}
                                        </a>
                                    )}
                                    {!a.lu_le && <span className="rounded-full bg-amber-400 px-2 text-xs font-bold text-white">Nouveau</span>}
                                    <span className="ml-auto text-xs text-muted-foreground">{dateHeure(a.created_at)}</span>
                                </div>
                                <p className="text-sm whitespace-pre-line">{a.commentaire}</p>
                            </li>
                        ))}
                    </ul>
                )}
            </Box>
        </Page>
    );
}

AvisPage.layout = { breadcrumbs: [{ title: 'Commentaires des patients', href: '/admin/avis' }] };
