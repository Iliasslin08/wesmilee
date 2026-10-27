import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus, Printer, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ModifierRdv } from '@/components/cabinet/modifier-rdv';
import { Creneaux, useCreneaux } from '@/components/cabinet/prochain-rdv';
import { PatientSearch } from '@/components/cabinet/patient-search';
import { FicheACompleter } from '@/components/cabinet/fiche-a-completer';
import { Box, Empty, Field, inputCls, Modal, Page, PageTitle, Pill } from '@/components/cabinet/ui';
import { conseilles, faitCeSoin } from '@/lib/docteurs';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import type { Patient } from '@/lib/cabinet';
import { heure, STATUT_RDV } from '@/lib/cabinet';

type Docteur = {
    id: number;
    name: string;
    specialite: string | null;
    couleur: string | null;
    salle: string | null;
    taches_principales: string | null;
    fait_consultation?: boolean;
    duree_rdv?: number | null;
    disponibilites: { heure_debut: string; heure_fin: string }[];
};
type Rdv = {
    id: number;
    docteur_id: number;
    debut: string;
    fin: string;
    mission: string;
    statut: string;
    urgence: boolean;
    en_ligne?: boolean;
    rappel_envoye_le?: string | null;
    patient: Patient & { dossier?: { id: number; numero: string } | null };
    docteur: { id: number; name: string; couleur: string | null; salle: string | null } | null;
    autres_rdv?: { id: number; debut: string; mission: string; docteur: string | null }[];
};
type AFixer = {
    id: number;
    mission: string;
    notes: string | null;
    acte_id: number | null;
    docteur_id: number | null;
    created_at: string;
    patient: Patient & { dossier?: { id: number; numero: string } | null };
    docteur: { id: number; name: string } | null;
    demande_par: { id: number; name: string } | null;
};
type Init = { docteur_id?: number; heure?: string; patient?: Patient; mission?: string; acte_id?: number | null; a_fixer_id?: number };
type ActeP = { id: number; libelle: string; dents: string | null; specialite: string | null };

const decaler = (date: string, jours: number) => {
    const d = new Date(`${date}T12:00:00`);
    d.setDate(d.getDate() + jours);
    return d.toISOString().slice(0, 10);
};
const jourFr = (iso: string) => new Date(iso).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });

type Props = {
    date: string;
    periode: 'jour' | 'semaine';
    vue: 'tableau' | 'docteurs';
    docteurFiltre: number | null;
    q: string;
    dateLabel: string;
    docteurs: Docteur[];
    rdvs: Rdv[];
    aFixer?: AFixer[];
};

