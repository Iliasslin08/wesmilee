import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Lock, Printer } from 'lucide-react';
import { Box, Empty, Field, inputCls, Page, PageTitle, Pill, textareaCls } from '@/components/cabinet/ui';
import { Button } from '@/components/ui/button';
import { dateFr, dh } from '@/lib/cabinet';
import { cn } from '@/lib/utils';

type Paiement = {
    id: number;
    numero_recu: string;
    montant: number;
    methode: string;
    created_at: string;
    dossier: { id: number; numero: string; patient: { nom_complet: string } };
    encaisse_par: { name: string } | null;
};
type Cloture = {
    id: number;
    date: string;
    totaux: Record<string, number>;
    total: number;
    nb_paiements: number;
    especes_comptees: number | null;
    ecart: number | null;
    note: string | null;
    created_at: string;
    cloture_par: { name: string } | null;
};

type Props = {
    date: string;
    dateLabel: string;
    paiements: Paiement[];
    totaux: Record<string, number>;
    cloture: Cloture | null;
    apresCloture: number;
    historique: Cloture[];
    methodes: Record<string, string>;
};

export default function Caisse({ date, dateLabel, paiements, totaux, cloture, apresCloture, historique, methodes }: Props) {
    const total = Object.values(totaux).reduce((a, b) => a + b, 0);
    const form = useForm({ date, especes_comptees: '', note: '' });
    const ecart = form.data.especes_comptees === '' ? null : Number(form.data.especes_comptees) - (totaux.especes ?? 0);

    return (
        <Page>
            <Head title="Clôture de caisse" />
            <PageTitle
                titre="Clôture de caisse"
                sousTitre={dateLabel}
                actions={
                    <>
                        <input type="date" className={cn(inputCls, 'w-40')} value={date} onChange={(e) => router.get('/caisse', { date: e.target.value })} />
                        <Button variant="outline" onClick={() => window.print()}>
                            <Printer /> Imprimer
                        </Button>
                    </>
                }
            />

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                {Object.entries(methodes).map(([k, l]) => (
                    <div key={k} className="rounded-xl border bg-card p-4">
                        <div className="text-sm text-muted-foreground">{l}</div>
                        <div className="text-xl font-bold">{dh(totaux[k] ?? 0)}</div>
                    </div>
                ))}
                <div className="rounded-xl border bg-teal-50 p-4 dark:bg-teal-950/40">
                    <div className="text-sm text-muted-foreground">Total du jour · {paiements.length} reçu(s)</div>
                    <div className="text-xl font-bold text-teal-900 dark:text-teal-100">{dh(total)}</div>
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-[1fr_380px]">
                <Box titre="Reçus de la journée">
                    {paiements.length === 0 ? (
                        <Empty>Aucun paiement ce jour.</Empty>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="py-2">Reçu</th>
                                    <th>Patient</th>
                                    <th>Méthode</th>
                                    <th className="text-right">Montant</th>
                                    <th className="text-right">Par</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {paiements.map((p) => (
                                    <tr key={p.id} className={cn(cloture && p.created_at > cloture.created_at && 'bg-amber-50 dark:bg-amber-950/30')}>
                                        <td className="py-2 font-mono">
                                            <a href={`/paiements/${p.id}/recu`} target="_blank" rel="noreferrer" className="text-primary underline">
                                                {p.numero_recu}
                                            </a>
                                        </td>
                                        <td>
                                            {p.dossier.patient.nom_complet} <span className="text-muted-foreground">· {p.dossier.numero}</span>
                                        </td>
                                        <td>{methodes[p.methode] ?? p.methode}</td>
                                        <td className="text-right font-semibold">{dh(p.montant)}</td>
                                        <td className="text-right text-muted-foreground">{p.encaisse_par?.name}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </Box>

                {cloture ? (
                    <Box titre={<span className="flex items-center gap-2 text-green-700"><CheckCircle2 className="size-5" /> Journée clôturée</span>}>
                        <dl className="grid gap-2 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">Par</dt>
                                <dd>{cloture.cloture_par?.name}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">Total clôturé</dt>
                                <dd className="font-bold">{dh(cloture.total)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">Espèces comptées</dt>
                                <dd>{cloture.especes_comptees != null ? dh(cloture.especes_comptees) : '—'}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">Écart</dt>
                                <dd className={cn('font-bold', cloture.ecart ? 'text-red-600' : 'text-green-700')}>{cloture.ecart != null ? dh(cloture.ecart) : '—'}</dd>
                            </div>
                            {cloture.note && <p className="text-muted-foreground">{cloture.note}</p>}
                        </dl>
                        {apresCloture > 0 && (
                            <p className="mt-3 rounded-md bg-amber-50 p-2 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                                {apresCloture} reçu(s) enregistré(s) après la clôture (en jaune) : à vérifier.
                            </p>
                        )}
                    </Box>
                ) : (
                    <Box titre={<span className="flex items-center gap-2"><Lock className="size-4" /> Clôturer la journée</span>}>
                        <div className="flex flex-col gap-3">
                            <Field label="Espèces comptées dans la caisse (DH)" error={form.errors.especes_comptees}>
                                <input type="number" step="0.01" className={inputCls} value={form.data.especes_comptees} onChange={(e) => form.setData('especes_comptees', e.target.value)} />
                            </Field>
                            {ecart !== null && (
                                <p className={cn('text-sm font-semibold', Math.abs(ecart) < 0.01 ? 'text-green-700' : 'text-red-600')}>
                                    {Math.abs(ecart) < 0.01 ? 'La caisse est juste.' : `Écart : ${dh(ecart)} (${ecart > 0 ? 'en trop' : 'manquant'})`}
                                </p>
                            )}
                            <Field label="Note (facultatif)">
                                <textarea className={textareaCls} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                            </Field>
                            <Button
                                disabled={form.processing}
                                onClick={() => confirm(`Clôturer la caisse du ${dateLabel} ? Total : ${dh(total)}`) && form.post('/caisse/cloturer', { preserveScroll: true })}
                            >
                                Clôturer la caisse
                            </Button>
                        </div>
                    </Box>
                )}
            </div>

            {historique.length > 0 && (
                <Box titre="Dernières clôtures">
                    <ul className="divide-y text-sm">
                        {historique.map((c) => (
                            <li key={c.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                <button className="font-medium text-primary underline" onClick={() => router.get('/caisse', { date: c.date.slice(0, 10) })}>
                                    {dateFr(c.date)}
                                </button>
                                <span>
                                    {dh(c.total)} · {c.nb_paiements} reçu(s)
                                </span>
                                {c.ecart ? <Pill className="bg-red-100 text-red-700">Écart {dh(c.ecart)}</Pill> : <Pill className="bg-green-100 text-green-800">Juste</Pill>}
                                <span className="text-muted-foreground">{c.cloture_par?.name}</span>
                            </li>
                        ))}
                    </ul>
                </Box>
            )}
        </Page>
    );
}

Caisse.layout = { breadcrumbs: [{ title: 'Clôture de caisse', href: '/caisse' }] };
