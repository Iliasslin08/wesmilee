import { Head, Link } from '@inertiajs/react';
import { Box, Empty, Page, PageTitle } from '@/components/cabinet/ui';
import type { Patient } from '@/lib/cabinet';
import { dateFr } from '@/lib/cabinet';

type Ligne = { id: number; numero: string; patient: Patient; prochain_controle: string | null; actes_a_faire: string[] };

export default function ARappeler({ dossiers }: { dossiers: Ligne[] }) {
    return (
        <Page>
            <Head title="Patients à rappeler" />
            <PageTitle titre="Patients à rappeler" sousTitre="Actes « À faire » sans rendez-vous, ou contrôle à prévoir. Appelez-les pour fixer un rendez-vous." />
            <Box>
                {dossiers.length === 0 ? (
                    <Empty>Personne à rappeler.</Empty>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="py-2">Dossier</th>
                                    <th>Patient</th>
                                    <th>Téléphone</th>
                                    <th>À faire</th>
                                    <th>Contrôle</th>
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
                                        <td>{d.actes_a_faire.join(', ') || '—'}</td>
                                        <td>{dateFr(d.prochain_controle)}</td>
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

ARappeler.layout = { breadcrumbs: [{ title: 'Patients à rappeler', href: '/a-rappeler' }] };
