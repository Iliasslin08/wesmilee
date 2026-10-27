import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import { useState } from 'react';
import type { Prochain } from '@/components/cabinet/prochain-rdv';
import { prochainIncomplet, ProchainRdvChamps, prochainVide } from '@/components/cabinet/prochain-rdv';
import { Box, Empty, Field, Page, PageTitle, Pill, textareaCls } from '@/components/cabinet/ui';
import { Button } from '@/components/ui/button';
import type { Patient } from '@/lib/cabinet';
import { dateHeure } from '@/lib/cabinet';
import type { DocSoins } from '@/lib/docteurs';

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
    docteur: Nom;
    valide_par: Nom;
    actes_ouverts?: { id: number; libelle: string; dents: string | null }[];
};

export default function Plans({ enAttente, traites, docteurs }: { enAttente: Plan[]; traites: Plan[]; docteurs: DocSoins[] }) {
    return (
        <Page>
            <Head title="Plans à valider" />
            <PageTitle
                titre="Plans à valider"
                sousTitre="Après la consultation, le docteur propose les actes et le docteur. Vous confirmez les actes, le docteur et la date du rendez-vous."
            />
            {enAttente.length === 0 ? (
                <Box>
                    <Empty>Aucun plan en attente.</Empty>
                </Box>
            ) : (
                enAttente.map((p) => <AValider key={p.id} plan={p} docteurs={docteurs} />)
            )}

            {traites.length > 0 && (
                <Box titre="Déjà traités">
                    <ul className="divide-y text-sm">
                        {traites.map((p) => (
                            <li key={p.id} className="flex flex-wrap items-center gap-2 py-2">
                                <Pill className={p.statut === 'valide' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}>
                                    {p.statut === 'valide' ? 'Validé' : 'Refusé'}
                                </Pill>
                                {p.dossier && (
                                    <Link className="font-semibold text-primary" href={`/dossiers/${p.dossier.id}`}>
                                        {p.dossier.patient.nom_complet} · {p.dossier.numero}
                                    </Link>
                                )}
                                <span className="text-muted-foreground">{p.actes.split('\n').filter(Boolean).join(', ')}</span>
                                {p.reponse && <span className="text-red-700">({p.reponse})</span>}
                                <span className="ml-auto text-xs text-muted-foreground">{dateHeure(p.valide_le)}</span>
                            </li>
                        ))}
                    </ul>
                </Box>
            )}
        </Page>
    );
}

function AValider({ plan, docteurs }: { plan: Plan; docteurs: DocSoins[] }) {
    const lignes = plan.actes.split('\n').filter(Boolean);
    const form = useForm({ actes: plan.actes, reponse: '' });
    const [avecRdv, setAvecRdv] = useState(true);
    const [prochain, setProchain] = useState<Prochain>(() => {
        const d = docteurs.find((x) => x.id === plan.docteur_id);
        return { ...prochainVide(lignes[0] ?? ''), mode: 'fixer', docteur_id: plan.docteur_id ? String(plan.docteur_id) : '', duree: d?.duree_rdv || 30 };
    });
    const [refus, setRefus] = useState(false);
    const erreurs = form.errors as Record<string, string | undefined>;
    const erreursProchain = Object.fromEntries(
        Object.entries(erreurs)
            .filter(([k]) => k.startsWith('prochain.'))
            .map(([k, e]) => [k.slice(9), e]),
    );

    const valider = () => {
        form.transform((d) => ({ actes: d.actes, prochain: avecRdv ? { ...prochain, acte_id: prochain.acte_id || null } : null }));
        form.post(`/admin/plans/${plan.id}/valider`, { preserveScroll: true });
    };
    const refuser = () => {
        form.transform((d) => ({ reponse: d.reponse }));
        form.post(`/admin/plans/${plan.id}/refuser`, { preserveScroll: true });
    };

    return (
        <Box
            titre={
                <span className="flex flex-wrap items-center gap-2">
                    <ClipboardCheck className="size-5 text-violet-600" />
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
                    Proposé par {plan.propose_par?.name ?? '—'} · {dateHeure(plan.created_at)}
                </span>
            }
        >
            <div className="flex flex-col gap-4">
                {plan.notes && (
                    <div className="rounded-lg bg-violet-50 px-3 py-2 text-sm text-violet-900 dark:bg-violet-950/30 dark:text-violet-100">
                        <b>Remarques du docteur :</b> {plan.notes}
                    </div>
                )}
                <Field label="Actes à faire (un par ligne) : corrigez si besoin" error={erreurs.actes}>
                    <textarea
                        className={textareaCls}
                        rows={Math.max(3, lignes.length + 1)}
                        value={form.data.actes}
                        onChange={(e) => form.setData('actes', e.target.value)}
                    />
                </Field>

                <div className="rounded-lg border p-3 text-sm">
                    <label className="mb-3 flex items-center gap-2 font-medium">
                        <input type="checkbox" checked={avecRdv} onChange={(e) => setAvecRdv(e.target.checked)} /> Rendez-vous : docteur et date
                    </label>
                    {avecRdv && (
                        <ProchainRdvChamps
                            value={prochain}
                            onChange={setProchain}
                            errors={erreursProchain}
                            docteurs={docteurs}
                            actes={plan.actes_ouverts ?? []}
                        />
                    )}
                </div>

                {refus && (
                    <Field label="Motif du refus (facultatif)">
                        <textarea className={textareaCls} rows={2} value={form.data.reponse} onChange={(e) => form.setData('reponse', e.target.value)} />
                    </Field>
                )}

                <div className="flex flex-wrap justify-end gap-2">
                    {refus ? (
                        <>
                            <Button variant="outline" onClick={() => setRefus(false)}>
                                Retour
                            </Button>
                            <Button variant="destructive" disabled={form.processing} onClick={refuser}>
                                Confirmer le refus
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button variant="outline" onClick={() => setRefus(true)}>
                                Refuser
                            </Button>
                            <Button disabled={form.processing || !form.data.actes.trim() || (avecRdv && prochainIncomplet(prochain))} onClick={valider}>
                                Valider le plan
                            </Button>
                        </>
                    )}
                </div>
            </div>
        </Box>
    );
}

Plans.layout = { breadcrumbs: [{ title: 'Plans à valider', href: '/admin/plans' }] };
