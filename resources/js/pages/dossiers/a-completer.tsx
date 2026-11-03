import { Head, Link } from '@inertiajs/react';
import { Box, Empty, Page, PageTitle } from '@/components/cabinet/ui';
import type { Patient } from '@/lib/cabinet';
import { dateFr, dateHeure } from '@/lib/cabinet';

type Ligne = { id: number; numero: string; patient: Patient; manques: string[]; accepte_le: string | null; prochain_rdv: string | null };

const BADGE: Record<string, string> = { radio: 'Radio', photos: 'Photos' };

export default function ACompleter({ dossiers }: { dossiers: Ligne[] }) {
    return (
        <Page>
            <Head title="Radio / photos manquantes" />
            <PageTitle
                titre="Radio / photos manquantes"
                sousTitre="Devis accepté chez la finance, mais la radio et/ou les photos ne sont pas encore dans le dossier. Le dossier sort de la liste quand il a une radio ET des photos."
            />
            <Box>
                {dossiers.length === 0 ? (
                    <Empty>Tous les dossiers sont complets.</Empty>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="py-2">Dossier</th>
                                    <th>Patient</th>
                                    <th>Téléphone</th>
                                    <th>Manque</th>
                                    <th>Prochain RDV</th>
                                    <th>Devis accepté</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {dossiers.map((d) => (
                                    <tr key={d.id}>
                                        <td className="py-2">
                                            <Link className="font-semibold text-primary" href={`/dossiers/${d.id}`}>
                                                {d.numero}
                                            </Link>
                                        </td>
                                        <td>{d.patient.nom_complet}</td>
                                        <td>
                                            <a className="underline" href={`tel:${d.patient.telephone}`}>
                                                {d.patient.telephone}
                                            </a>
                                        </td>
                                        <td>
                                            <span className="flex flex-wrap gap-1">
                                                {d.manques.map((m) => (
                                                    <span key={m} className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">
                                                        {BADGE[m] ?? m}
                                                    </span>
                                                ))}
                                            </span>
                                        </td>
                                        <td>{d.prochain_rdv ? dateHeure(d.prochain_rdv) : '—'}</td>
                                        <td>{dateFr(d.accepte_le)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Box>
        </Page>
    );
}

ACompleter.layout = { breadcrumbs: [{ title: 'Radio / photos manquantes', href: '/a-completer' }] };
