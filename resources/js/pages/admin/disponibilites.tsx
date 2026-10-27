import { Head, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Box, inputCls, Page, PageTitle } from '@/components/cabinet/ui';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';

type Plage = { jour_semaine: number; heure_debut: string; heure_fin: string };
type D = { id: number; name: string; specialite: string | null; disponibilites: Plage[] };
const JOURS = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

export default function Disponibilites({ docteurs }: { docteurs: D[] }) {
    return (
        <Page>
            <Head title="Horaires" />
            <PageTitle titre="Horaires des docteurs" sousTitre="Le « feu vert » : la réception ne peut prendre un rendez-vous que dans ces horaires." />
            {docteurs.map((d) => (
                <Horaires key={d.id} docteur={d} />
            ))}
        </Page>
    );
}

function Horaires({ docteur }: { docteur: D }) {
    const [plages, setPlages] = useState<Plage[]>(docteur.disponibilites.map((p) => ({ ...p, heure_debut: p.heure_debut.slice(0, 5), heure_fin: p.heure_fin.slice(0, 5) })));
    const maj = (i: number, k: keyof Plage, v: string | number) => setPlages(plages.map((p, j) => (j === i ? { ...p, [k]: v } : p)));

    return (
        <Box titre={`${docteur.name} · ${docteur.specialite ?? ''}`}>
            <div className="flex flex-col gap-2">
                {plages
                    .map((p, i) => ({ p, i }))
                    .sort((a, b) => a.p.jour_semaine - b.p.jour_semaine || a.p.heure_debut.localeCompare(b.p.heure_debut))
                    .map(({ p, i }) => (
                        <div key={i} className="flex flex-wrap items-center gap-2">
                            <select className={cn(inputCls, 'w-36')} value={p.jour_semaine} onChange={(e) => maj(i, 'jour_semaine', Number(e.target.value))}>
                                {JOURS.slice(1).map((j, k) => (
                                    <option key={j} value={k + 1}>
                                        {j}
                                    </option>
                                ))}
                            </select>
                            <input type="time" className={cn(inputCls, 'w-32')} value={p.heure_debut} onChange={(e) => maj(i, 'heure_debut', e.target.value)} />
                            <span>→</span>
                            <input type="time" className={cn(inputCls, 'w-32')} value={p.heure_fin} onChange={(e) => maj(i, 'heure_fin', e.target.value)} />
                            <Button size="icon" variant="ghost" onClick={() => setPlages(plages.filter((_, j) => j !== i))} aria-label="Supprimer">
                                <Trash2 />
                            </Button>
                        </div>
                    ))}
            </div>
            <div className="mt-3 flex gap-2">
                <Button variant="outline" onClick={() => setPlages([...plages, { jour_semaine: 1, heure_debut: '09:00', heure_fin: '13:00' }])}>
                    <Plus /> Ajouter une plage
                </Button>
                <Button onClick={() => router.put(`/horaires/${docteur.id}`, { plages }, { preserveScroll: true })}>Enregistrer</Button>
            </div>
        </Box>
    );
}

Disponibilites.layout = { breadcrumbs: [{ title: 'Horaires', href: '#' }] };
