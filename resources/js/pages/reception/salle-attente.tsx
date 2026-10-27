import { Head, Link, router, useForm, usePage, usePoll } from '@inertiajs/react';
import { AlertTriangle, ClipboardCheck, MapPin, UserPlus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { PatientSearch } from '@/components/cabinet/patient-search';
import { Box, Empty, Field, inputCls, Modal, Page, PageTitle, Pill } from '@/components/cabinet/ui';
import { conseilles, faitCeSoin } from '@/lib/docteurs';
import { cn } from '@/lib/utils';
import { Creneaux, useCreneaux } from '@/components/cabinet/prochain-rdv';
import { FicheACompleter } from '@/components/cabinet/fiche-a-completer';
import { Button } from '@/components/ui/button';
import type { Patient } from '@/lib/cabinet';
import { dateFr, depuis, ETAT_DOCTEUR, heure, STATUT_RDV } from '@/lib/cabinet';

type Rdv = {
    id: number;
    debut: string;
    mission: string;
    statut: string;
    sans_rdv: boolean;
    urgence: boolean;
    numero_appel: number | null;
    arrive_le: string | null;
    docteur_id: number | null;
    patient: Patient;
    docteur: { id: number; name: string } | null;
    visite?: 'premiere' | 'premiere_sans_rdv' | 'suivi';
    autres_rdv?: { id: number; debut: string; mission: string; docteur: string | null }[];
    seance_num?: number;
};

/** Les 3 types de patients : 1re consultation sans RDV, 1re consultation sur RDV (téléphone), suivi (séance n° X). */
/** Le patient est venu aujourd'hui mais a aussi un RDV plus tard (ex. venu en urgence) : on peut le supprimer. */
function AutresRdv({ r }: { r: Rdv }) {
    if (!r.autres_rdv?.length) return null;
    return (
        <div className="mt-1 flex flex-col gap-1">
            {r.autres_rdv.map((f) => (
                <div key={f.id} className="flex flex-wrap items-center gap-2 rounded-md bg-amber-50 px-2 py-1 text-xs text-amber-900">
                    A aussi un RDV le{' '}
                    <b>
                        {dateFr(f.debut)} à {heure(f.debut)}
                    </b>{' '}
                    · {f.mission} · {f.docteur}
                    <button
                        className="font-semibold text-red-600 underline"
                        onClick={() =>
                            confirm(`Supprimer le RDV du ${dateFr(f.debut)} à ${heure(f.debut)} ?`) &&
                            router.delete(`/rendez-vous/${f.id}`, { preserveScroll: true })
                        }
                    >
                        Supprimer ce RDV
                    </button>
                </div>
            ))}
        </div>
    );
}

function TypeVisite({ r }: { r: Rdv }) {
    if (r.visite === 'suivi') return <Pill className="bg-indigo-50 text-indigo-700">Suivi · séance {r.seance_num}</Pill>;
    if (r.visite === 'premiere_sans_rdv') return <Pill className="bg-slate-100 text-slate-700">1re consultation · sans RDV</Pill>;
    return <Pill className="bg-teal-50 text-teal-800">1re consultation · RDV</Pill>;
}

type Docteur = {
    id: number;
    name: string;
    specialite: string | null;
    seance_depuis?: string | null;
    etat: string;
    etat_depuis: string | null;
    salle: string | null;
    en_seance: string | null;
    en_attente: { id: number; numero_appel: number; urgence: boolean; patient: Patient }[];
    taches_principales?: string | null;
    fait_consultation?: boolean;
    duree_rdv?: number | null;
};

/** Garde le premier rendez-vous de chaque patient (évite les doublons à l'écran). */
const unique = (liste: Rdv[]) => {
    const deja = new Set<number>();
    return liste.filter((r) => (deja.has(r.patient.id) ? false : (deja.add(r.patient.id), true)));
};

export default function SalleAttente({ rdvs, docteurs }: { rdvs: Rdv[]; docteurs: Docteur[] }) {
    usePoll(5000, { only: ['rdvs', 'docteurs', 'fichesEnAttente'] });
    const { fichesEnAttente, reglages } = usePage().props as { fichesEnAttente?: number; reglages?: { seanceMaxMinutes: number } };
    const fiches = fichesEnAttente ?? 0;
    const seanceMax = reglages?.seanceMaxMinutes ?? 120;
    const [sansRdv, setSansRdv] = useState(false);
    const [choixDoc, setChoixDoc] = useState<Record<number, string>>({});

    // Chaque patient n'apparaît qu'une seule fois : en séance > en attente > à venir > archive
    const enSeance = unique(rdvs.filter((r) => r.statut === 'en_cours'));
    const vus = new Set(enSeance.map((r) => r.patient.id));
    const arrives = unique(rdvs.filter((r) => r.statut === 'arrive' && !vus.has(r.patient.id)));
    arrives.forEach((r) => vus.add(r.patient.id));
    // Ordre : arrivée → tablette (fiche) → salle d'attente → docteur
    const accueil = arrives.filter((r) => r.patient.fiche_a_completer);
    const attente = arrives.filter((r) => !r.patient.fiche_a_completer);
    const aVenir = unique(rdvs.filter((r) => r.statut === 'planifie' && !vus.has(r.patient.id)));
    aVenir.forEach((r) => vus.add(r.patient.id));
    const archives = unique(
        rdvs.filter((r) => ['termine', 'absent'].includes(r.statut) && !vus.has(r.patient.id)).sort((a, b) => b.debut.localeCompare(a.debut)),
    );
    const [voirArchive, setVoirArchive] = useState(false);

    const post = (url: string, data = {}) => router.post(url, data, { preserveScroll: true });

    return (
        <Page>
            <Head title="Salle d'attente" />
            <PageTitle
                titre="Salle d'attente"
                sousTitre={`${accueil.length ? `${accueil.length} à la tablette · ` : ''}${attente.length} patient(s) en attente · ${aVenir.length} à venir aujourd'hui`}
                actions={
                    <>
                        <Button onClick={() => setSansRdv(true)}>
                            <UserPlus /> Patient sans RDV / Prendre un RDV
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/fiches">Fiches à valider{fiches > 0 && ` (${fiches})`}</Link>
                        </Button>
                    </>
                }
            />

            {fiches > 0 && (
                <Link
                    href="/fiches"
                    className="flex items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 font-medium text-amber-900 hover:bg-amber-100"
                >
                    <ClipboardCheck className="size-5" />
                    {fiches} nouvelle(s) fiche(s) remplie(s) sur la tablette : cliquez pour vérifier et créer le dossier.
                </Link>
            )}

            <div className="grid gap-4 xl:grid-cols-[1fr_420px]">
                <div className="flex flex-col gap-4">
                    {accueil.length > 0 && (
                        <Box titre={<span className="text-amber-800">À l’accueil · fiche à remplir sur la tablette ({accueil.length})</span>}>
                            <ul className="divide-y">
                                {accueil.map((r) => (
                                    <li key={r.id} className="flex flex-wrap items-center gap-3 py-3">
                                        <span className="w-12 text-xl font-bold text-amber-700">N° {r.numero_appel}</span>
                                        <div className="min-w-48 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <b>{r.patient.nom_complet}</b>
                                                <TypeVisite r={r} />
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {r.mission} · arrivé depuis {depuis(r.arrive_le)} · passera en salle d’attente dès que sa fiche est envoyée
                                            </div>
                                            <AutresRdv r={r} />
                                        </div>
                                        <FicheACompleter patientId={r.patient.id} bouton />
                                    </li>
                                ))}
                            </ul>
                        </Box>
                    )}
                    <Box titre="En attente">
                        {attente.length === 0 ? (
                            <Empty>Personne en salle d'attente.</Empty>
                        ) : (
                            <ul className="divide-y">
                                {attente.map((r) => (
                                    <li key={r.id} className="flex flex-wrap items-center gap-3 py-3">
                                        <span className="w-12 text-xl font-bold text-primary">N° {r.numero_appel}</span>
                                        <div className="min-w-48 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <b>{r.patient.nom_complet}</b>
                                                {r.urgence && (
                                                    <Pill className="bg-red-100 text-red-700">
                                                        <AlertTriangle className="size-3" /> Urgence
                                                    </Pill>
                                                )}
                                                <TypeVisite r={r} />
                                                {r.patient.allergies && <Pill className="bg-red-50 text-red-700">Allergie</Pill>}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {r.mission} · attend depuis {depuis(r.arrive_le)}
                                            </div>
                                            <AutresRdv r={r} />
                                        </div>
                                        <select
                                            className={cn(inputCls, 'w-44')}
                                            value={choixDoc[r.id] ?? String(r.docteur_id ?? '')}
                                            onChange={(e) => setChoixDoc({ ...choixDoc, [r.id]: e.target.value })}
                                        >
                                            <option value="">Choisir un docteur</option>
                                            {docteurs.map((d) => (
                                                <option key={d.id} value={d.id}>
                                                    {d.name} · {ETAT_DOCTEUR[d.etat]?.label}
                                                </option>
                                            ))}
                                        </select>
                                        <Button
                                            onClick={() => post(`/rendez-vous/${r.id}/envoyer`, { docteur_id: choixDoc[r.id] ?? r.docteur_id })}
                                            disabled={!(choixDoc[r.id] ?? r.docteur_id)}
                                        >
                                            Envoyer
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Box>

                    <Box titre="Rendez-vous à venir aujourd'hui">
                        {aVenir.length === 0 ? (
                            <Empty>Aucun autre rendez-vous prévu.</Empty>
                        ) : (
                            <ul className="divide-y">
                                {aVenir.map((r) => (
                                    <li key={r.id} className="flex flex-wrap items-center gap-3 py-3">
                                        <span className="w-14 font-bold text-teal-900 dark:text-teal-200">{heure(r.debut)}</span>
                                        <div className="min-w-48 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <b>{r.patient.nom_complet}</b>
                                                {r.patient.vient_de_loin && (
                                                    <Pill className="bg-orange-100 text-orange-700">
                                                        <MapPin className="size-3" /> {r.patient.ville}
                                                    </Pill>
                                                )}
                                                <TypeVisite r={r} />
                                                {r.patient.fiche_a_completer && <FicheACompleter patientId={r.patient.id} />}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {r.mission} · {r.docteur?.name}
                                            </div>
                                        </div>
                                        <Button variant="outline" size="sm" onClick={() => post(`/rendez-vous/${r.id}/absent`)}>
                                            Absent
                                        </Button>
                                        <Button size="sm" onClick={() => post(`/rendez-vous/${r.id}/arrive`)}>
                                            Marquer arrivé
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Box>

                    {enSeance.length > 0 && (
                        <Box titre={`En séance (${enSeance.length})`}>
                            <ul className="divide-y text-sm">
                                {enSeance.map((r) => (
                                    <li key={r.id} className="flex flex-wrap items-center gap-3 py-2">
                                        <span className="w-14">{heure(r.debut)}</span>
                                        <span className="flex-1">
                                            {r.patient.nom_complet} · {r.docteur?.name}
                                            <AutresRdv r={r} />
                                        </span>
                                        <Pill className={STATUT_RDV[r.statut]?.cls}>{STATUT_RDV[r.statut]?.label}</Pill>
                                        {r.patient.dossier && (
                                            <Link className="text-primary underline" href={`/dossiers/${r.patient.dossier.id}`}>
                                                Dossier
                                            </Link>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </Box>
                    )}

                    {/* Patients terminés : rangés dans l'archive du jour, en attendant leur retour */}
                    {archives.length > 0 && (
                        <div className="rounded-xl border border-dashed bg-card/60 p-4">
                            <button
                                className="flex w-full items-center justify-between text-sm font-semibold text-muted-foreground"
                                onClick={() => setVoirArchive(!voirArchive)}
                            >
                                <span>Archive du jour : terminés, absents ({archives.length})</span>
                                <span>{voirArchive ? 'Masquer' : 'Afficher'}</span>
                            </button>
                            {voirArchive && (
                                <ul className="mt-2 divide-y text-sm">
                                    {archives.map((r) => (
                                        <li key={r.id} className="flex flex-wrap items-center gap-3 py-2 text-muted-foreground">
                                            <span className="w-14">{heure(r.debut)}</span>
                                            <span className="flex-1">
                                                {r.patient.nom_complet} · {r.docteur?.name} · {r.mission}
                                            </span>
                                            <Pill className={STATUT_RDV[r.statut]?.cls}>{STATUT_RDV[r.statut]?.label}</Pill>
                                            {r.patient.dossier && (
                                                <Link className="text-primary underline" href={`/dossiers/${r.patient.dossier.id}`}>
                                                    Dossier
                                                </Link>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}
                </div>

                <Box titre="Docteurs en temps réel" className="h-fit">
                    <ul className="flex flex-col gap-3">
                        {docteurs.map((d) => {
                            const e = ETAT_DOCTEUR[d.etat] ?? ETAT_DOCTEUR.absent;
                            const suivant = d.en_attente[0];
                            return (
                                <li key={d.id} className="rounded-lg border p-3">
                                    <div className="flex items-center justify-between gap-2">
                                        <div>
                                            <div className="font-semibold">{d.name}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {d.specialite} {d.salle ? `· ${d.salle}` : ''}
                                            </div>
                                        </div>
                                        <Pill className={e.cls}>
                                            <span className={`size-2 rounded-full ${e.dot}`} /> {e.label}
                                            {d.etat_depuis ? ` · ${depuis(d.etat_depuis)}` : ''}
                                        </Pill>
                                    </div>
                                    {d.en_seance && <div className="mt-2 text-sm">En séance : {d.en_seance}</div>}
                                    {d.seance_depuis && (Date.now() - new Date(d.seance_depuis).getTime()) / 60000 > seanceMax && (
                                        <div className="mt-2 flex items-center gap-1 rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-800 dark:bg-red-950/40 dark:text-red-200">
                                            <AlertTriangle className="size-3.5" /> Séance ouverte depuis {depuis(d.seance_depuis)} : oubli ?
                                        </div>
                                    )}
                                    {d.etat === 'pret' && suivant && (
                                        <Button className="mt-2 w-full" onClick={() => post(`/rendez-vous/${suivant.id}/envoyer`, { docteur_id: d.id })}>
                                            Envoyer le suivant · N° {suivant.numero_appel}
                                        </Button>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </Box>
            </div>

            <SansRdv open={sansRdv} onClose={() => setSansRdv(false)} docteurs={docteurs} />
        </Page>
    );
}

const demain = () => {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    return d.toISOString().slice(0, 10);
};

function SansRdv({ open, onClose, docteurs }: { open: boolean; onClose: () => void; docteurs: Docteur[] }) {
    const [patient, setPatient] = useState<Patient | null>(null);
    const [nouveau, setNouveau] = useState(false);
    // « ici » : le patient est au centre maintenant · « rdv » : il appelle, on fixe un rendez-vous
    const [mode, setMode] = useState<'ici' | 'rdv'>('ici');
    const form = useForm({
        patient_id: 0,
        docteur_id: '',
        mission: 'Consultation',
        urgence: false,
        nom: '',
        prenom: '',
        telephone: '',
        creer_quand_meme: false,
        date: demain(),
        heure: '',
        duree: 30,
        forcer: false,
    });
    const creneaux = useCreneaux(mode === 'rdv' ? form.data.docteur_id : '', form.data.date, form.data.duree);
    const suggeres = conseilles(docteurs, form.data.mission);
    const docChoisi = docteurs.find((d) => String(d.id) === form.data.docteur_id);
    const exception = docChoisi && faitCeSoin(docChoisi, form.data.mission) === false;
    const choisirDocteur = (id: string) => {
        const d = docteurs.find((x) => String(x.id) === id);
        form.setData((f) => ({ ...f, docteur_id: id, heure: '', duree: d?.duree_rdv || f.duree }));
    };
    // Un seul docteur fait ce soin (ex. Consultation → Dr Nassim) : il est choisi tout seul (on peut changer)
    const seul = suggeres.length === 1 ? String(suggeres[0].id) : '';
    useEffect(() => {
        if (seul && !form.data.docteur_id) choisirDocteur(seul);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [seul]);
    const errs = form.errors as Record<string, string>;
    const erreurNouveau = errs.nouveau ?? errs['nouveau.nom'] ?? errs['nouveau.prenom'] ?? errs['nouveau.telephone'];

    const fini = () => {
        form.reset();
        setPatient(null);
        setNouveau(false);
        setMode('ici');
        onClose();
    };
    const valider = () => {
        if (mode === 'rdv') {
            form.transform((d) => ({
                patient_id: nouveau ? null : (patient?.id ?? 0),
                nouveau: nouveau ? { nom: d.nom, prenom: d.prenom, telephone: d.telephone } : null,
                creer_quand_meme: d.creer_quand_meme,
                docteur_id: d.docteur_id,
                mission: d.mission,
                date: d.date,
                heure: d.heure,
                duree: d.duree,
                forcer: d.forcer,
            }));
            form.post('/agenda', { preserveScroll: true, onSuccess: fini });
            return;
        }
        form.transform((d) => ({
            patient_id: nouveau ? null : (patient?.id ?? 0),
            nouveau: nouveau ? { nom: d.nom, prenom: d.prenom, telephone: d.telephone } : null,
            creer_quand_meme: d.creer_quand_meme,
            docteur_id: d.docteur_id || null,
            mission: d.mission,
            urgence: d.urgence,
        }));
        form.post('/salle-attente/sans-rdv', { preserveScroll: true, onSuccess: fini });
    };
    const pret =
        (nouveau ? form.data.nom && form.data.prenom && form.data.telephone : !!patient) && (mode === 'ici' || (form.data.docteur_id && form.data.heure));

    return (
        <Modal open={open} xl={mode === 'rdv'} onClose={onClose} titre={mode === 'rdv' ? 'Prendre un rendez-vous' : 'Patient sans rendez-vous'}>
            <div className="flex flex-col gap-3">
                <div className={cn('grid gap-5', mode === 'rdv' && 'lg:grid-cols-2')}>
                    <div className="flex flex-col gap-3">
                        <div className="grid grid-cols-2 gap-2">
                            {(
                                [
                                    ['ici', 'Il est là maintenant', "→ salle d'attente"],
                                    ['rdv', 'Il appelle (téléphone)', '→ fixer un rendez-vous'],
                                ] as const
                            ).map(([m, titre, sous]) => (
                                <button
                                    key={m}
                                    type="button"
                                    onClick={() => setMode(m)}
                                    className={cn(
                                        'rounded-lg border-2 px-3 py-2 text-left text-sm',
                                        mode === m ? 'border-primary bg-primary/10 font-semibold text-primary' : 'border-muted hover:border-primary/40',
                                    )}
                                >
                                    {titre}
                                    <span className="block text-xs font-normal text-muted-foreground">{sous}</span>
                                </button>
                            ))}
                        </div>
                        <div className="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1 text-sm">
                            <button
                                type="button"
                                onClick={() => setNouveau(false)}
                                className={cn('rounded-md py-2', !nouveau && 'bg-background font-semibold shadow')}
                            >
                                Patient déjà venu
                            </button>
                            <button
                                type="button"
                                onClick={() => setNouveau(true)}
                                className={cn('rounded-md py-2', nouveau && 'bg-background font-semibold shadow')}
                            >
                                + Nouveau patient
                            </button>
                        </div>
                        {nouveau ? (
                            <>
                                <div className="grid grid-cols-2 gap-2">
                                    <Field label="Nom">
                                        <input className={inputCls} value={form.data.nom} onChange={(e) => form.setData('nom', e.target.value)} autoFocus />
                                    </Field>
                                    <Field label="Prénom">
                                        <input className={inputCls} value={form.data.prenom} onChange={(e) => form.setData('prenom', e.target.value)} />
                                    </Field>
                                </div>
                                <Field label="Téléphone">
                                    <input
                                        type="tel"
                                        className={inputCls}
                                        value={form.data.telephone}
                                        onChange={(e) => form.setData('telephone', e.target.value)}
                                    />
                                </Field>
                                {erreurNouveau && (
                                    <div className="rounded-md border border-amber-300 bg-amber-50 p-2 text-sm text-amber-900">
                                        {erreurNouveau}
                                        {erreurNouveau.includes('existe') && (
                                            <label className="mt-1 flex items-center gap-2 font-medium">
                                                <input
                                                    type="checkbox"
                                                    checked={form.data.creer_quand_meme}
                                                    onChange={(e) => form.setData('creer_quand_meme', e.target.checked)}
                                                />{' '}
                                                C'est une autre personne : créer quand même
                                            </label>
                                        )}
                                    </div>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    Le dossier est créé tout de suite. Le patient complétera sa fiche (CIN, antécédents) sur la tablette ou à la réception.
                                </p>
                            </>
                        ) : (
                            <Field label="Patient" error={form.errors.patient_id}>
                                <PatientSearch value={patient} onSelect={setPatient} />
                            </Field>
                        )}
                        <Field label="Mission (soin à faire)" error={form.errors.mission}>
                            <div className="mb-1 flex flex-wrap gap-1.5">
                                {['Consultation', 'Contrôle'].map((m) => (
                                    <button
                                        key={m}
                                        type="button"
                                        onClick={() => form.setData('mission', m)}
                                        className={cn(
                                            'rounded-full border px-3 py-1 text-sm',
                                            form.data.mission === m ? 'border-primary bg-primary text-white' : 'bg-background hover:border-primary',
                                        )}
                                    >
                                        {m}
                                    </button>
                                ))}
                            </div>
                            <input className={inputCls} value={form.data.mission} onChange={(e) => form.setData('mission', e.target.value)} />
                        </Field>
                        <Field label={mode === 'rdv' ? 'Docteur' : 'Docteur (facultatif, peut être choisi plus tard)'} error={form.errors.docteur_id}>
                            <select className={inputCls} value={form.data.docteur_id} onChange={(e) => choisirDocteur(e.target.value)}>
                                <option value="">{mode === 'rdv' ? 'Choisir' : 'Premier docteur disponible'}</option>
                                {[...suggeres, ...docteurs.filter((d) => !suggeres.includes(d))].map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {suggeres.includes(d) ? '★ ' : ''}
                                        {d.name} · {mode === 'ici' ? `${ETAT_DOCTEUR[d.etat]?.label} · ` : ''}
                                        {d.specialite}
                                    </option>
                                ))}
                            </select>
                            {suggeres.length > 0 && <span className="text-xs text-primary">★ = fait habituellement ce soin</span>}
                            {exception && (
                                <span className="rounded-md bg-amber-50 px-2 py-1 text-xs text-amber-800">
                                    Exception : {docChoisi.name} ne fait pas habituellement « {form.data.mission} ». C'est possible quand même.
                                </span>
                            )}
                        </Field>
                        {mode === 'rdv' ? (
                            <>
                                <div className="grid grid-cols-3 gap-2">
                                    <Field label="Date" error={form.errors.date}>
                                        <input
                                            type="date"
                                            className={inputCls}
                                            value={form.data.date}
                                            onChange={(e) => form.setData((d) => ({ ...d, date: e.target.value, heure: '' }))}
                                        />
                                    </Field>
                                    <Field label="Heure" error={form.errors.heure}>
                                        <input
                                            type="time"
                                            step={900}
                                            className={inputCls}
                                            value={form.data.heure}
                                            onChange={(e) => form.setData('heure', e.target.value)}
                                        />
                                    </Field>
                                    <Field label="Durée (min)" error={form.errors.duree}>
                                        <input
                                            type="number"
                                            min={10}
                                            step={5}
                                            className={inputCls}
                                            value={form.data.duree}
                                            onChange={(e) => form.setData('duree', Number(e.target.value))}
                                        />
                                    </Field>
                                </div>
                                {form.errors.heure && (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input type="checkbox" checked={form.data.forcer} onChange={(e) => form.setData('forcer', e.target.checked)} /> Forcer
                                        quand même (exception)
                                    </label>
                                )}
                            </>
                        ) : (
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={form.data.urgence} onChange={(e) => form.setData('urgence', e.target.checked)} />
                                Urgence (douleur) : passe en priorité
                            </label>
                        )}
                    </div>
                    {mode === 'rdv' && (
                        <div className="flex flex-col gap-2 lg:border-l lg:pl-5">
                            {form.data.docteur_id ? (
                                <>
                                    <div className="text-xs font-medium text-muted-foreground">Créneaux libres (cliquez)</div>
                                    <Creneaux creneaux={creneaux} value={form.data.heure} onPick={(h) => form.setData('heure', h)} />
                                </>
                            ) : (
                                <p className="rounded-lg border border-dashed px-3 py-6 text-center text-sm text-muted-foreground">
                                    Choisissez le docteur : ses heures libres s'affichent ici.
                                </p>
                            )}
                        </div>
                    )}
                </div>
                <Button onClick={valider} disabled={!pret || form.processing}>
                    {mode === 'rdv' ? 'Enregistrer le rendez-vous' : "Ajouter à la salle d'attente"}
                </Button>
                {!pret && (
                    <p className="-mt-1 text-center text-xs text-muted-foreground">
                        {!(nouveau ? form.data.nom && form.data.prenom && form.data.telephone : patient)
                            ? nouveau
                                ? 'Remplissez nom, prénom et téléphone.'
                                : 'Cherchez puis cliquez sur le patient.'
                            : 'Choisissez le docteur puis un créneau.'}
                    </p>
                )}
            </div>
        </Modal>
    );
}

SalleAttente.layout = { breadcrumbs: [{ title: "Salle d'attente", href: '/salle-attente' }] };