export default function Agenda({ date, periode, vue, docteurFiltre, q, dateLabel, docteurs, rdvs, aFixer = [] }: Props) {
    const [nouveau, setNouveau] = useState<Init | null>(null);
    const [recherche, setRecherche] = useState(q);
    const [modif, setModif] = useState<Rdv | null>(null);
    const params = { date, periode, vue, docteur: docteurFiltre ?? undefined, q: q || undefined };
    const aller = (p: Partial<Record<keyof typeof params, unknown>>) =>
        router.get('/agenda', { ...params, ...p } as Record<string, string>, { preserveState: true, preserveScroll: true });
    const pas = periode === 'semaine' ? 7 : 1;
    const imprimer = `/agenda/imprimer?date=${date}&periode=${periode}${docteurFiltre ? `&docteur=${docteurFiltre}` : ''}`;

    return (
        <Page>
            <Head title="Agenda" />
            <PageTitle
                titre={q ? `Rendez-vous de « ${q} »` : `Agenda · ${dateLabel}`}
                sousTitre="Patient au téléphone : cherchez-le, ou créez-le directement dans « Nouveau rendez-vous » (nom, prénom, téléphone)."
                actions={
                    <>
                        <Button variant="outline" asChild>
                            <a href={imprimer} target="_blank" rel="noreferrer">
                                <Printer /> Imprimer
                            </a>
                        </Button>
                        <Button onClick={() => setNouveau(docteurFiltre ? { docteur_id: docteurFiltre } : {})}>
                            <Plus /> Nouveau rendez-vous
                        </Button>
                    </>
                }
            />

            {/* Recherche rapide pendant un appel */}
            <form
                className="flex gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    aller({ q: recherche || undefined });
                }}
            >
                <div className="relative flex-1">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        className={cn(inputCls, 'h-11 pl-9 text-base')}
                        placeholder="Patient au téléphone ? Tapez son nom ou son numéro…"
                        value={recherche}
                        onChange={(e) => setRecherche(e.target.value)}
                    />
                </div>
                <Button type="submit" className="h-11">
                    Chercher
                </Button>
                {q && (
                    <Button
                        type="button"
                        variant="outline"
                        className="h-11"
                        onClick={() => {
                            setRecherche('');
                            aller({ q: undefined });
                        }}
                    >
                        Effacer
                    </Button>
                )}
            </form>

            {!q && (
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="icon" onClick={() => aller({ date: decaler(date, -pas) })} aria-label="Précédent">
                        <ChevronLeft />
                    </Button>
                    <input type="date" className={cn(inputCls, 'w-40')} value={date} onChange={(e) => aller({ date: e.target.value })} />
                    <Button variant="outline" size="icon" onClick={() => aller({ date: decaler(date, pas) })} aria-label="Suivant">
                        <ChevronRight />
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => aller({ date: new Date().toISOString().slice(0, 10) })}>
                        Aujourd'hui
                    </Button>
                    <Segment
                        value={periode}
                        onChange={(v) => aller({ periode: v })}
                        items={[
                            ['jour', 'Jour'],
                            ['semaine', 'Semaine'],
                        ]}
                    />
                    <Segment
                        value={vue}
                        onChange={(v) => aller({ vue: v })}
                        items={[
                            ['tableau', 'Tableau'],
                            ['docteurs', 'Par docteur'],
                        ]}
                    />
                </div>
            )}

            {aFixer.length > 0 && (
                <AFixerListe
                    liste={aFixer}
                    onFixer={(a) =>
                        setNouveau({ patient: a.patient, docteur_id: a.docteur_id ?? undefined, mission: a.mission, acte_id: a.acte_id, a_fixer_id: a.id })
                    }
                />
            )}

            {/* Filtre par docteur */}
            <div className="flex flex-wrap gap-2">
                <button
                    onClick={() => aller({ docteur: undefined })}
                    className={cn('rounded-full border px-3 py-1 text-sm', !docteurFiltre && 'border-primary bg-primary text-primary-foreground')}
                >
                    Tous les docteurs ({rdvs.length})
                </button>
                {docteurs.map((d) => {
                    const n = rdvs.filter((r) => r.docteur_id === d.id).length;
                    return (
                        <button
                            key={d.id}
                            onClick={() => aller({ docteur: d.id })}
                            className={cn(
                                'flex items-center gap-2 rounded-full border px-3 py-1 text-sm',
                                docteurFiltre === d.id && 'border-primary bg-primary/10 font-semibold',
                            )}
                        >
                            <span className="size-2.5 rounded-full" style={{ background: d.couleur ?? '#04a3b5' }} />
                            {d.name}
                            {!docteurFiltre && <span className="text-muted-foreground">({n})</span>}
                        </button>
                    );
                })}
            </div>

            {vue === 'docteurs' && !q ? (
                <ParDocteur
                    docteurs={docteurFiltre ? docteurs.filter((d) => d.id === docteurFiltre) : docteurs}
                    rdvs={rdvs}
                    semaine={periode === 'semaine'}
                    onNouveau={(id) => setNouveau({ docteur_id: id })}
                    onModifier={setModif}
                />
            ) : (
                <Tableau rdvs={rdvs} avecDate={periode === 'semaine' || !!q} onModifier={setModif} />
            )}

            {modif && <ModifierRdv rdv={modif} docteurs={docteurs} onClose={() => setModif(null)} />}
            {nouveau && <NouveauRdv date={date} docteurs={docteurs} init={nouveau} onClose={() => setNouveau(null)} />}
        </Page>
    );
}

function Segment({ value, onChange, items }: { value: string; onChange: (v: string) => void; items: [string, string][] }) {
    return (
        <div className="flex rounded-lg bg-muted p-1 text-sm">
            {items.map(([v, l]) => (
                <button key={v} onClick={() => onChange(v)} className={cn('rounded-md px-3 py-1', value === v && 'bg-background font-semibold shadow')}>
                    {l}
                </button>
            ))}
        </div>
    );
}

