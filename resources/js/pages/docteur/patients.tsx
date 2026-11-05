import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, History, MapPin, Play, Square } from 'lucide-react';
import { useEffect, useState } from 'react';
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
    arrive_le?: string | null;
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
                <Journee rdvs={rdvs} termines={termines} aujourdhui={jour === 'aujourdhui'} onModifier={setModif} />
            )}
            {modif && <ModifierRdv rdv={modif} onClose={() => setModif(null)} />}
        </Page>
    );
}

MesPatients.layout = {
    breadcrumbs: [{ title: 'Mes patients', href: '/mes-patients' }],
};

const minutesDepuis = (d?: string | null) => (d ? Math.max(0, Math.round((Date.now() - new Date(d).getTime()) / 60000)) : null);
const duree = (m: number) => (m >= 60 ? `${Math.floor(m / 60)} h ${String(m % 60).padStart(2, '0')}` : `${m} min`);

/** Alertes et infos utiles avant de soigner (allergie, urgence, ville, dernière visite, labo). */
function InfosPatient({ r }: { r: Rdv }) {
    return (
        <div className="mt-2 flex flex-col gap-1.5 text-sm">
            <div className="flex flex-wrap gap-2">
                {r.urgence && (
                    <Pill className="bg-red-100 text-red-700">
                        <AlertTriangle className="size-3" /> Urgence
                    </Pill>
                )}
                {r.patient.allergies && (
                    <Pill className="bg-red-100 text-red-700">
                        <AlertTriangle className="size-3" /> Allergie : {r.patient.allergies}
                    </Pill>
                )}
                {r.patient.vient_de_loin && (
                    <Pill className="bg-orange-100 text-orange-700">
                        <MapPin className="size-3" /> {r.patient.ville}
                    </Pill>
                )}
                {r.visite && (
                    <Pill className={r.visite === 'suivi' ? 'bg-indigo-50 text-indigo-700' : 'bg-amber-50 text-amber-800'}>
                        {r.visite === 'suivi' ? `Suivi · séance ${r.seance_num}` : '1re consultation'}
                    </Pill>
                )}
            </div>
            {r.historique && (
                <span className="flex items-start gap-1 text-muted-foreground">
                    <History className="mt-0.5 size-4 shrink-0" />
                    <span>
                        Dernière fois le {dateFr(r.historique.date)} avec {r.historique.docteur}
                        {r.historique.fait && ` : ${r.historique.fait}`}
                        {r.historique.suite && <b className="text-green-700 dark:text-green-400"> · Suite : {r.historique.suite}</b>}
                    </span>
                </span>
            )}
            {r.labo && (
                <span className={cn('flex items-center gap-1', ['pret', 'livre'].includes(r.labo.statut) ? 'text-green-700' : 'text-orange-700')}>
                    {['pret', 'livre'].includes(r.labo.statut) && <CheckCircle2 className="size-4" />}
                    Labo : {r.labo.travail} · {r.labo.label}
                </span>
            )}
        </div>
    );
}

