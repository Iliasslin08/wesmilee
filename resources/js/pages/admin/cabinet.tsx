import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Box, Field, inputCls, Page, PageTitle, Pill } from '@/components/cabinet/ui';
import { Button } from '@/components/ui/button';

type Salle = { id: number; nom: string; equipement: string | null; actif: boolean; docteurs: string[] };

export default function Cabinet({ champs, valeurs, salles }: { champs: Record<string, string>; valeurs: Record<string, string>; salles: Salle[] }) {
    const form = useForm(valeurs);
    const nouvelle = useForm({ nom: '', equipement: '', actif: true });

    return (
        <Page>
            <Head title="Cabinet & salles" />
            <PageTitle titre="Cabinet & salles" sousTitre="Ces informations apparaissent sur les reçus, le planning imprimé et l'écran TV." />
            <div className="grid gap-4 lg:grid-cols-2">
                <Box titre="Informations du cabinet">
                    <div className="grid gap-3 md:grid-cols-2">
                        {Object.entries(champs).map(([k, l]) => (
                            <Field key={k} label={l} error={form.errors[k]}>
                                <input className={inputCls} value={form.data[k] ?? ''} onChange={(e) => form.setData(k, e.target.value)} />
                            </Field>
                        ))}
                    </div>
                    <Button className="mt-3" disabled={form.processing || !form.isDirty} onClick={() => form.put('/admin/cabinet', { preserveScroll: true })}>
                        Enregistrer
                    </Button>
                </Box>

                <Box titre={`Salles (${salles.filter((s) => s.actif).length})`}>
                    <ul className="flex flex-col gap-2">
                        {salles.map((s) => (
                            <LigneSalle key={s.id} salle={s} />
                        ))}
                    </ul>
                    <div className="mt-4 grid gap-2 border-t pt-4 md:grid-cols-[1fr_2fr_auto]">
                        <Field label="Nouvelle salle" error={nouvelle.errors.nom}>
                            <input className={inputCls} placeholder="Salle 4" value={nouvelle.data.nom} onChange={(e) => nouvelle.setData('nom', e.target.value)} />
                        </Field>
                        <Field label="Équipement">
                            <input className={inputCls} placeholder="Fauteuil, radio…" value={nouvelle.data.equipement} onChange={(e) => nouvelle.setData('equipement', e.target.value)} />
                        </Field>
                        <Button className="self-end" disabled={!nouvelle.data.nom || nouvelle.processing} onClick={() => nouvelle.post('/admin/salles', { preserveScroll: true, onSuccess: () => nouvelle.reset() })}>
                            <Plus /> Ajouter
                        </Button>
                    </div>
                    <p className="mt-2 text-xs text-muted-foreground">La salle habituelle de chaque docteur se choisit dans « Utilisateurs ».</p>
                </Box>
            </div>
        </Page>
    );
}

function LigneSalle({ salle }: { salle: Salle }) {
    const [edit, setEdit] = useState(false);
    const form = useForm({ nom: salle.nom, equipement: salle.equipement ?? '', actif: salle.actif });
    if (edit) {
        return (
            <li className="grid gap-2 rounded-lg border p-2 md:grid-cols-[1fr_2fr_auto]">
                <input className={inputCls} value={form.data.nom} onChange={(e) => form.setData('nom', e.target.value)} />
                <input className={inputCls} value={form.data.equipement} onChange={(e) => form.setData('equipement', e.target.value)} />
                <div className="flex items-center gap-2">
                    <label className="flex items-center gap-1 text-xs">
                        <input type="checkbox" checked={form.data.actif} onChange={(e) => form.setData('actif', e.target.checked)} /> Active
                    </label>
                    <Button size="sm" onClick={() => form.put(`/admin/salles/${salle.id}`, { preserveScroll: true, onSuccess: () => setEdit(false) })}>
                        OK
                    </Button>
                </div>
                {form.errors.nom && <p className="text-xs text-red-600">{form.errors.nom}</p>}
            </li>
        );
    }
    return (
        <li className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3">
            <div>
                <b>{salle.nom}</b> {!salle.actif && <Pill className="bg-slate-100 text-slate-500">Fermée</Pill>}
                <div className="text-sm text-muted-foreground">{salle.equipement || '—'}</div>
                {salle.docteurs.length > 0 && <div className="text-xs text-teal-800 dark:text-teal-200">{salle.docteurs.join(', ')}</div>}
            </div>
            <Button size="sm" variant="outline" onClick={() => setEdit(true)}>
                Modifier
            </Button>
        </li>
    );
}

Cabinet.layout = { breadcrumbs: [{ title: 'Cabinet & salles', href: '/admin/cabinet' }] };