function Tableau({ rdvs, avecDate, onModifier }: { rdvs: Rdv[]; avecDate: boolean; onModifier: (r: Rdv) => void }) {
    if (rdvs.length === 0) return <Empty>Aucun rendez-vous.</Empty>;
    const groupes: [string, Rdv[]][] = avecDate
        ? Object.entries(rdvs.reduce<Record<string, Rdv[]>>((g, r) => ((g[r.debut.slice(0, 10)] ??= []).push(r), g), {}))
        : [['', rdvs]];
    return (
        <div className="flex flex-col gap-4">
            {groupes.map(([jour, liste]) => (
                <Box key={jour} titre={jour ? <span className="capitalize">{jourFr(jour)}</span> : undefined}>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-sm">
                            <thead className="bg-muted/60 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-3 py-2">Heure</th>
                                    <th className="px-3">Patient</th>
                                    <th className="px-3">Téléphone</th>
                                    <th className="px-3">Docteur</th>
                                    <th className="px-3">Soin / mission</th>
                                    <th className="px-3">Salle</th>
                                    <th className="px-3">Statut</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {liste.map((r) => (
                                    <tr key={r.id} className={cn('hover:bg-muted/40', r.urgence && 'bg-red-50/60 dark:bg-red-950/20')}>
                                        <td className="px-3 py-2.5 font-bold whitespace-nowrap">
                                            {heure(r.debut)}
                                            <span className="font-normal text-muted-foreground">–{heure(r.fin)}</span>
                                        </td>
                                        <td className="px-3 font-semibold">
                                            {r.patient.nom_complet}
                                            {r.urgence && <Pill className="ml-2 bg-red-100 text-red-700">Urgence</Pill>}
                                            {r.patient.vient_de_loin && <Pill className="ml-2 bg-orange-100 text-orange-700">{r.patient.ville}</Pill>}
                                            {r.autres_rdv?.map((f) => (
                                                <div key={f.id} className="mt-1 text-xs font-normal text-amber-900">
                                                    A aussi un RDV le{' '}
                                                    <b>
                                                        {new Date(f.debut).toLocaleDateString('fr-FR')} à {heure(f.debut)}
                                                    </b>{' '}
                                                    ({f.docteur}){' '}
                                                    <button
                                                        className="font-semibold text-red-600 underline"
                                                        onClick={() =>
                                                            confirm('Supprimer ce rendez-vous ?') &&
                                                            router.delete(`/rendez-vous/${f.id}`, { preserveScroll: true })
                                                        }
                                                    >
                                                        Supprimer ce RDV
                                                    </button>
                                                </div>
                                            ))}
                                            {r.patient.fiche_a_completer && (
                                                <span className="ml-2">
                                                    <FicheACompleter patientId={r.patient.id} />
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 whitespace-nowrap">{r.patient.telephone}</td>
                                        <td className="px-3 whitespace-nowrap">
                                            <span
                                                className="mr-1.5 inline-block size-2.5 rounded-full"
                                                style={{ background: r.docteur?.couleur ?? '#04a3b5' }}
                                            />
                                            {r.docteur?.name ?? '—'}
                                        </td>
                                        <td className="px-3">
                                            {r.mission}
                                            {r.en_ligne && <Pill className="ml-1 bg-violet-100 text-violet-800">En ligne</Pill>}
                                            {r.rappel_envoye_le && <Pill className="ml-1 bg-emerald-100 text-emerald-800">SMS ✓</Pill>}
                                        </td>
                                        <td className="px-3 whitespace-nowrap">{r.docteur?.salle}</td>
                                        <td className="px-3">
                                            <Pill className={STATUT_RDV[r.statut]?.cls}>{STATUT_RDV[r.statut]?.label}</Pill>
                                        </td>
                                        <td className="px-3 text-right whitespace-nowrap">
                                            {r.patient.dossier && (
                                                <Link href={`/dossiers/${r.patient.dossier.id}`} className="mr-3 text-primary underline">
                                                    Dossier
                                                </Link>
                                            )}
                                            {['planifie', 'arrive'].includes(r.statut) && (
                                                <button className="text-xs text-primary underline" onClick={() => onModifier(r)}>
                                                    Modifier / annuler
                                                </button>
                                            )}
                                            <SupprimerRdv rdv={r} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Box>
            ))}
        </div>
    );
}

/** Supprimer un rendez-vous (sauf séance en cours). */
function SupprimerRdv({ rdv }: { rdv: Rdv }) {
    if (rdv.statut === 'en_cours') return null;
    return (
        <button
            className="ml-3 inline-flex items-center gap-1 text-xs text-red-600 hover:underline"
            onClick={() =>
                confirm(`Supprimer le rendez-vous de ${rdv.patient.nom_complet} (${heure(rdv.debut)}) ?`) &&
                router.delete(`/rendez-vous/${rdv.id}`, { preserveScroll: true })
            }
        >
            <Trash2 className="size-3.5" /> Supprimer
        </button>
    );
}

function ParDocteur({
    docteurs,
    rdvs,
    semaine,
    onNouveau,
    onModifier,
}: {
    docteurs: Docteur[];
    rdvs: Rdv[];
    semaine: boolean;
    onNouveau: (id: number) => void;
    onModifier: (r: Rdv) => void;
}) {
    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {docteurs.map((d) => {
                const liste = rdvs.filter((r) => r.docteur_id === d.id);
                return (
                    <Box
                        key={d.id}
                        titre={
                            <span className="flex items-center gap-2">
                                <span className="size-3 rounded-full" style={{ background: d.couleur ?? '#04a3b5' }} /> {d.name}
                                <span className="text-sm font-normal text-muted-foreground">· {liste.length} RDV</span>
                            </span>
                        }
                        actions={
                            <Button size="sm" variant="outline" onClick={() => onNouveau(d.id)}>
                                <Plus /> RDV
                            </Button>
                        }
                    >
                        <p className="mb-2 text-xs text-muted-foreground">
                            {[d.specialite, d.salle].filter(Boolean).join(' · ')}
                            {!semaine && (
                                <>
                                    {' · '}
                                    {d.disponibilites.length
                                        ? d.disponibilites.map((p) => `${p.heure_debut.slice(0, 5)}–${p.heure_fin.slice(0, 5)}`).join(', ')
                                        : 'Pas disponible ce jour'}
                                </>
                            )}
                        </p>
                        {liste.length === 0 ? (
                            <Empty>Aucun rendez-vous.</Empty>
                        ) : (
                            <ul className="flex flex-col gap-2">
                                {liste.map((r) => (
                                    <li key={r.id} className="rounded-lg border-l-4 bg-muted/40 p-2 text-sm" style={{ borderColor: d.couleur ?? '#04a3b5' }}>
                                        <div className="flex items-center justify-between gap-2">
                                            <b>
                                                {semaine && <span className="capitalize">{jourFr(r.debut).split(' ')[0]} </span>}
                                                {heure(r.debut)}–{heure(r.fin)}
                                            </b>
                                            <Pill className={STATUT_RDV[r.statut]?.cls}>{STATUT_RDV[r.statut]?.label}</Pill>
                                        </div>
                                        <div className="font-medium">
                                            {r.patient.nom_complet} <span className="font-normal text-muted-foreground">· {r.patient.telephone}</span>
                                        </div>
                                        <div className="text-muted-foreground">{r.mission}</div>
                                        <div className="mt-1.5 flex items-center gap-3">
                                            {['planifie', 'arrive'].includes(r.statut) && (
                                                <button className="text-xs text-primary underline" onClick={() => onModifier(r)}>
                                                    Modifier / annuler
                                                </button>
                                            )}
                                            <SupprimerRdv rdv={r} />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Box>
                );
            })}
        </div>
    );
}

/** Prochains rendez-vous demandés par les docteurs : la réception appelle le patient et fixe la date. */
function AFixerListe({ liste, onFixer }: { liste: AFixer[]; onFixer: (a: AFixer) => void }) {
    return (
        <Box titre={<span className="text-amber-800">Rendez-vous à fixer ({liste.length}) · demandés par les docteurs</span>}>
            <ul className="divide-y text-sm">
                {liste.map((a) => (
                    <li key={a.id} className="flex flex-wrap items-center gap-3 py-2">
                        <span className="min-w-56 flex-1">
                            <b>{a.patient.nom_complet}</b> <span className="text-muted-foreground">· {a.patient.telephone}</span>
                            <br />
                            {a.mission} · <b>{a.docteur?.name ?? 'docteur à choisir'}</b>
                            {a.notes && <span className="text-amber-800"> · {a.notes}</span>}
                            <span className="text-xs text-muted-foreground"> · demandé par {a.demande_par?.name}</span>
                        </span>
                        <Button size="sm" onClick={() => onFixer(a)}>
                            Fixer la date
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => confirm('Retirer cette demande ?') && router.post(`/rdv-a-fixer/${a.id}/retirer`, {}, { preserveScroll: true })}
                        >
                            Retirer
                        </Button>
                    </li>
                ))}
            </ul>
        </Box>
    );
}

function NouveauRdv({ date, docteurs, init, onClose }: { date: string; docteurs: Docteur[]; init: Init; onClose: () => void }) {
    const [patient, setPatient] = useState<Patient | null>(init.patient ?? null);
    const [nouveauPatient, setNouveauPatient] = useState(false);
    const [actes, setActes] = useState<ActeP[]>([]);
    const form = useForm({
        patient_id: 0,
        nouveau: { nom: '', prenom: '', telephone: '' },
        creer_quand_meme: false,
        a_fixer_id: init.a_fixer_id ?? null,
        docteur_id: init.docteur_id ? String(init.docteur_id) : '',
        acte_id: init.acte_id ? String(init.acte_id) : '',
        mission: init.mission ?? '',
        date,
        heure: init.heure ?? '',
        duree: 30,
        forcer: false,
    });
    const erreurs = form.errors as Record<string, string | undefined>;
    const creneaux = useCreneaux(form.data.docteur_id, form.data.date, form.data.duree);

    useEffect(() => {
        if (!patient) {
            setActes([]);
            return;
        }
        fetch(`/patients/${patient.id}/actes`, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then(setActes)
            .catch(() => setActes([]));
    }, [patient]);

    const basculerNouveau = (oui: boolean) => {
        setNouveauPatient(oui);
        setPatient(null);
        if (oui && !form.data.mission) form.setData('mission', 'Première consultation');
    };

    const acteChoisi = actes.find((a) => String(a.id) === form.data.acte_id);
    // Soin demandé → ★ docteurs qui le font ; un autre docteur reste possible (exception)
    const soin = acteChoisi ? acteChoisi.libelle : form.data.mission;
    const suggeres = conseilles(docteurs, soin);
    const docChoisi = docteurs.find((d) => String(d.id) === form.data.docteur_id);
    const exception = docChoisi && faitCeSoin(docChoisi, soin) === false;
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

    const pret =
        (nouveauPatient ? form.data.nouveau.nom && form.data.nouveau.prenom && form.data.nouveau.telephone : patient) &&
        form.data.docteur_id &&
        form.data.heure;

    const valider = () => {
        form.transform((d) => ({
            ...d,
            patient_id: nouveauPatient ? null : (patient?.id ?? 0),
            nouveau: nouveauPatient ? d.nouveau : null,
            acte_id: d.acte_id || null,
        }));
        form.post('/agenda', { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal open xl onClose={onClose} titre={init.a_fixer_id ? 'Fixer le rendez-vous demandé' : 'Nouveau rendez-vous'}>
            <div className="flex flex-col gap-3">
                <div className="grid gap-5 lg:grid-cols-2">
                    <div className="flex flex-col gap-3">
                        {!init.a_fixer_id && (
                            <div className="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1 text-sm">
                                <button
                                    type="button"
                                    onClick={() => basculerNouveau(false)}
                                    className={cn(
                                        'rounded-md px-3 py-2 font-medium',
                                        !nouveauPatient ? 'bg-white text-primary shadow' : 'text-muted-foreground',
                                    )}
                                >
                                    Patient déjà connu
                                </button>
                                <button
                                    type="button"
                                    onClick={() => basculerNouveau(true)}
                                    className={cn(
                                        'rounded-md px-3 py-2 font-medium',
                                        nouveauPatient ? 'bg-white text-primary shadow' : 'text-muted-foreground',
                                    )}
                                >
                                    Nouveau patient (1re fois)
                                </button>
                            </div>
                        )}
                        {nouveauPatient ? (
                            <div className="grid gap-2 rounded-lg border border-primary/30 bg-primary/5 p-3 md:grid-cols-3">
                                <Field label="Nom" error={erreurs['nouveau.nom']}>
                                    <input
                                        className={inputCls}
                                        value={form.data.nouveau.nom}
                                        onChange={(e) => form.setData('nouveau', { ...form.data.nouveau, nom: e.target.value })}
                                        autoFocus
                                    />
                                </Field>
                                <Field label="Prénom" error={erreurs['nouveau.prenom']}>
                                    <input
                                        className={inputCls}
                                        value={form.data.nouveau.prenom}
                                        onChange={(e) => form.setData('nouveau', { ...form.data.nouveau, prenom: e.target.value })}
                                    />
                                </Field>
                                <Field label="Téléphone" error={erreurs['nouveau.telephone']}>
                                    <input
                                        type="tel"
                                        className={inputCls}
                                        value={form.data.nouveau.telephone}
                                        onChange={(e) => form.setData('nouveau', { ...form.data.nouveau, telephone: e.target.value })}
                                    />
                                </Field>
                                <p className="text-xs text-muted-foreground md:col-span-3">
                                    Le dossier est créé tout de suite. Le patient complète sa fiche sur la tablette le jour de la consultation.
                                </p>
                                {erreurs['nouveau.nom']?.includes('existe') && (
                                    <label className="flex items-center gap-2 text-sm md:col-span-3">
                                        <input
                                            type="checkbox"
                                            checked={form.data.creer_quand_meme}
                                            onChange={(e) => form.setData('creer_quand_meme', e.target.checked)}
                                        />{' '}
                                        C'est une autre personne : créer quand même
                                    </label>
                                )}
                            </div>
                        ) : (
                            <Field label="Patient" error={form.errors.patient_id}>
                                {init.a_fixer_id && patient ? (
                                    <div className="rounded-md border bg-teal-50 px-3 py-2 text-sm font-medium">{patient.nom_complet}</div>
                                ) : (
                                    <PatientSearch value={patient} onSelect={setPatient} />
                                )}
                            </Field>
                        )}
                        {actes.length > 0 && (
                            <Field label="Mission : acte du plan de traitement">
                                <select className={inputCls} value={form.data.acte_id} onChange={(e) => form.setData('acte_id', e.target.value)}>
                                    <option value="">— Autre (écrire la mission) —</option>
                                    {actes.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.libelle} {a.dents ? `· ${a.dents}` : ''}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        )}
                        {!form.data.acte_id && (
                            <Field label="Mission (soin à faire)" error={form.errors.mission}>
                                <input
                                    className={inputCls}
                                    value={form.data.mission}
                                    onChange={(e) => form.setData('mission', e.target.value)}
                                    placeholder="Consultation, contrôle…"
                                />
                            </Field>
                        )}
                        <Field label="Docteur" error={form.errors.docteur_id}>
                            <select className={inputCls} value={form.data.docteur_id} onChange={(e) => choisirDocteur(e.target.value)}>
                                <option value="">Choisir</option>
                                {[...suggeres, ...docteurs.filter((d) => !suggeres.includes(d))].map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {suggeres.includes(d) ? '★ ' : ''}
                                        {d.name} · {d.specialite}
                                    </option>
                                ))}
                            </select>
                            {suggeres.length > 0 && <span className="text-xs text-primary">★ = fait habituellement ce soin</span>}
                            {exception && (
                                <span className="rounded-md bg-amber-50 px-2 py-1 text-xs text-amber-800">
                                    Exception : {docChoisi.name} ne fait pas habituellement « {soin} ». C'est possible (par ex. si le patient le demande).
                                </span>
                            )}
                        </Field>
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
                                <input type="checkbox" checked={form.data.forcer} onChange={(e) => form.setData('forcer', e.target.checked)} /> Forcer quand
                                même (exception)
                            </label>
                        )}
                    </div>
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
                </div>
                <Button onClick={valider} disabled={!pret || form.processing}>
                    Enregistrer le rendez-vous
                </Button>
            </div>
        </Modal>
    );
}

Agenda.layout = { breadcrumbs: [{ title: 'Agenda', href: '/agenda' }] };
