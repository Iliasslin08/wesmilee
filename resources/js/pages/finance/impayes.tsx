import { Head, Link } from '@inertiajs/react';
import { Box, Empty, Page, PageTitle } from '@/components/cabinet/ui';
import type { Patient } from '@/lib/cabinet';
import { dh } from '@/lib/cabinet';

type Ligne = { id: number; numero: string; patient: Patient; convenu: number; recu: number; reste: number };

export default function Impayes({ dossiers, total, encaisseAujourdhui }: { dossiers: Ligne[]; total: number; encaisseAujourdhui: number }) {
    return (
        <Page>
            <Head title="Impayés" />
            <PageTitle titre="Paiements et impayés" sousTitre="Ouvrez un dossier (onglet Paiements) pour encaisser et imprimer le reçu." />
            <div className="grid gap-3 sm:grid-cols-3">
                <Box className="text-center">
                    <div className="text-2xl font-bold text-red-600">{dh(total)}</div>
                    <div className="text-xs text-muted-foreground">Total restant à encaisser</div>
                </Box>
                <Box className="text-center">
                    <div className="text-2xl font-bold">{dossiers.length}</div>
                    <div className="text-xs text-muted-foreground">Dossiers avec un reste</div>
                </Box>
                <Box className="text-center">
                    <div className="text-2xl font-bold text-green-700">{dh(encaisseAujourdhui)}</div>
                    <div className="text-xs text-muted-foreground">Encaissé aujourd'hui</div>
                </Box>
            </div>
            <Box>
                {dossiers.length === 0 ? (
                    <Empty>Aucun impayé.</Empty>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[640px] text-sm">
                            <thead className="text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="py-2">Dossier</th>
                                    <th>Patient</th>
                                    <th>Téléphone</th>
                                    <th className="text-right">Convenu</th>
                                    <th className="text-right">Reçu</th>
                                    <th className="text-right">Reste</th>
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
                                        <td>{d.patient.telephone}</td>
                                        <td className="text-right">{dh(d.convenu)}</td>
                                        <td className="text-right">{dh(d.recu)}</td>
                                        <td className="text-right font-bold text-red-600">{dh(d.reste)}</td>
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

Impayes.layout = { breadcrumbs: [{ title: 'Impayés', href: '/impayes' }] };
