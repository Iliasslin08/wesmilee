import { Head, router, useForm } from '@inertiajs/react';
import { Box, Field, inputCls, Page, PageTitle } from '@/components/cabinet/ui';
import { Button } from '@/components/ui/button';
import { dh } from '@/lib/cabinet';

type A = { id: number; libelle: string; specialite: string | null; prix_defaut: number | null; duree_minutes: number; necessite_labo: boolean; actif: boolean };

export default function Catalogue({ actes }: { actes: A[] }) {
    const form = useForm({ libelle: '', specialite: '', prix_defaut: '', duree_minutes: 30, necessite_labo: false, actif: true });
    return (
        <Page>
            <Head title="Catalogue des actes" />
            <PageTitle titre="Catalogue des actes et tarifs" sousTitre="Utilisé par les docteurs pour construire le plan de traitement." />
            <Box titre="Ajouter un acte">
                <div className="grid gap-3 md:grid-cols-[2fr_1.5fr_1fr_1fr_auto]">
                    <Field label="Acte" error={form.errors.libelle}>
                        <input className={inputCls} value={form.data.libelle} onChange={(e) => form.setData('libelle', e.target.value)} />
                    </Field>
                    <Field label="Spécialité">
                        <input className={inputCls} value={form.data.specialite} onChange={(e) => form.setData('specialite', e.target.value)} />
                    </Field>
                    <Field label="Prix (DH)">
                        <input type="number" className={inputCls} value={form.data.prix_defaut} onChange={(e) => form.setData('prix_defaut', e.target.value)} />
                    </Field>
                    <Field label="Durée (min)">
                        <input type="number" className={inputCls} value={form.data.duree_minutes} onChange={(e) => form.setData('duree_minutes', Number(e.target.value))} />
                    </Field>
                    <Button className="self-end" onClick={() => form.post('/admin/catalogue', { onSuccess: () => form.reset() })}>
                        Ajouter
                    </Button>
                </div>
                <label className="mt-2 flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.necessite_labo} onChange={(e) => form.setData('necessite_labo', e.target.checked)} /> Nécessite le labo
                </label>
            </Box>
            <Box>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[600px] text-sm">
                        <thead className="text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="py-2">Acte</th>
                                <th>Spécialité</th>
                                <th className="text-right">Prix</th>
                                <th className="text-right">Durée</th>
                                <th>Labo</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {actes.map((a) => (
                                <tr key={a.id} className={a.actif ? '' : 'opacity-50'}>
                                    <td className="py-2 font-semibold">{a.libelle}</td>
                                    <td>{a.specialite}</td>
                                    <td className="text-right">{a.prix_defaut != null ? dh(a.prix_defaut) : '—'}</td>
                                    <td className="text-right">{a.duree_minutes} min</td>
                                    <td>{a.necessite_labo ? 'Oui' : ''}</td>
                                    <td className="text-right">
                                        <Button size="sm" variant="outline" onClick={() => router.put(`/admin/catalogue/${a.id}`, { ...a, actif: !a.actif }, { preserveScroll: true })}>
                                            {a.actif ? 'Désactiver' : 'Activer'}
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Box>
        </Page>
    );
}

Catalogue.layout = { breadcrumbs: [{ title: 'Catalogue', href: '/admin/catalogue' }] };
