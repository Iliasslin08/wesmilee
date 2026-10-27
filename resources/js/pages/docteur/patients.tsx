import { Head, Link, router, usePoll } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, History, MapPin } from 'lucide-react';
import { useState } from 'react';
import { ModifierRdv } from '@/components/cabinet/modifier-rdv';
import { Box, Empty, Page, PageTitle, Pill, Tabs } from '@/components/cabinet/ui';
import { Button } from '@/components/ui/button';
import type { Patient } from '@/lib/cabinet';
import { dateFr, ETAT_DOCTEUR, heure, STATUT_RDV } from '@/lib/cabinet';
import { cn } from '@/lib/utils';

type Rdv = {
    id: number;
    debut: string;
    fin: string | null;
    mission: string;
    statut: string;
    urgence: boolean;
    sans_rdv: boolean;
    patient: Patient;
    labo: { statut: string; label: string; travail: string } | null;
    visite?: string;
    seance_num?: number;
    historique: {
        date: string;
        docteur: string | null;
        fait: string;
        suite: string | null;
    } | null;
};

type Jour = { date: string; label: string; rdvs: Rdv[] };

export default function MesPatients({
    jour,
    dateLabel,
    rdvs,
    termines = [],
    etat,
    semaine = [],
}: {
    jour: string;
    dateLabel: string;
    rdvs: Rdv[];
    termines?: Rdv[];
    etat: string;
    semaine?: Jour[];
}) {
    usePoll(10000, {
        only: ['rdvs', 'termines', 'etat', 'maSeance', 'semaine'],
    });
    const [voirTermines, setVoirTermines] = useState(false);
    const [modif, setModif] = useState<Rdv | null>(null);

    const changerEtat = (e: string) => router.post('/mon-etat', { etat: e }, { preserveScroll: true });

    return (
        <Page>
            <Head title="Mes patients" />
            <PageTitle
                titre={`Mes patients · ${dateLabel}`}
                sousTitre="Votre état est visible en temps réel par la réception."
                actions={
                    <div className="flex gap-2">
                        {(['pret', 'occupe', 'pause'] as const).map((e) => (
                            <button
                                key={e}
                                onClick={() => changerEtat(e)}
                                className={cn(
                                    'rounded-lg border-2 px-4 py-2 text-sm font-bold transition-colors md:px-6 md:text-base',
                                    etat === e ? `${ETAT_DOCTEUR[e].cls} border-current` : 'border-border text-muted-foreground',
                                )}
                            >
                                {ETAT_DOCTEUR[e].label}
                            </button>
                        ))}
                    </div>
                }
            />
            <Tabs
                value={jour}
                onChange={(v) => router.get('/mes-patients', { jour: v })}
                items={[
                    { value: 'aujourdhui', label: "Aujourd'hui" },
                    { value: 'demain', label: 'Demain' },
                    { value: 'semaine', label: 'Ma semaine' },
                ]}
            />
            {jour === 'semaine' ? (
                <Semaine jours={semaine} onModifier={setModif} />
            ) : (
                <Box>
                    {rdvs.length === 0 ? (
                        <Empty>Aucun patient prévu.</Empty>
                    ) : (
                        <ul className="divide-y">
                            {rdvs.map((r) => (
                                <li key={r.id} className="flex flex-wrap items-center gap-4 py-4">
                                    <span className="w-16 text-lg font-bold text-teal-900 dark:text-teal-200">{heure(r.debut)}</span>
                                    <div className="min-w-56 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <b className="text-base">{r.patient.nom_complet}</b>
                                            {r.patient.dossier && <span className="text-sm text-muted-foreground">· Dossier {r.patient.dossier.numero}</span>}
                                            {r.urgence && (
                                                <Pill className="bg-red-100 text-red-700">
                                                    <AlertTriangle className="size-3" /> Urgence
                                                </Pill>
                                            )}
                                            {r.sans_rdv && <Pill className="bg-slate-100 text-slate-600">Sans RDV</Pill>}
                                            {r.patient.vient_de_loin && (
                                                <Pill className="bg-orange-100 text-orange-700">
                                                    <MapPin className="size-3" /> {r.patient.ville}
                                                </Pill>
                                            )}
                                            {r.patient.allergies && <Pill className="bg-red-100 text-red-700">Allergie : {r.patient.allergies}</Pill>}
                                        </div>
                                        <div className="mt-1 flex flex-wrap items-center gap-2 text-sm">
                                            <Pill className="bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200">{r.mission}</Pill>
                                            {r.visite && (
                                                <Pill className={r.visite === 'suivi' ? 'bg-indigo-50 text-indigo-700' : 'bg-amber-50 text-amber-800'}>
                                                    {r.visite === 'suivi' ? `Suivi · séance ${r.seance_num}` : '1re consultation'}
                                                </Pill>
                                            )}
                                            {r.historique && (
                                                <span className="flex w-full items-start gap-1 text-muted-foreground">
                                                    <History className="mt-0.5 size-4 shrink-0" />
                                                    <span>
                                                        Dernière fois le {dateFr(r.historique.date)} avec {r.historique.docteur}
                                                        {r.historique.fait && ` : ${r.historique.fait}`}
                                                        {r.historique.suite && (
                                                            <b className="text-green-700 dark:text-green-400"> · Suite : {r.historique.suite}</b>
                                                        )}
                                                    </span>
                                                </span>
                                            )}
                                            {r.labo && (
                                                <span
                                                    className={cn(
                                                        'flex items-center gap-1',
                                                        ['pret', 'livre'].includes(r.labo.statut) ? 'text-green-700' : 'text-orange-700',
                                                    )}
                                                >
                                                    {['pret', 'livre'].includes(r.labo.statut) && <CheckCircle2 className="size-4" />}
                                                    Labo : {r.labo.travail} · {r.labo.label}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <Pill className={STATUT_RDV[r.statut]?.cls}>{STATUT_RDV[r.statut]?.label}</Pill>
                                    {['planifie', 'arrive'].includes(r.statut) && (
                                        <Button variant="outline" size="sm" onClick={() => setModif(r)}>
                                            Modifier / annuler
                                        </Button>
                                    )}
                                    {r.patient.dossier && (
                                        <Button asChild>
                                            <Link href={`/dossiers/${r.patient.dossier.id}`}>Ouvrir le dossier</Link>
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </Box>
            )}
            {modif && <ModifierRdv rdv={modif} onClose={() => setModif(null)} />}
            {jour !== 'semaine' && termines.length > 0 && (
                <div>
                    <button className="text-sm text-muted-foreground underline" onClick={() => setVoirTermines(!voirTermines)}>
                        {voirTermines ? 'Masquer' : 'Voir'} les patients déjà terminés ({termines.length})
                    </button>
                    {voirTermines && (
                        <ul className="mt-2 divide-y rounded-xl border bg-card px-4 text-sm">
                            {termines.map((r) => (
                                <li key={r.id} className="flex flex-wrap items-center gap-3 py-2 text-muted-foreground">
                                    <span className="w-14">{heure(r.debut)}</span>
                                    <span className="flex-1">
                                        {r.patient.nom_complet} · {r.mission}
                                    </span>
                                    <Pill className={STATUT_RDV[r.statut]?.cls}>{STATUT_RDV[r.statut]?.label}</Pill>
                                    {r.patient.dossier && (
                                        <Link href={`/dossiers/${r.patient.dossier.id}`} className="text-primary underline">
                                            Dossier
                                        </Link>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </Page>
    );
}

MesPatients.layout = {
    breadcrumbs: [{ title: 'Mes patients', href: '/mes-patients' }],
};

/** Plan de la semaine du docteur : 7 jours, avec modifier / annuler. */
function Semaine({ jours, onModifier }: { jours: Jour[]; onModifier: (r: Rdv) => void }) {
    if (jours.length === 0) {
        return (
            <Box>
                <Empty>Aucun rendez-vous cette semaine.</Empty>
            </Box>
        );
    }
    return (
        <div className="flex flex-col gap-4">
            {jours.map((j) => (
                <Box key={j.date}>
                    <div className="mb-2 flex items-center justify-between">
                        <h3 className="font-semibold text-[#0b5f6b]">{j.label}</h3>
                        <span className="text-sm text-muted-foreground">{j.rdvs.length} rendez-vous</span>
                    </div>
                    <ul className="divide-y text-sm">
                        {j.rdvs.map((r) => (
                            <li key={r.id} className="flex flex-wrap items-center gap-3 py-2">
                                <b className="w-14">{heure(r.debut)}</b>
                                <span className="min-w-48 flex-1">
                                    <b>{r.patient.nom_complet}</b> · {r.mission}
                                    {r.urgence && <span className="ml-1 font-semibold text-red-600">· Urgence</span>}
                                </span>
                                <Pill className={STATUT_RDV[r.statut]?.cls}>{STATUT_RDV[r.statut]?.label}</Pill>
                                {['planifie', 'arrive'].includes(r.statut) && (
                                    <Button variant="outline" size="sm" onClick={() => onModifier(r)}>
                                        Modifier / annuler
                                    </Button>
                                )}
                                {r.patient.dossier && (
                                    <Button asChild size="sm" variant="ghost">
                                        <Link href={`/dossiers/${r.patient.dossier.id}`}>Dossier</Link>
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                </Box>
            ))}
        </div>
    );
}
