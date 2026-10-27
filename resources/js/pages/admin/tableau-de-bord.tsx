import { Head } from '@inertiajs/react';
import { Box, Page, PageTitle, Pill } from '@/components/cabinet/ui';
import { dateHeure } from '@/lib/cabinet';

type Props = {
    kpis: { label: string; valeur: number | string }[];
    docteurs: { name: string; etat: string | null; seances: number; minutes: number }[];
    journal: { id: number; action: string; sujet_type: string; sujet_id: number; created_at: string; user: { name: string } | null }[];
};

export default function TableauDeBord({ kpis, docteurs, journal }: Props) {
    return (
        <Page>
            <Head title="Tableau de bord" />
            <PageTitle titre="Tableau de bord du chef" sousTitre="Vue d'ensemble de l'activité du centre." />
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                {kpis.map((k) => (
                    <Box key={k.label}>
                        <div className="text-2xl font-bold text-teal-900 md:text-3xl dark:text-teal-100">{k.valeur}</div>
                        <div className="text-xs text-muted-foreground">{k.label}</div>
                    </Box>
                ))}
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
                <Box titre="Docteurs ce mois">
                    <table className="w-full text-sm">
                        <thead className="text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="py-2">Docteur</th>
                                <th>État</th>
                                <th className="text-right">Séances</th>
                                <th className="text-right">Temps au fauteuil</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {docteurs.map((d) => (
                                <tr key={d.name}>
                                    <td className="py-2 font-semibold">{d.name}</td>
                                    <td>
                                        <Pill className="bg-slate-100 text-slate-700">{d.etat}</Pill>
                                    </td>
                                    <td className="text-right">{d.seances}</td>
                                    <td className="text-right">
                                        {Math.floor(d.minutes / 60)} h {d.minutes % 60} min
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Box>
                <Box titre="Journal : qui a fait quoi">
                    <ul className="max-h-96 divide-y overflow-auto text-sm">
                        {journal.map((j) => (
                            <li key={j.id} className="flex justify-between gap-2 py-1.5">
                                <span>
                                    <b>{j.user?.name ?? 'Système'}</b> · {j.action} · {j.sujet_type} #{j.sujet_id}
                                </span>
                                <span className="text-xs whitespace-nowrap text-muted-foreground">{dateHeure(j.created_at)}</span>
                            </li>
                        ))}
                    </ul>
                </Box>
            </div>
        </Page>
    );
}

TableauDeBord.layout = { breadcrumbs: [{ title: 'Tableau de bord', href: '/admin/tableau-de-bord' }] };
