import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { useState } from 'react';
import type { Prochain } from '@/components/cabinet/prochain-rdv';
import { prochainIncomplet, ProchainRdvChamps, prochainVide } from '@/components/cabinet/prochain-rdv';
import { Box, Empty, Field, inputCls, Page, PageTitle, Pill } from '@/components/cabinet/ui';
import { Button } from '@/components/ui/button';
import type { Patient } from '@/lib/cabinet';
import { dateHeure, dh } from '@/lib/cabinet';
import type { DocSoins } from '@/lib/docteurs';
import { cn } from '@/lib/utils';

type Nom = { id: number; name: string } | null;
type Plan = {
    id: number;
    actes: string;
    notes: string | null;
    statut: 'en_attente' | 'valide' | 'refuse';
    reponse: string | null;
    created_at: string;
    valide_le: string | null;
    docteur_id: number | null;
    dossier: { id: number; numero: string; patient: Patient } | null;
    propose_par: Nom;
    valide_par: Nom;
    lignes?: { texte: string; prix: number | null }[];
};
type Ligne = { texte: string; prix: string; accepte: boolean };

export default function Plans({ enAttente, traites, docteurs }: { enAttente: Plan[]; traites: Plan[]; docteurs: DocSoins[] }) {
    return (
        <Page>
            <Head title="Devis à présenter" />
            <PageTitle
                titre="Devis à présenter"
                sousTitre="Après la consultation : expliquez le prix au patient. Il accepte tout, une partie ou rien. Pour les actes acceptés, fixez le docteur et la date."
            />
            {enAttente.length === 0 ? (
                <Box>
                    <Empty>Aucun devis en attente.</Empty>
                </Box>
            ) : (
                enAttente.map((p) => <Devis key={p.id} plan={p} docteurs={docteurs} />)
            )}

            {traites.length > 0 && (
                <Box titre="Déjà présentés">
                    <ul className="divide-y text-sm">
                        {traites.map((p) => (
                            <li key={p.id} className="flex flex-wrap items-center gap-2 py-2">
                                <Pill className={p.statut === 'valide' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}>
                                    {p.statut === 'valide' ? 'Accepté' : 'Tout refusé'}
                                </Pill>
                                {p.dossier && (
                                    <Link className="font-semibold text-primary" href={`/dossiers/${p.dossier.id}`}>
                                        {p.dossier.patient.nom_complet} · {p.dossier.numero}
                                    </Link>
                                )}
                                <span className="text-muted-foreground">{p.reponse}</span>
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {dateHeure(p.valide_le)} · {p.valide_par?.name}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Box>
            )}
        </Page>
    );
}

function Devis({ plan, docteurs }: { plan: Plan; docteurs: DocSoins[] }) {
    const [lignes, setLignes] = useState<Ligne[]>(() =>
        (plan.lignes ?? plan.actes.split('\n').map((t) => ({ texte: t, prix: null }))).map((l) => ({
            texte: l.texte,
            prix: l.prix != null ? String(l.prix) : '',
            accepte: true,
        })),
    );
    const [note, setNote] = useState('');
    const [prochain, setProchain] = useState<Prochain>(() => {
        const d = docteurs.find((x) => x.id === plan.docteur_id);
        return {
            ...prochainVide(lignes[0]?.texte ?? ''),
            mode: 'fixer',
            docteur_id: plan.docteur_id ? String(plan.docteur_id) : '',
            duree: d?.duree_rdv || 30,
        };
    });
    const [avecRdv, setAvecRdv] = useState(true);
    const form = useForm({});
    const erreurs = form.errors as Record<string, string | undefined>;
    const erreursProchain = Object.fromEntries(
        Object.entries(erreurs)
            .filter(([k]) => k.startsWith('prochain.'))
            .map(([k, e]) => [k.slice(9), e]),
    );

    const acceptes = lignes.filter((l) => l.accepte && l.texte.trim());
    const total = acceptes.reduce((t, l) => t + (Number(l.prix) || 0), 0);
    const totalPlan = lignes.reduce((t, l) => t + (Number(l.prix) || 0), 0);
    const set = (i: number, p: Partial<Ligne>) => setLignes(lignes.map((l, j) => (j === i ? { ...l, ...p } : l)));
    const rdv = avecRdv && acceptes.length > 0;

    const enregistrer = () => {
        form.transform(() => ({
            lignes: lignes
                .filter((l) => l.texte.trim())
                .map((l) => ({ texte: l.texte.trim(), prix: l.prix === '' ? null : Number(l.prix), accepte: l.accepte })),
            note,
            prochain: rdv ? { ...prochain, acte_id: prochain.acte_id || null } : null,
        }));
        form.post(`/plans/${plan.id}/valider`, { preserveScroll: true });
    };

    return (
        <Box
            titre={
                <span className="flex flex-wrap items-center gap-2">
                    <ClipboardList className="size-5 text-violet-600" />
                    {plan.dossier ? (
                        <Link className="text-primary" href={`/dossiers/${plan.dossier.id}`}>
                            {plan.dossier.patient.nom_complet} · {plan.dossier.numero}
                        </Link>
                    ) : (
                        'Dossier supprimé'
                    )}
                </span>
            }
            actions={
                <span className="text-xs text-muted-foreground">
                    Consultation : {plan.propose_par?.name ?? '—'} · {dateHeure(plan.created_at)}
                </span>
            }
        >
            <div className="flex flex-col gap-4">
                {plan.notes && (
                    <div className="rounded-lg bg-violet-50 px-3 py-2 text-sm text-violet-900 dark:bg-violet-950/30 dark:text-violet-100">
                        <b>Remarques du docteur :</b> {plan.notes}
                    </div>
                )}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="w-24 px-3 py-2">Accepté ?</th>
                                <th className="px-3">Acte</th>
                                <th className="w-36 px-3 text-right">Prix (DH)</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {lignes.map((l, i) => (
                                <tr key={i} className={cn(!l.accepte && 'bg-red-50/60 text-muted-foreground line-through dark:bg-red-950/20')}>
                                    <td className="px-3 py-2">
                                        <button
                                            type="button"
                                            onClick={() => set(i, { accepte: !l.accepte })}
                                            className={cn(
                                                'rounded-full px-3 py-1 text-xs font-bold no-underline',
                                                l.accepte ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700',
                                            )}
                                        >
                                            {l.accepte ? '✓ Oui' : '✗ Non'}
                                        </button>
                                    </td>
                                    <td className="px-3">
                                        <input className={cn(inputCls, 'h-9')} value={l.texte} onChange={(e) => set(i, { texte: e.target.value })} />
                                    </td>
                                    <td className="px-3">
                                        <input
                                            type="number"
                                            min={0}
                                            className={cn(inputCls, 'h-9 text-right')}
                                            value={l.prix}
                                            onChange={(e) => set(i, { prix: e.target.value })}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="border-t bg-muted/30 font-semibold">
                            <tr>
                                <td colSpan={2} className="px-3 py-2 text-right">
                                    Total accepté{' '}
                                    {acceptes.length < lignes.length && (
                                        <span className="font-normal text-muted-foreground">(plan complet : {dh(totalPlan)})</span>
                                    )}
                                </td>
                                <td className="px-3 text-right text-base text-teal-900 dark:text-teal-100">{dh(total)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div className="flex flex-wrap gap-2 text-xs">
                    <Button size="sm" variant="outline" onClick={() => setLignes(lignes.map((l) => ({ ...l, accepte: true })))}>
                        Tout accepté
                    </Button>
                    <Button size="sm" variant="outline" onClick={() => setLignes(lignes.map((l) => ({ ...l, accepte: false })))}>
                        Rien accepté
                    </Button>
                    <Button size="sm" variant="ghost" onClick={() => setLignes([...lignes, { texte: '', prix: '', accepte: true }])}>
                        + Ajouter une ligne
                    </Button>
                </div>

                <Field label="Remarque (raison du refus, paiement en plusieurs fois…)">
                    <input
                        className={inputCls}
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        placeholder="Ex. trop cher pour l'instant, veut réfléchir"
                    />
                </Field>

                {acceptes.length > 0 && (
                    <div className="rounded-lg border p-3 text-sm">
                        <label className="mb-3 flex items-center gap-2 font-medium">
                            <input type="checkbox" checked={avecRdv} onChange={(e) => setAvecRdv(e.target.checked)} /> Rendez-vous : docteur et date
                        </label>
                        {avecRdv && <ProchainRdvChamps value={prochain} onChange={setProchain} errors={erreursProchain} docteurs={docteurs} />}
                    </div>
                )}
                {erreurs.lignes && <p className="text-sm text-red-700">{erreurs.lignes}</p>}

                <div className="flex justify-end">
                    <Button
                        disabled={form.processing || lignes.every((l) => !l.texte.trim()) || (rdv && prochainIncomplet(prochain))}
                        variant={acceptes.length ? 'default' : 'destructive'}
                        onClick={enregistrer}
                    >
                        {acceptes.length === 0
                            ? 'Le patient refuse tout'
                            : acceptes.length === lignes.length
                              ? 'Le patient accepte tout'
                              : `Le patient accepte ${acceptes.length} acte(s) sur ${lignes.length}`}
                    </Button>
                </div>
            </div>
        </Box>
    );
}

Plans.layout = { breadcrumbs: [{ title: 'Devis à présenter', href: '/plans' }] };