/** Journée du docteur : patient en séance, patient suivant, puis toute la journée. */
function Journee({ rdvs, termines, aujourdhui, onModifier }: { rdvs: Rdv[]; termines: Rdv[]; aujourdhui: boolean; onModifier: (r: Rdv) => void }) {
    const { maSeance } = usePage().props as unknown as { maSeance: { id: number; debut: string; dossier: { id: number } } | null };
    const [, setTic] = useState(0);
    useEffect(() => {
        const t = setInterval(() => setTic((x) => x + 1), 30000);
        return () => clearInterval(t);
    }, []);
    const enCours = aujourdhui ? rdvs.find((r) => r.statut === 'en_cours') : undefined;
    const suivant = aujourdhui ? (rdvs.find((r) => r.statut === 'arrive') ?? rdvs.find((r) => r.statut === 'planifie')) : undefined;
    const tous = [...rdvs, ...termines].sort((a, b) => a.debut.localeCompare(b.debut));
    const occupe = !!maSeance;

    const commencer = (r: Rdv) =>
        r.patient.dossier &&
        router.post(`/dossiers/${r.patient.dossier.id}/seances`, {}, { onSuccess: () => router.visit(`/dossiers/${r.patient.dossier!.id}`) });

    const liste = (
        <Box titre={aujourdhui ? 'Toute la journée' : 'Patients de demain'}>
            {tous.length === 0 ? (
                <Empty>Aucun patient prévu.</Empty>
            ) : (
                <ul className="divide-y">
                    {tous.map((r) => {
                        const fini = ['termine', 'absent'].includes(r.statut);
                        return (
                            <li
                                key={r.id}
                                className={cn(
                                    'flex items-center gap-3 py-3',
                                    fini && 'opacity-50',
                                    r.id === enCours?.id && 'rounded-lg bg-blue-50 px-2 dark:bg-blue-950/30',
                                )}
                            >
                                <b className="w-14 text-base">{heure(r.debut)}</b>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        {r.patient.dossier ? (
                                            <Link href={`/dossiers/${r.patient.dossier.id}`} className="font-semibold hover:underline">
                                                {r.patient.nom_complet}
                                            </Link>
                                        ) : (
                                            <b>{r.patient.nom_complet}</b>
                                        )}
                                        {(r.patient.allergies || r.urgence) && <AlertTriangle className="size-4 text-red-600" />}
                                    </div>
                                    <div className="truncate text-xs text-muted-foreground">{r.mission}</div>
                                </div>
                                <Pill className={STATUT_RDV[r.statut]?.cls}>{STATUT_RDV[r.statut]?.label}</Pill>
                                {['planifie', 'arrive'].includes(r.statut) && (
                                    <button className="text-xs text-muted-foreground underline" onClick={() => onModifier(r)}>
                                        Modifier
                                    </button>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </Box>
    );

    if (!aujourdhui) return liste;

    return (
        <div className="grid gap-5 lg:grid-cols-[1.25fr_1fr]">
            <div className="flex flex-col gap-5">
                {enCours ? (
                    <div className="rounded-2xl border-2 border-blue-500 bg-gradient-to-b from-blue-50 to-white p-6 dark:from-blue-950/40 dark:to-transparent">
                        <div className="text-xs font-bold tracking-wider text-blue-800 uppercase dark:text-blue-300">● En séance maintenant</div>
                        <div className="mt-1 text-3xl font-bold">{enCours.patient.nom_complet}</div>
                        <div className="mt-1">
                            {enCours.patient.dossier && <>Dossier {enCours.patient.dossier.numero} · </>}RDV {heure(enCours.debut)} · <b>{enCours.mission}</b>
                        </div>
                        <InfosPatient r={enCours} />
                        {maSeance && (
                            <div className="mt-2 text-sm text-blue-800 dark:text-blue-300">En séance depuis {duree(minutesDepuis(maSeance.debut) ?? 0)}</div>
                        )}
                        {enCours.patient.dossier && (
                            <div className="mt-5 flex flex-wrap gap-3">
                                <Button asChild size="lg" className="bg-red-600 text-base hover:bg-red-700">
                                    <Link href={`/dossiers/${enCours.patient.dossier.id}?terminer=1`}>
                                        <Square /> Terminer la séance
                                    </Link>
                                </Button>
                                <Button asChild size="lg" variant="outline" className="text-base">
                                    <Link href={`/dossiers/${enCours.patient.dossier.id}`}>Ouvrir le dossier</Link>
                                </Button>
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="rounded-2xl border-2 border-dashed p-6 text-center text-muted-foreground">Aucun patient en séance pour le moment.</div>
                )}

                {suivant ? (
                    <div
                        className={cn(
                            'rounded-2xl border-2 p-6',
                            suivant.statut === 'arrive' ? 'border-amber-400 bg-amber-50 dark:bg-amber-950/30' : 'border-slate-200 bg-card',
                        )}
                    >
                        <div
                            className={cn(
                                'text-xs font-bold tracking-wider uppercase',
                                suivant.statut === 'arrive' ? 'text-amber-800' : 'text-muted-foreground',
                            )}
                        >
                            {suivant.statut === 'arrive' ? 'Patient suivant · déjà en salle d’attente' : 'Prochain patient · pas encore arrivé'}
                        </div>
                        <div className="mt-1 text-2xl font-bold">{suivant.patient.nom_complet}</div>
                        <div className="mt-1">
                            {heure(suivant.debut)} · {suivant.mission}
                            {suivant.statut === 'arrive' && suivant.arrive_le && (
                                <> · en salle d’attente depuis {duree(minutesDepuis(suivant.arrive_le) ?? 0)}</>
                            )}
                        </div>
                        <InfosPatient r={suivant} />
                        <div className="mt-5 flex flex-wrap gap-3">
                            {suivant.statut === 'arrive' && suivant.patient.dossier && (
                                <Button
                                    size="lg"
                                    className="flex-1 bg-green-600 text-base hover:bg-green-700"
                                    disabled={occupe}
                                    onClick={() => commencer(suivant)}
                                >
                                    <Play /> Commencer la séance
                                </Button>
                            )}
                            {suivant.patient.dossier && (
                                <Button asChild size="lg" variant="outline" className="text-base">
                                    <Link href={`/dossiers/${suivant.patient.dossier.id}`}>Ouvrir le dossier</Link>
                                </Button>
                            )}
                            <Button size="lg" variant="ghost" onClick={() => onModifier(suivant)}>
                                Modifier / annuler
                            </Button>
                        </div>
                        {occupe && suivant.statut === 'arrive' && (
                            <p className="mt-2 text-sm text-muted-foreground">Terminez d’abord la séance en cours pour commencer avec ce patient.</p>
                        )}
                    </div>
                ) : (
                    <div className="rounded-2xl border-2 border-dashed p-6 text-center text-muted-foreground">Plus aucun patient à venir aujourd’hui.</div>
                )}
            </div>
            {liste}
        </div>
    );
}

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
