import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarPlus,
    Camera,
    ClipboardCheck,
    FileText,
    History,
    IdCard,
    Info,
    MessageSquare,
    Play,
    Printer,
    ScanLine,
    Send,
    Square,
    Trash2,
    Upload,
} from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useEffect, useState } from 'react';
import { PatientSearch } from '@/components/cabinet/patient-search';
import { RadioForm } from '@/components/cabinet/radio-form';
import { FicheACompleter } from '@/components/cabinet/fiche-a-completer';
import { ModifierRdv } from '@/components/cabinet/modifier-rdv';
import type { Prochain } from '@/components/cabinet/prochain-rdv';
import { prochainIncomplet, ProchainRdvChamps, ProchainRdvModal, prochainVide } from '@/components/cabinet/prochain-rdv';
import { COULEURS_DENT, SchemaBouche } from '@/components/cabinet/schema-bouche';
import { Box, Empty, Field, inputCls, Modal, Page, Pill, Tabs, textareaCls } from '@/components/cabinet/ui';
import { Button } from '@/components/ui/button';
import type { Patient } from '@/lib/cabinet';
import type { MembreLabo } from '@/lib/labo';
import { dateFr, dateHeure, dh, heure, METHODES, PRIORITE_LABO, STATUT_RDV } from '@/lib/cabinet';
import { detourer } from '@/lib/detourer';
import { cn } from '@/lib/utils';

type User = { id: number; name: string; specialite?: string | null };
type Acte = {
    id: number;
    libelle: string;
    dents: string | null;
    statut: 'a_faire' | 'en_cours' | 'fait' | 'annule';
    prix_convenu?: number;
    notes: string | null;
    fait_le: string | null;
    prevu_par: User | null;
    realise_par: User | null;
    [k: string]: unknown;
};
type Seance = {
    id: number;
    debut: string;
    fin: string | null;
    duree_minutes: number | null;
    compte_rendu: string | null;
    suite: string | null;
    docteur: User;
    actes: { id: number; libelle: string; dents: string | null }[];
};
type Commande = {
    technicien?: { id: number; name: string } | null;
    id: number;
    travail: string;
    dents: string | null;
    teinte: string | null;
    materiau: string | null;
    instructions: string | null;
    date_pose: string | null;
    statut: string;
    priorite: string;
    garantie: boolean;
    parent_id: number | null;
    acte: { libelle: string } | null;
    created_at: string;
    acceptee_le?: string | null;
    date_prete?: string | null;
};
type Paiement = {
    id: number;
    numero_recu: string;
    montant: number;
    methode: string;
    date: string;
    reste_apres: number;
    encaisse_par: User | null;
};
type Doc = {
    id: number;
    type: string;
    nom_original: string | null;
    created_at: string;
    ajoute_par: User | null;
};
type LigneOrdo = { medicament: string; posologie: string; duree: string };
type Ordo = {
    id: number;
    date: string;
    lignes: LigneOrdo[];
    notes: string | null;
    docteur: User | null;
};
type RadioD = {
    id: number;
    type: string;
    type_label: string;
    dents: string | null;
    mesures: string | null;
    notes: string | null;
    created_at: string;
    vue_labo_le: string | null;
    faite_par: User | null;
    documents: { id: number; nom_original: string | null }[];
};
type AFixer = { id: number; mission: string; notes: string | null; docteur: User | null; demande_par: User | null };
type Rdv = {
    id: number;
    debut: string;
    fin: string | null;
    mission: string;
    statut: string;
    docteur_id: number | null;
    docteur: User | null;
};
type Catalogue = {
    id: number;
    libelle: string;
    prix_defaut: number | null;
    specialite: string | null;
    necessite_labo: boolean;
};

type Props = {
    dossier: {
        id: number;
        numero: string;
        ancien_numero_papier: string | null;
        statut: string;
        diagnostic: string | null;
        notes: string | null;
        prochain_controle: string | null;
        docteur_consultation_id: number | null;
        docteur_consultation: User | null;
        patient: Patient & Record<string, unknown>;
    };
    alertes: { type: string; texte: string }[];
    actes: Acte[];
    seances: Seance[];
    seanceOuverte: Seance | null;
    dents: Record<string, string>;
    etatsDents: string[];
    documents: Doc[];
    typesDocuments: Record<string, string>;
    commandes: Commande[];
    paiements: Paiement[];
    avis?: { id: number; commentaire: string; created_at: string }[];
    finance: { convenu: number; recu: number; reste: number } | null;
    prochainRdv: Rdv | null;
    rendezVous: Rdv[];
    catalogue: Catalogue[];
    docteurs: User[];
    rdvAFixer?: AFixer[];
    carte?: { photo: number | null; lien: string } | null;
    equipeLabo?: MembreLabo[];
    antecedentsListe: Record<string, string>;
    radios: RadioD[];
    typesRadio: Record<string, string>;
    ordonnances: Ordo[];
    suggestionsOrdonnance: LigneOrdo[];
    demandesRadio: {
        id: number;
        type: string;
        notes: string | null;
        created_at: string;
        demande_par: User | null;
    }[];
    peut: {
        demanderRadio: boolean;
        radio: boolean;
        identite: boolean;
        medical: boolean;
        actes?: boolean;
        papier?: boolean;
        antecedents: boolean;
        finance: boolean;
        prix: boolean;
        documents: boolean;
        labo: boolean;
        supprimer?: boolean;
        rdvTous: boolean;
        rdvMiens: number | null;
        envoyer: boolean;
    };
    plansDossier: { id: number; actes: string; notes: string | null; created_at: string; propose_par: User | null; docteur: User | null }[];
};

type Onglet =
    | 'apercu'
    | 'ordonnances'
    | 'plan'
    | 'papier'
    | 'seances'
    | 'dents'
    | 'medical'
    | 'labo'
    | 'paiements'
    | 'documents'
    | 'identite'
    | 'rdv'
    | 'carte';

export default function DossierShow(props: Props) {
    const { dossier, alertes, peut, seanceOuverte, finance } = props;
    const p = dossier.patient;
    const [onglet, setOnglet] = useState<Onglet>('apercu');
    const [terminer, setTerminer] = useState(
        () => typeof window !== 'undefined' && new URLSearchParams(window.location.search).has('terminer') && !!seanceOuverte,
    );
    const [ajoutDoc, setAjoutDoc] = useState(false);
    const [photos, setPhotos] = useState(false);
    const [radio, setRadio] = useState(false);
    const [demRadio, setDemRadio] = useState(false);

    // Séance pas arrêtée : on prévient le docteur avant qu'il quitte le dossier
    useEffect(() => {
        if (!seanceOuverte) return;
        const avantDePartir = (e: BeforeUnloadEvent) => {
            e.preventDefault();
        };
        window.addEventListener('beforeunload', avantDePartir);
        const retirer = router.on('before', (ev) => {
            const visite = ev.detail.visit;
            const url = visite.url.pathname;
            if (visite.method !== 'get' || url === `/dossiers/${dossier.id}`) return;
            if (!confirm(`La séance avec ${p.nom_complet} n'est pas arrêtée.\n\nOK = quitter quand même\nAnnuler = rester et terminer la séance`)) {
                setTerminer(true);
                return false;
            }
        });
        return () => {
            window.removeEventListener('beforeunload', avantDePartir);
            retirer();
        };
    }, [seanceOuverte, dossier.id, p.nom_complet]);
    const cinOk = props.documents.some((d) => d.type === 'cin');

    return (
        <Page>
            <Head title={`Dossier ${dossier.numero}`} />
            <div className="flex flex-wrap items-start justify-between gap-3 rounded-xl border bg-card p-4">
                <div>
                    <h1 className="text-xl font-bold text-teal-900 md:text-2xl dark:text-teal-100">
                        Dossier n° {dossier.numero} · {p.nom_complet}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {[
                            p.age ? `${p.age} ans` : null,
                            p.profession as string,
                            p.ville,
                            p.telephone,
                            dossier.ancien_numero_papier ? `ancien n° papier ${dossier.ancien_numero_papier}` : null,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                    <div className="mt-2 flex flex-wrap gap-2">
                        <Pill className={dossier.statut === 'en_traitement' ? 'bg-teal-100 text-teal-800' : 'bg-slate-100 text-slate-700'}>
                            {dossier.statut === 'en_traitement' ? 'En traitement' : 'En suivi'}
                        </Pill>
                        {dossier.docteur_consultation && (
                            <Pill className="bg-slate-100 text-slate-700">Consultation : {dossier.docteur_consultation.name}</Pill>
                        )}
                        {p.vient_de_loin && <Pill className="bg-orange-100 text-orange-700">Vient de {p.ville}</Pill>}
                        {p.fiche_a_completer && <FicheACompleter patientId={p.id} bouton={peut.identite} />}
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    {peut.supprimer && (
                        <Button
                            variant="outline"
                            className="border-red-200 text-red-700 hover:bg-red-50"
                            onClick={() =>
                                confirm(
                                    `Supprimer le dossier n° ${dossier.numero} de ${p.nom_complet} ?\nIl ira dans la corbeille (restaurable). Ses rendez-vous à venir seront annulés.`,
                                ) && router.delete(`/dossiers/${dossier.id}`)
                            }
                        >
                            <Trash2 /> Supprimer
                        </Button>
                    )}
                    {peut.demanderRadio && (
                        <Button variant="outline" onClick={() => setDemRadio(true)}>
                            <ScanLine /> Envoyer à la radio
                        </Button>
                    )}
                    {peut.radio && (
                        <Button onClick={() => setRadio(true)}>
                            <ScanLine /> Nouvelle radio
                        </Button>
                    )}
                    {peut.documents && peut.medical && (
                        <Button variant="outline" onClick={() => setPhotos(true)}>
                            <Camera /> Photos
                        </Button>
                    )}
                    {peut.documents && !peut.medical && !peut.radio && (
                        <Button variant={cinOk ? 'outline' : 'default'} onClick={() => setAjoutDoc(true)}>
                            <IdCard /> {cinOk ? 'Ajouter des papiers' : 'Ajouter CIN / papiers'}
                        </Button>
                    )}
                    {peut.medical &&
                        (seanceOuverte ? (
                            <Button onClick={() => setTerminer(true)} className="bg-red-600 hover:bg-red-700">
                                <Square /> Terminer la séance (depuis {heure(seanceOuverte.debut)})
                            </Button>
                        ) : (
                            <Button onClick={() => router.post(`/dossiers/${dossier.id}/seances`, {}, { preserveScroll: true })}>
                                <Play /> Démarrer une séance
                            </Button>
                        ))}
                </div>
            </div>

            {props.demandesRadio.length > 0 && (
                <div className="flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm font-medium text-sky-900">
                    <ScanLine className="size-4" /> Radio demandée à la radiologie :{' '}
                    {props.demandesRadio.map((d) => `${props.typesRadio[d.type] ?? d.type} (par ${d.demande_par?.name ?? '—'})`).join(', ')}
                </div>
            )}
            {alertes.length > 0 && (
                <div className="flex flex-col gap-2">
                    {alertes.map((a, i) => (
                        <div
                            key={i}
                            className={cn(
                                'flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium',
                                a.type === 'danger' && 'border-red-200 bg-red-50 text-red-800',
                                a.type === 'warning' && 'border-amber-200 bg-amber-50 text-amber-800',
                                a.type === 'info' && 'border-sky-200 bg-sky-50 text-sky-800',
                            )}
                        >
                            {a.type === 'info' ? <Info className="size-4" /> : <AlertTriangle className="size-4" />} {a.texte}
                        </div>
                    ))}
                </div>
            )}

            {props.plansDossier.map((p) => (
                <div
                    key={p.id}
                    className="flex flex-wrap items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-sm text-violet-900 dark:bg-violet-950/30 dark:text-violet-100"
                >
                    <ClipboardCheck className="size-4" />
                    <span>
                        <b>Plan chez la finance (devis à présenter au patient)</b> · proposé par {p.propose_par?.name ?? '—'}
                        {p.docteur ? ` · docteur proposé : ${p.docteur.name}` : ''} — {p.actes.split('\n').filter(Boolean).join(', ')}
                    </span>
                    {props.peut.finance && (
                        <Link href="/plans" className="ml-auto font-semibold underline">
                            Présenter le devis
                        </Link>
                    )}
                </div>
            ))}
            {(props.avis?.length ?? 0) > 0 && (
                <details className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:bg-amber-950/30 dark:text-amber-100">
                    <summary className="flex cursor-pointer items-center gap-2 font-medium">
                        <MessageSquare className="size-4" /> Commentaires du patient ({props.avis!.length}) · visible par le chef seulement
                    </summary>
                    <ul className="mt-2 divide-y divide-amber-200">
                        {props.avis!.map((a) => (
                            <li key={a.id} className="py-1.5">
                                <span className="text-xs text-muted-foreground">{dateHeure(a.created_at)}</span>
                                <p className="whitespace-pre-line">{a.commentaire}</p>
                            </li>
                        ))}
                    </ul>
                </details>
            )}

            <Tabs<Onglet>
                value={onglet}
                onChange={setOnglet}
                items={
                    [
                        { value: 'apercu', label: "Vue d'ensemble" },
                        { value: 'plan', label: 'Plan de traitement' },
                        {
                            value: 'papier',
                            label: `Dossier papier (${props.documents.filter((d) => d.type === 'ancien_dossier').length})`,
                        },
                        {
                            value: 'seances',
                            label: `Séances (${props.seances.length})`,
                        },
                        { value: 'dents', label: 'Schéma dentaire' },
                        { value: 'medical', label: 'Médical' },
                        {
                            value: 'labo',
                            label: `Labo (${props.commandes.length})`,
                        },
                        ...(finance
                            ? [
                                  {
                                      value: 'paiements' as Onglet,
                                      label: `Paiements · reste ${dh(finance.reste)}`,
                                  },
                              ]
                            : []),
                        {
                            value: 'documents',
                            label: `Documents (${props.documents.length})`,
                        },
                        ...(props.carte ? [{ value: 'carte' as Onglet, label: 'Carte patient' }] : []),
                        {
                            value: 'ordonnances',
                            label: `Ordonnances (${props.ordonnances.length})`,
                        },
                        { value: 'identite', label: 'Identité' },
                        { value: 'rdv', label: 'Rendez-vous' },
                    ] as { value: Onglet; label: string }[]
                }
            />

            {onglet === 'apercu' && <Apercu {...props} onOnglet={setOnglet} />}
            {onglet === 'plan' && <Plan {...props} onPapier={() => setOnglet('papier')} />}
            {onglet === 'papier' && <DossierPapier {...props} />}
            {onglet === 'seances' && <Seances seances={props.seances} />}
            {onglet === 'dents' && <Dents {...props} />}
            {onglet === 'medical' && <Medical {...props} />}
            {onglet === 'labo' && <Labo {...props} />}
            {onglet === 'paiements' && finance && <Paiements {...props} finance={finance} />}
            {onglet === 'documents' && <Documents {...props} />}
            {onglet === 'carte' && props.carte && <CartePatient carte={props.carte} patient={props.dossier.patient} numero={props.dossier.numero} />}
            {onglet === 'ordonnances' && <Ordonnances {...props} />}
            {onglet === 'identite' && <Identite {...props} />}
            {onglet === 'rdv' && (
                <RendezVous
                    rdvs={props.rendezVous}
                    patient={props.dossier.patient.nom_complet}
                    tous={props.peut.rdvTous}
                    miens={props.peut.envoyer ? props.peut.rdvMiens : null}
                    aFixer={props.rdvAFixer ?? []}
                    dossierId={props.dossier.id}
                    docteurs={props.docteurs}
                    actes={props.actes}
                />
            )}

            <Modal open={ajoutDoc} onClose={() => setAjoutDoc(false)} titre="Ajouter CIN / papiers du patient">
                <AjoutDocuments
                    patientId={dossier.patient.id}
                    typesDocuments={props.typesDocuments}
                    typeDefaut={cinOk ? 'autre' : 'cin'}
                    onDone={() => setAjoutDoc(false)}
                />
            </Modal>

            <Modal open={demRadio} onClose={() => setDemRadio(false)} titre="Envoyer le patient à la radiologie">
                <DemandeRadioForm
                    dossierId={dossier.id}
                    types={props.typesRadio}
                    dejaEnvoyees={props.demandesRadio.map((d) => d.type)}
                    onDone={() => setDemRadio(false)}
                />
            </Modal>
            <Modal open={radio} onClose={() => setRadio(false)} titre="Nouvelle radio (envoyée au labo)" large>
                <RadioForm dossierId={dossier.id} types={props.typesRadio} onDone={() => setRadio(false)} />
            </Modal>
            <Modal open={photos} onClose={() => setPhotos(false)} titre="Ajouter des photos au dossier">
                <PhotosRapides patientId={dossier.patient.id} onDone={() => setPhotos(false)} />
            </Modal>

            {seanceOuverte && terminer && (
                <TerminerSeance
                    seance={seanceOuverte}
                    actes={props.actes}
                    docteurs={props.docteurs}
                    envoyer={props.peut.envoyer}
                    catalogue={props.catalogue}
                    onClose={() => setTerminer(false)}
                />
            )}
        </Page>
    );
}

function DemandeRadioForm({
    dossierId,
    types,
    dejaEnvoyees = [],
    onDone,
}: {
    dossierId: number;
    types: Record<string, string>;
    dejaEnvoyees?: string[];
    onDone: () => void;
}) {
    const form = useForm({ type: Object.keys(types).find((k) => !dejaEnvoyees.includes(k)) ?? '', notes: '' });
    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap gap-2">
                {Object.entries(types).map(([k, v]) => {
                    const deja = dejaEnvoyees.includes(k);
                    return (
                        <button
                            key={k}
                            type="button"
                            disabled={deja}
                            onClick={() => form.setData('type', k)}
                            title={deja ? 'Déjà envoyée à la radio' : undefined}
                            className={cn(
                                'rounded-full border px-3 py-1 text-sm',
                                deja
                                    ? 'cursor-not-allowed border-dashed text-muted-foreground line-through'
                                    : form.data.type === k
                                      ? 'border-primary bg-primary text-primary-foreground'
                                      : 'hover:bg-muted',
                            )}
                        >
                            {v}
                            {deja && ' · déjà envoyée'}
                        </button>
                    );
                })}
            </div>
            {form.errors.type && <p className="text-sm text-red-600">{form.errors.type}</p>}
            <Field label="Consigne pour la radiologie (dents, mesures à prendre…)">
                <textarea className={textareaCls} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
            </Field>
            <Button
                disabled={form.processing || !form.data.type}
                onClick={() =>
                    form.post(`/dossiers/${dossierId}/demandes-radio`, {
                        preserveScroll: true,
                        onSuccess: onDone,
                    })
                }
            >
                <ScanLine /> Envoyer la demande
            </Button>
        </div>
    );
}

/* ---------------- Ordonnances ---------------- */
function Ordonnances({ ordonnances, suggestionsOrdonnance, dossier, peut }: Props) {
    const vide = { medicament: '', posologie: '', duree: '' };
    const form = useForm<{ lignes: LigneOrdo[]; notes: string }>({
        lignes: [{ ...vide }],
        notes: '',
    });
    const allergies = String(dossier.patient.allergies ?? '').toLowerCase();
    const risque = (m: string) => allergies.includes('pénicilline') && /amoxi|pénicil|augmentin/i.test(m);
    const setLigne = (i: number, k: keyof LigneOrdo, v: string) =>
        form.setData(
            'lignes',
            form.data.lignes.map((l, j) => (j === i ? { ...l, [k]: v } : l)),
        );
    const ajouter = (l: LigneOrdo) => {
        const lignes = form.data.lignes.filter((x) => x.medicament.trim());
        form.setData('lignes', [...lignes, { ...l }]);
    };
    useEffect(
        () =>
            router.on('flash', (ev) => {
                const o = (ev as CustomEvent).detail?.flash?.ordonnance as { id: number } | undefined;
                if (o) window.open(`/ordonnances/${o.id}/imprimer`, '_blank');
            }),
        [],
    );

    return (
        <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
            {peut.medical ? (
                <Box titre="Nouvelle ordonnance">
                    {allergies && (
                        <p className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-800">
                            ⚠ Allergie : {String(dossier.patient.allergies)}
                        </p>
                    )}
                    <div className="mb-3 flex flex-wrap gap-2">
                        {suggestionsOrdonnance.map((l) => (
                            <button
                                key={l.medicament}
                                type="button"
                                onClick={() => ajouter(l)}
                                className={cn('rounded-full border px-3 py-1 text-xs hover:bg-muted', risque(l.medicament) && 'border-red-300 text-red-700')}
                            >
                                + {l.medicament}
                            </button>
                        ))}
                    </div>
                    <div className="flex flex-col gap-2">
                        {form.data.lignes.map((l, i) => (
                            <div
                                key={i}
                                className={cn(
                                    'grid gap-2 rounded-lg border p-2 md:grid-cols-[1.3fr_1.5fr_0.7fr_auto]',
                                    risque(l.medicament) && 'border-red-400 bg-red-50',
                                )}
                            >
                                <input
                                    className={inputCls}
                                    placeholder="Médicament"
                                    value={l.medicament}
                                    onChange={(e) => setLigne(i, 'medicament', e.target.value)}
                                />
                                <input
                                    className={inputCls}
                                    placeholder="Posologie"
                                    value={l.posologie}
                                    onChange={(e) => setLigne(i, 'posologie', e.target.value)}
                                />
                                <input className={inputCls} placeholder="Durée" value={l.duree} onChange={(e) => setLigne(i, 'duree', e.target.value)} />
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() =>
                                        form.setData(
                                            'lignes',
                                            form.data.lignes.filter((_, j) => j !== i),
                                        )
                                    }
                                    aria-label="Retirer"
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                                {risque(l.medicament) && (
                                    <p className="text-xs font-semibold text-red-700 md:col-span-4">Attention : le patient est allergique à la pénicilline.</p>
                                )}
                            </div>
                        ))}
                    </div>
                    <Button variant="outline" size="sm" className="mt-2" onClick={() => form.setData('lignes', [...form.data.lignes, { ...vide }])}>
                        + Ajouter une ligne
                    </Button>
                    <Field label="Conseils (facultatif)" className="mt-3">
                        <textarea className={textareaCls} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                    </Field>
                    <Button
                        className="mt-3"
                        disabled={form.processing || !form.data.lignes.some((l) => l.medicament.trim())}
                        onClick={() => {
                            form.transform((d) => ({
                                ...d,
                                lignes: d.lignes.filter((l) => l.medicament.trim()),
                            }));
                            form.post(`/dossiers/${dossier.id}/ordonnances`, {
                                preserveScroll: true,
                                onSuccess: () => form.reset(),
                            });
                        }}
                    >
                        <Printer /> Enregistrer et imprimer
                    </Button>
                </Box>
            ) : (
                <Box titre="Ordonnances">
                    <p className="text-sm text-muted-foreground">Les ordonnances sont rédigées par les docteurs.</p>
                </Box>
            )}
            <Box titre="Ordonnances du patient">
                {ordonnances.length === 0 ? (
                    <Empty>Aucune ordonnance.</Empty>
                ) : (
                    <ul className="divide-y text-sm">
                        {ordonnances.map((o) => (
                            <li key={o.id} className="flex items-start justify-between gap-3 py-2">
                                <div>
                                    <b>
                                        {dateFr(o.date)} · {o.docteur?.name}
                                    </b>
                                    <div className="text-muted-foreground">{o.lignes.map((l) => l.medicament).join(', ')}</div>
                                </div>
                                <a
                                    href={`/ordonnances/${o.id}/imprimer`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex items-center gap-1 text-primary underline"
                                >
                                    <Printer className="size-4" /> Imprimer
                                </a>
                            </li>
                        ))}
                    </ul>
                )}
            </Box>
        </div>
    );
}

/* ---------------- Vue d'ensemble : tout le dossier sur un écran ---------------- */
const LABO_STATUT: Record<string, { l: string; c: string }> = {
    a_faire: { l: 'À faire', c: 'bg-slate-100 text-slate-600' },
    en_fabrication: { l: 'En fabrication', c: 'bg-amber-100 text-amber-800' },
    pret: { l: 'Prêt', c: 'bg-green-100 text-green-800' },
    livre: { l: 'Livré', c: 'bg-teal-100 text-teal-800' },
    pose: { l: 'Posé', c: 'bg-teal-100 text-teal-800' },
};
const ACTE_STATUT: Record<string, { l: string; c: string }> = {
    fait: { l: 'Fait', c: 'bg-green-100 text-green-800' },
    en_cours: { l: 'En cours', c: 'bg-amber-100 text-amber-800' },
    a_faire: { l: 'À faire', c: 'bg-slate-100 text-slate-600' },
};

function Apercu(props: Props & { onOnglet: (o: Onglet) => void }) {
    const { dossier, antecedentsListe, commandes, prochainRdv, actes, seances, radios, dents, onOnglet } = props;
    const p = dossier.patient;
    const antecedents = ((p.antecedents as string[] | null) ?? []).map((a) => antecedentsListe[a] ?? a);
    const pano = radios.find((r) => r.type === 'panoramique' && r.documents.length) ?? radios.find((r) => r.documents.length);
    const image = pano?.documents.find((d) => /\.(jpe?g|png|webp)$/i.test(d.nom_original ?? ''));
    const ordre = { en_cours: 0, a_faire: 1, fait: 2 } as Record<string, number>;
    const plan = actes.filter((a) => a.statut !== 'annule').sort((a, b) => (ordre[a.statut] ?? 3) - (ordre[b.statut] ?? 3));
    const faits = plan.filter((a) => a.statut === 'fait').length;
    const titre = (t: string, o?: Onglet) => (
        <div className="mb-2 flex items-center justify-between">
            <h3 className="font-semibold text-[#0b5f6b] dark:text-teal-200">{t}</h3>
            {o && (
                <button className="text-xs text-primary underline" onClick={() => onOnglet(o)}>
                    Ouvrir
                </button>
            )}
        </div>
    );
    const ligne = (g: React.ReactNode, d: React.ReactNode, key?: React.Key) => (
        <div key={key} className="flex items-center justify-between gap-3 border-b py-2 text-sm last:border-0">
            <span className="text-muted-foreground">{g}</span>
            <span className="text-right font-semibold">{d}</span>
        </div>
    );

    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <div className="rounded-xl border bg-card p-4">
                {titre('Médical', 'medical')}
                {ligne('Antécédents', antecedents.length ? antecedents.join(', ') : 'Aucun')}
                {ligne('Allergies', <span className={p.allergies ? 'text-red-700' : ''}>{(p.allergies as string) || 'Aucune'}</span>)}
                {ligne('Traitement', (p.traitements as string) || '—')}
                {dossier.diagnostic && ligne('Diagnostic', dossier.diagnostic)}
            </div>

            <div className="rounded-xl border bg-card p-4">
                {titre('Labo', 'labo')}
                {commandes.length === 0 ? (
                    <p className="py-3 text-sm text-muted-foreground">Aucun travail envoyé au labo.</p>
                ) : (
                    commandes.slice(0, 4).map((c) =>
                        ligne(
                            <span>
                                {c.travail} {c.dents && `· ${c.dents}`}
                                {c.teinte && <span className="block text-xs">Teinte {c.teinte}</span>}
                            </span>,
                            <Pill className={LABO_STATUT[c.statut]?.c}>{LABO_STATUT[c.statut]?.l ?? c.statut}</Pill>,
                            c.id,
                        ),
                    )
                )}
            </div>

            <div className="flex flex-col justify-between rounded-xl bg-gradient-to-br from-[#04a3b5] to-[#0b6f8a] p-5 text-white">
                <div className="text-xs tracking-wide uppercase opacity-85">Prochain rendez-vous</div>
                {prochainRdv ? (
                    <>
                        <div className="mt-1 text-2xl font-bold capitalize">
                            {new Date(prochainRdv.debut).toLocaleDateString('fr-FR', {
                                weekday: 'short',
                                day: '2-digit',
                                month: '2-digit',
                            })}{' '}
                            · {heure(prochainRdv.debut)}
                        </div>
                        <div className="opacity-95">
                            {prochainRdv.mission} · {prochainRdv.docteur?.name ?? '—'}
                        </div>
                    </>
                ) : (
                    <div className="mt-1 text-lg">Aucun rendez-vous prévu</div>
                )}
                <button className="mt-3 self-start text-xs underline opacity-90" onClick={() => onOnglet('rdv')}>
                    Voir tous les rendez-vous
                </button>
            </div>

            <div className="rounded-xl border bg-card p-4">
                {titre('Plan de traitement', 'plan')}
                {plan.length === 0 ? (
                    <p className="py-3 text-sm text-muted-foreground">Aucun acte pour l'instant.</p>
                ) : (
                    <>
                        {plan.slice(0, 6).map((a) => (
                            <div key={a.id} className="flex items-center justify-between gap-3 border-b py-2 last:border-0">
                                <div className="text-sm">
                                    <b>
                                        {a.libelle} {a.dents && `· ${a.dents}`}
                                    </b>
                                    <div className="text-xs text-muted-foreground">
                                        {a.statut === 'fait' ? `${dateFr(a.fait_le)} · ${a.realise_par?.name ?? ''}` : `Prévu par ${a.prevu_par?.name ?? '—'}`}
                                    </div>
                                </div>
                                <Pill className={ACTE_STATUT[a.statut]?.c}>{ACTE_STATUT[a.statut]?.l}</Pill>
                            </div>
                        ))}
                        <div className="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-gradient-to-r from-[#04a3b5] to-[#80bb4b]"
                                style={{
                                    width: `${plan.length ? (100 * faits) / plan.length : 0}%`,
                                }}
                            />
                        </div>
                        <div className="mt-1 text-xs text-muted-foreground">
                            {faits} sur {plan.length} actes faits
                        </div>
                    </>
                )}
            </div>

            <div className="rounded-xl border bg-card p-4">
                {titre('Historique', 'seances')}
                {seances.filter((x) => x.fin).length === 0 ? (
                    <p className="py-3 text-sm text-muted-foreground">Première visite.</p>
                ) : (
                    <ol className="flex flex-col gap-3">
                        {seances
                            .filter((x) => x.fin)
                            .slice(0, 5)
                            .map((x) => (
                                <li key={x.id} className="flex gap-3 text-sm">
                                    <span className="w-12 shrink-0 font-bold text-[#04a3b5]">
                                        {new Date(x.debut).toLocaleDateString('fr-FR', {
                                            day: '2-digit',
                                            month: '2-digit',
                                        })}
                                    </span>
                                    <span className="border-l-2 border-[#80bb4b] pl-3">
                                        <b>{x.docteur.name}</b> · {x.actes.length ? x.actes.map((a) => a.libelle).join(', ') : x.compte_rendu || 'Séance'}
                                        {x.suite && <span className="block text-xs text-muted-foreground">→ {x.suite}</span>}
                                    </span>
                                </li>
                            ))}
                    </ol>
                )}
            </div>

            <div className="rounded-xl border bg-card p-4">
                {titre('Radio & dents', 'dents')}
                {image ? (
                    <a href={`/documents/${image.id}`} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-lg bg-black">
                        <img src={`/documents/${image.id}`} alt="Radio" className="h-40 w-full object-contain" />
                    </a>
                ) : (
                    <div className="flex h-28 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground">
                        Pas encore de radio panoramique
                    </div>
                )}
                {pano && (
                    <div className="mt-1 text-xs text-muted-foreground">
                        {pano.type_label} · {dateFr(pano.created_at)} {pano.mesures && `· ${pano.mesures}`}
                    </div>
                )}
                <div className="mx-auto mt-2 max-w-[230px]">
                    <SchemaBouche dents={dents} mode="mixte" modifiable={false} onClic={() => undefined} compact />
                </div>
            </div>
        </div>
    );
}

/* ---------------- Plan de traitement : 3 casiers ---------------- */
function Historique({ seances, prochainRdv, actes }: Props) {
    const passees = seances.filter((x) => x.fin).slice(0, 3);
    const aFaire = actes.filter((a) => a.statut === 'a_faire' || a.statut === 'en_cours');
    return (
        <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
            <Box
                titre={
                    <span className="flex items-center gap-2">
                        <History className="size-4" /> Historique du patient
                    </span>
                }
            >
                {passees.length === 0 ? (
                    <Empty>Première visite : aucune séance avant.</Empty>
                ) : (
                    <ol className="flex flex-col gap-3 border-l-2 border-teal-200 pl-4">
                        {passees.map((x) => (
                            <li key={x.id} className="text-sm">
                                <div className="font-semibold">
                                    {dateFr(x.debut)} · {x.docteur.name}
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Fait : </span>
                                    {x.actes.length ? x.actes.map((a) => `${a.libelle}${a.dents ? ` (${a.dents})` : ''}`).join(', ') : x.compte_rendu || '—'}
                                </div>
                                {x.suite && <div className="font-medium text-green-700 dark:text-green-400">Consigne pour la suite : {x.suite}</div>}
                            </li>
                        ))}
                    </ol>
                )}
            </Box>
            <Box titre="Prochaine mission">
                {prochainRdv ? (
                    <p className="text-sm">
                        <b>
                            {dateFr(prochainRdv.debut)} à {heure(prochainRdv.debut)}
                        </b>
                        <br />
                        {prochainRdv.mission} · {prochainRdv.docteur?.name ?? '—'}
                    </p>
                ) : (
                    <p className="text-sm text-muted-foreground">Pas de rendez-vous prévu.</p>
                )}
                {aFaire.length > 0 && (
                    <p className="mt-2 text-sm">
                        <span className="text-muted-foreground">Reste à faire : </span>
                        {aFaire.map((a) => `${a.libelle}${a.dents ? ` (${a.dents})` : ''}`).join(', ')}
                    </p>
                )}
            </Box>
        </div>
    );
}

function Plan(props: Props & { onPapier?: () => void }) {
    const { actes, catalogue, dossier, peut } = props;
    const scans = props.documents.filter((d) => d.type === 'ancien_dossier').length;
    const colonnes: { statut: Acte['statut']; titre: string; cls: string }[] = [
        {
            statut: 'a_faire',
            titre: 'À faire',
            cls: 'bg-slate-100 text-slate-700',
        },
        {
            statut: 'en_cours',
            titre: 'En cours',
            cls: 'bg-amber-100 text-amber-800',
        },
        { statut: 'fait', titre: 'Fait', cls: 'bg-green-100 text-green-800' },
    ];
    const maj = (a: Acte, data: Record<string, string | number>) => router.put(`/actes/${a.id}`, data, { preserveScroll: true });
    const moi = (usePage().props as unknown as { auth: { user?: { id: number } } }).auth.user?.id;
    return (
        <div className="flex flex-col gap-4">
            {scans > 0 && (
                <button
                    type="button"
                    onClick={props.onPapier}
                    className="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-left text-sm text-amber-900 hover:bg-amber-100 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    <FileText className="size-4" /> <b>Ancien dossier papier scanné ({scans} page(s))</b> · cliquez pour le lire
                </button>
            )}
            <Historique {...props} />
            {(peut.actes ?? peut.medical) && (
                <NouvelActe
                    dossierId={dossier.id}
                    catalogue={catalogue}
                    avecPrix={peut.prix && !peut.papier}
                    papier={!!peut.papier}
                    docteurs={props.docteurs}
                />
            )}
            <div className="grid gap-4 lg:grid-cols-3">
                {colonnes.map((c) => {
                    const liste = actes.filter((a) => a.statut === c.statut);
                    return (
                        <div key={c.statut} className="rounded-xl bg-muted/50 p-3">
                            <div className="mb-3 flex items-center justify-between">
                                <h3 className="font-semibold">{c.titre}</h3>
                                <Pill className={c.cls}>{liste.length}</Pill>
                            </div>
                            <div className="flex flex-col gap-2">
                                {liste.length === 0 && <p className="py-4 text-center text-xs text-muted-foreground">Vide</p>}
                                {liste.map((a) => (
                                    <CarteActe
                                        key={a.id}
                                        acte={a}
                                        medical={peut.medical}
                                        prix={peut.prix}
                                        maj={maj}
                                        moi={moi}
                                        chef={!!peut.supprimer}
                                        papier={!!peut.papier}
                                    />
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>
            <p className="text-xs text-muted-foreground">
                Le dossier ne se ferme jamais : si le patient revient, ajoutez simplement de nouveaux actes dans « À faire ».
            </p>
        </div>
    );
}

/** Un acte dans À faire / En cours / Fait : déplacer, modifier (texte, dents) ou supprimer. */
function CarteActe({
    acte: a,
    medical,
    prix,
    maj,
    moi,
    chef,
    papier = false,
}: {
    acte: Acte;
    medical: boolean;
    prix: boolean;
    maj: (a: Acte, data: Record<string, string | number>) => void;
    moi?: number;
    chef: boolean;
    papier?: boolean;
}) {
    const [edition, setEdition] = useState(false);
    const [texte, setTexte] = useState({ libelle: a.libelle, dents: a.dents ?? '' });
    const fait = a.statut === 'fait';
    const peutSupprimerFait = fait && (chef || a.realise_par?.id === moi);
    const enregistrer = () => {
        if (!texte.libelle.trim()) return;
        router.put(
            `/actes/${a.id}`,
            { libelle: texte.libelle.trim(), dents: texte.dents.trim() },
            { preserveScroll: true, onSuccess: () => setEdition(false) },
        );
    };
    return (
        <div className="rounded-lg bg-background p-3 shadow-xs">
            {edition ? (
                <div className="flex flex-col gap-2">
                    <input
                        className={inputCls}
                        value={texte.libelle}
                        autoFocus
                        onChange={(e) => setTexte({ ...texte, libelle: e.target.value })}
                        onKeyDown={(e) => e.key === 'Enter' && enregistrer()}
                    />
                    <input
                        className={inputCls}
                        placeholder="Dent(s)"
                        value={texte.dents}
                        onChange={(e) => setTexte({ ...texte, dents: e.target.value })}
                        onKeyDown={(e) => e.key === 'Enter' && enregistrer()}
                    />
                    <div className="flex gap-1">
                        <Button size="sm" onClick={enregistrer} disabled={!texte.libelle.trim()}>
                            Enregistrer
                        </Button>
                        <Button size="sm" variant="ghost" onClick={() => setEdition(false)}>
                            Fermer
                        </Button>
                    </div>
                </div>
            ) : (
                <>
                    <div className="flex items-start justify-between gap-2">
                        <div>
                            <b className="text-teal-900 dark:text-teal-100">{a.libelle}</b>
                            {a.dents && <span className="text-sm text-muted-foreground"> · dents {a.dents}</span>}
                        </div>
                        {a.prix_convenu !== undefined && <span className="text-sm font-semibold whitespace-nowrap">{dh(a.prix_convenu)}</span>}
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {fait ? `Fait le ${dateFr(a.fait_le)} · ${a.realise_par?.name ?? ''}` : `Prévu par ${a.prevu_par?.name ?? '—'}`}
                        {a.notes ? ` · ${a.notes}` : ''}
                    </p>
                </>
            )}
            {medical && !edition && (
                <div className="mt-2 flex flex-wrap gap-1">
                    {a.statut === 'a_faire' && (
                        <Button size="sm" variant="outline" onClick={() => maj(a, { statut: 'en_cours' })}>
                            → En cours
                        </Button>
                    )}
                    {!fait && (
                        <Button size="sm" onClick={() => maj(a, { statut: 'fait' })}>
                            → Fait
                        </Button>
                    )}
                    {fait && (chef || a.realise_par?.id === moi) && (
                        <Button size="sm" variant="outline" onClick={() => confirm('Remettre cet acte dans « À faire » ?') && maj(a, { statut: 'a_faire' })}>
                            ← À faire
                        </Button>
                    )}
                    {(!fait || peutSupprimerFait) && (
                        <Button size="sm" variant="outline" onClick={() => setEdition(true)}>
                            Modifier
                        </Button>
                    )}
                    {(!fait || peutSupprimerFait) && (
                        <Button
                            size="sm"
                            variant="ghost"
                            className="text-red-600"
                            onClick={() =>
                                confirm(fait ? 'Supprimer cet acte fait ?' : 'Annuler cet acte ?') && router.delete(`/actes/${a.id}`, { preserveScroll: true })
                            }
                        >
                            {fait ? 'Supprimer' : 'Annuler'}
                        </Button>
                    )}
                </div>
            )}
            {papier && !medical && !edition && a.prevu_par?.id === moi && (
                <div className="mt-2">
                    <Button
                        size="sm"
                        variant="ghost"
                        className="text-red-600"
                        onClick={() =>
                            confirm(fait ? 'Supprimer cet acte ?' : 'Annuler cet acte ?') && router.delete(`/actes/${a.id}`, { preserveScroll: true })
                        }
                    >
                        {fait ? 'Supprimer' : 'Annuler'}
                    </Button>
                </div>
            )}
            {prix && !medical && <PrixEditable acte={a} />}
        </div>
    );
}

function PrixEditable({ acte }: { acte: Acte }) {
    const [prix, setPrix] = useState(String(acte.prix_convenu));
    return (
        <div className="mt-2 flex gap-1">
            <input className={cn(inputCls, 'h-8')} type="number" value={prix} onChange={(e) => setPrix(e.target.value)} />
            <Button size="sm" variant="outline" onClick={() => router.put(`/actes/${acte.id}`, { prix_convenu: Number(prix) }, { preserveScroll: true })}>
                Prix
            </Button>
        </div>
    );
}

function NouvelActe({
    dossierId,
    catalogue,
    avecPrix,
    papier = false,
    docteurs = [],
}: {
    dossierId: number;
    catalogue: Catalogue[];
    avecPrix: boolean;
    papier?: boolean;
    docteurs?: User[];
}) {
    const form = useForm({
        statut: (papier ? 'fait' : 'a_faire') as 'a_faire' | 'fait',
        fait_le: '',
        docteur_id: '',
        catalogue_acte_id: '',
        libelle: '',
        dents: '',
        prix_convenu: '',
        notes: '',
    });
    // On écrit librement ; si le texte correspond au catalogue, le prix du catalogue est repris
    const ecrire = (texte: string) => {
        const c = catalogue.find((x) => x.libelle.toLowerCase() === texte.trim().toLowerCase());
        form.setData({
            ...form.data,
            libelle: texte,
            catalogue_acte_id: c ? String(c.id) : '',
            prix_convenu: c?.prix_defaut ? String(c.prix_defaut) : form.data.prix_convenu,
        });
    };
    const fait = form.data.statut === 'fait';
    const envoyer = () => {
        if (!form.data.libelle.trim() || form.processing) return;
        form.post(`/dossiers/${dossierId}/actes`, {
            preserveScroll: true,
            onSuccess: () => form.reset('libelle', 'catalogue_acte_id', 'dents', 'prix_convenu', 'notes'),
        });
    };
    const entree = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            envoyer();
        }
    };
    return (
        <Box titre={papier ? 'Reprendre l’ancien dossier papier' : 'Écrire un acte'}>
            {papier && (
                <p className="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                    Recopiez les soins déjà faits sur le dossier papier (sans prix : ils ne créent pas de reste à payer), et les soins encore à faire.
                </p>
            )}
            <div className="mb-3 inline-grid grid-cols-2 gap-1 rounded-lg bg-muted p-1 text-sm">
                {(
                    [
                        ['a_faire', 'À faire'],
                        ['fait', 'Fait'],
                    ] as const
                ).map(([v, lib]) => (
                    <button
                        type="button"
                        key={v}
                        onClick={() => form.setData('statut', v)}
                        className={cn('rounded-md px-4 py-1.5 font-medium', form.data.statut === v ? 'bg-white text-primary shadow' : 'text-muted-foreground')}
                    >
                        {lib}
                    </button>
                ))}
            </div>
            <div
                className={cn(
                    'grid gap-2',
                    papier && fait
                        ? 'md:grid-cols-[2fr_1fr_1fr_1fr_auto]'
                        : avecPrix && !fait
                          ? 'md:grid-cols-[2fr_1fr_1fr_auto]'
                          : 'md:grid-cols-[2fr_1fr_auto]',
                )}
            >
                <Field label={fait ? 'Acte fait (écrire)' : 'Acte à faire (écrire)'} error={form.errors.libelle}>
                    <input
                        className={inputCls}
                        list="catalogue-actes"
                        value={form.data.libelle}
                        onChange={(e) => ecrire(e.target.value)}
                        onKeyDown={entree}
                        placeholder="Ex. Détartrage, Couronne zircone, All on 4…"
                    />
                    <datalist id="catalogue-actes">
                        {catalogue.map((c) => (
                            <option key={c.id} value={c.libelle} />
                        ))}
                    </datalist>
                </Field>
                <Field label="Dent(s)">
                    <input
                        className={inputCls}
                        placeholder="16, 26 ou maxillaire"
                        onKeyDown={entree}
                        value={form.data.dents}
                        onChange={(e) => form.setData('dents', e.target.value)}
                    />
                </Field>
                {avecPrix && !fait && (
                    <Field label="Prix convenu (DH)">
                        <input
                            className={inputCls}
                            type="number"
                            value={form.data.prix_convenu}
                            onChange={(e) => form.setData('prix_convenu', e.target.value)}
                        />
                    </Field>
                )}
                {papier && fait && (
                    <Field label="Fait le" error={form.errors.fait_le}>
                        <input className={inputCls} type="date" value={form.data.fait_le} onChange={(e) => form.setData('fait_le', e.target.value)} />
                    </Field>
                )}
                {papier && fait && (
                    <Field label="Docteur (si connu)">
                        <select className={inputCls} value={form.data.docteur_id} onChange={(e) => form.setData('docteur_id', e.target.value)}>
                            <option value="">—</option>
                            {docteurs.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                )}
                <Button className="self-end" disabled={!form.data.libelle.trim() || form.processing} onClick={envoyer}>
                    {fait ? 'Ajouter dans « Fait »' : 'Ajouter dans « À faire »'}
                </Button>
            </div>
            <div className="mt-2 flex max-h-20 flex-wrap gap-1 overflow-y-auto">
                {catalogue.map((c) => (
                    <button
                        type="button"
                        key={c.id}
                        onClick={() => ecrire(c.libelle)}
                        className={cn(
                            'rounded-full border px-2 py-0.5 text-xs hover:border-primary hover:text-primary',
                            form.data.libelle === c.libelle ? 'border-primary bg-primary/10 text-primary' : 'bg-background',
                        )}
                    >
                        {c.libelle}
                    </button>
                ))}
            </div>
            <p className="mt-1 text-xs text-muted-foreground">Écrivez librement ou cliquez un acte, puis « Entrée » pour ajouter.</p>
        </Box>
    );
}

/** Case pour écrire des actes (un par ligne) + boutons rapides du catalogue : un clic ajoute une ligne. */
function EcrireActes({
    label,
    value,
    onChange,
    catalogue,
    placeholder,
    error,
}: {
    label: string;
    value: string;
    onChange: (v: string) => void;
    catalogue: Catalogue[];
    placeholder?: string;
    error?: string;
}) {
    const ajouter = (t: string) => onChange(value.trim() ? `${value.replace(/\s+$/, '')}\n${t}` : t);
    return (
        <Field label={label} error={error}>
            <textarea className={cn(textareaCls, 'text-base')} rows={3} value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} />
            {catalogue.length > 0 && (
                <div className="mt-1.5 flex max-h-20 flex-wrap gap-1 overflow-y-auto">
                    {catalogue.map((c) => (
                        <button
                            type="button"
                            key={c.id}
                            onClick={() => ajouter(c.libelle)}
                            className="rounded-full border bg-background px-2 py-0.5 text-xs hover:border-primary hover:text-primary"
                        >
                            + {c.libelle}
                        </button>
                    ))}
                </div>
            )}
        </Field>
    );
}

function TerminerSeance({
    seance,
    actes,
    docteurs,
    envoyer,
    catalogue,
    onClose,
}: {
    seance: Seance;
    actes: Acte[];
    docteurs: User[];
    envoyer: boolean;
    catalogue: Catalogue[];
    onClose: () => void;
}) {
    const ouverts = actes.filter((a) => a.statut === 'a_faire' || a.statut === 'en_cours');
    const [plan, setPlan] = useState({
        envoyer: true,
        actes: ouverts.map((a) => `${a.libelle}${a.dents ? ` · ${a.dents}` : ''}`).join('\n'),
        docteur_id: '',
        notes: '',
    });
    const form = useForm({
        compte_rendu: '',
        suite: '',
        actes_faits: [] as number[],
        actes_en_cours: [] as number[],
        faits_texte: '',
        a_faire_texte: '',
        envoyer: false,
        envoyer_docteur_id: '',
        envoyer_mission: ouverts[0] ? `${ouverts[0].libelle}${ouverts[0].dents ? ` · ${ouverts[0].dents}` : ''}` : '',
    });
    const [avecProchain, setAvecProchain] = useState(false);
    const [prochain, setProchain] = useState<Prochain>(() =>
        prochainVide(ouverts[0] ? `${ouverts[0].libelle}${ouverts[0].dents ? ` · ${ouverts[0].dents}` : ''}` : ''),
    );
    const erreurs = form.errors as Record<string, string | undefined>;
    const erreursProchain = Object.fromEntries(
        Object.entries(erreurs)
            .filter(([k]) => k.startsWith('prochain.'))
            .map(([k, e]) => [k.slice(9), e]),
    );
    const bascule = (k: 'actes_faits' | 'actes_en_cours', id: number) =>
        form.setData(k, form.data[k].includes(id) ? form.data[k].filter((x) => x !== id) : [...form.data[k], id]);
    const autres = docteurs.filter((d) => d.id !== seance.docteur.id);

    const valider = () => {
        form.transform((d) => ({
            compte_rendu: d.compte_rendu,
            suite: d.suite,
            actes_faits: d.actes_faits,
            actes_en_cours: d.actes_en_cours,
            faits_texte: d.faits_texte,
            a_faire_texte: envoyer ? d.a_faire_texte : '',
            envoyer_docteur_id: d.envoyer && d.envoyer_docteur_id ? d.envoyer_docteur_id : null,
            envoyer_mission: d.envoyer && d.envoyer_docteur_id ? d.envoyer_mission : null,
            prochain: envoyer && avecProchain ? { ...prochain, acte_id: prochain.acte_id || null } : null,
            plan: !envoyer && plan.envoyer && plan.actes.trim() ? { actes: plan.actes, docteur_id: plan.docteur_id || null, notes: plan.notes } : null,
        }));
        form.post(`/seances/${seance.id}/terminer`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Modal open xl onClose={onClose} titre={`Terminer la séance (commencée à ${heure(seance.debut)})`}>
            <div className="flex flex-col gap-3">
                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="flex flex-col gap-3">
                        {ouverts.length > 0 && (
                            <div className="rounded-lg border p-3 text-sm">
                                <div className="mb-2 font-medium">Actes prévus : cochez ce que vous avez fait</div>
                                {ouverts.map((a) => (
                                    <div key={a.id} className="flex flex-wrap items-center justify-between gap-2 py-1">
                                        <span>
                                            {a.libelle} {a.dents ? `· ${a.dents}` : ''}
                                        </span>
                                        <span className="flex gap-3">
                                            <label className="flex items-center gap-1">
                                                <input
                                                    type="checkbox"
                                                    checked={form.data.actes_faits.includes(a.id)}
                                                    onChange={() => bascule('actes_faits', a.id)}
                                                />{' '}
                                                Fait
                                            </label>
                                            {a.statut === 'a_faire' && (
                                                <label className="flex items-center gap-1">
                                                    <input
                                                        type="checkbox"
                                                        checked={form.data.actes_en_cours.includes(a.id)}
                                                        onChange={() => bascule('actes_en_cours', a.id)}
                                                    />{' '}
                                                    Commencé
                                                </label>
                                            )}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                        <div className={cn('grid gap-3', envoyer && 'md:grid-cols-2')}>
                            <EcrireActes
                                label="✅ Fait aujourd'hui (un acte par ligne)"
                                value={form.data.faits_texte}
                                onChange={(v) => form.setData('faits_texte', v)}
                                catalogue={catalogue}
                                placeholder={'Détartrage\nComposite · 36'}
                            />
                            {envoyer && (
                                <EcrireActes
                                    label="📝 À faire ensuite (un acte par ligne)"
                                    value={form.data.a_faire_texte}
                                    onChange={(v) => form.setData('a_faire_texte', v)}
                                    catalogue={catalogue}
                                    placeholder={'Couronne zircone · 36\nContrôle'}
                                />
                            )}
                        </div>
                        <Field label="Compte rendu de la séance">
                            <textarea className={textareaCls} value={form.data.compte_rendu} onChange={(e) => form.setData('compte_rendu', e.target.value)} />
                        </Field>
                        <Field label="Prochaine mission / consigne (pour le prochain docteur ou le labo)">
                            <textarea className={textareaCls} value={form.data.suite} onChange={(e) => form.setData('suite', e.target.value)} />
                        </Field>
                    </div>

                    <div className="flex flex-col gap-3">
                        {envoyer ? (
                            <>
                                <div className={cn('rounded-lg border p-3 text-sm', form.data.envoyer && 'border-primary bg-primary/5')}>
                                    <label className="flex items-center gap-2 font-medium">
                                        <input type="checkbox" checked={form.data.envoyer} onChange={(e) => form.setData('envoyer', e.target.checked)} />
                                        <Send className="size-4" /> Envoyer le patient maintenant à un autre docteur
                                    </label>
                                    {form.data.envoyer && (
                                        <div className="mt-2 grid gap-2 md:grid-cols-2">
                                            <Field label="Docteur" error={form.errors.envoyer_docteur_id}>
                                                <select
                                                    className={inputCls}
                                                    value={form.data.envoyer_docteur_id}
                                                    onChange={(e) => form.setData('envoyer_docteur_id', e.target.value)}
                                                >
                                                    <option value="">Choisir</option>
                                                    {autres.map((d) => (
                                                        <option key={d.id} value={d.id}>
                                                            {d.name} {d.specialite ? `· ${d.specialite}` : ''}
                                                        </option>
                                                    ))}
                                                </select>
                                            </Field>
                                            <Field label="Mission" error={form.errors.envoyer_mission}>
                                                <input
                                                    className={inputCls}
                                                    value={form.data.envoyer_mission}
                                                    onChange={(e) => form.setData('envoyer_mission', e.target.value)}
                                                />
                                            </Field>
                                            <p className="text-xs text-muted-foreground md:col-span-2">
                                                Le patient quitte votre liste et passe dans la salle d'attente du docteur choisi, avec votre consigne.
                                            </p>
                                        </div>
                                    )}
                                </div>

                                <div className={cn('rounded-lg border p-3 text-sm', avecProchain && 'border-primary bg-primary/5')}>
                                    <label className="flex items-center gap-2 font-medium">
                                        <input type="checkbox" checked={avecProchain} onChange={(e) => setAvecProchain(e.target.checked)} />
                                        <CalendarPlus className="size-4" /> Prévoir le prochain rendez-vous (autre jour, autre docteur)
                                    </label>
                                    {avecProchain && (
                                        <div className="mt-3">
                                            <ProchainRdvChamps
                                                value={prochain}
                                                onChange={setProchain}
                                                errors={erreursProchain}
                                                docteurs={docteurs}
                                                actes={ouverts}
                                            />
                                        </div>
                                    )}
                                </div>
                            </>
                        ) : (
                            <div className={cn('rounded-lg border p-3 text-sm', plan.envoyer && 'border-violet-300 bg-violet-50 dark:bg-violet-950/30')}>
                                <label className="flex items-center gap-2 font-medium">
                                    <input type="checkbox" checked={plan.envoyer} onChange={(e) => setPlan({ ...plan, envoyer: e.target.checked })} />
                                    <ClipboardCheck className="size-4" /> Envoyer le plan à la finance (devis)
                                </label>
                                {plan.envoyer && (
                                    <div className="mt-3 flex flex-col gap-3">
                                        <EcrireActes
                                            label="📝 Actes à faire (un par ligne)"
                                            value={plan.actes}
                                            onChange={(v) => setPlan({ ...plan, actes: v })}
                                            catalogue={catalogue}
                                            error={erreurs['plan.actes']}
                                            placeholder={'All on 4 (chirurgie) · maxillaire\nDétartrage'}
                                        />
                                        <div className="grid gap-3 md:grid-cols-2">
                                            <Field label="Docteur proposé">
                                                <select
                                                    className={inputCls}
                                                    value={plan.docteur_id}
                                                    onChange={(e) => setPlan({ ...plan, docteur_id: e.target.value })}
                                                >
                                                    <option value="">La finance choisit</option>
                                                    {autres.map((d) => (
                                                        <option key={d.id} value={d.id}>
                                                            {d.name} {d.specialite ? `· ${d.specialite}` : ''}
                                                        </option>
                                                    ))}
                                                </select>
                                            </Field>
                                            <Field label="Remarques pour la finance">
                                                <input
                                                    className={inputCls}
                                                    value={plan.notes}
                                                    onChange={(e) => setPlan({ ...plan, notes: e.target.value })}
                                                    placeholder="Urgence, délai, préférence du patient…"
                                                />
                                            </Field>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Le patient passe chez la finance : elle explique le prix, il accepte tout, une partie ou rien, puis elle fixe le
                                            docteur et la date.
                                        </p>
                                    </div>
                                )}
                                {erreurs.plan && <p className="mt-2 text-xs text-red-700">{erreurs.plan}</p>}
                            </div>
                        )}
                    </div>
                </div>

                <Button
                    onClick={valider}
                    disabled={
                        form.processing ||
                        (envoyer && form.data.envoyer && !form.data.envoyer_docteur_id) ||
                        (envoyer && avecProchain && prochainIncomplet(prochain)) ||
                        (!envoyer && plan.envoyer && !plan.actes.trim())
                    }
                >
                    {envoyer && form.data.envoyer
                        ? 'Terminer et envoyer le patient'
                        : !envoyer && plan.envoyer
                          ? 'Terminer et envoyer le plan à la finance'
                          : 'Terminer et passer « Prêt »'}
                </Button>
            </div>
        </Modal>
    );
}

/** Photos du docteur : un clic = l'appareil photo s'ouvre, la photo part directement dans le dossier. */
function PhotosRapides({ patientId, onDone }: { patientId: number; onDone: () => void }) {
    const [envoi, setEnvoi] = useState<string | null>(null);
    const envoyer = (type: string, fichiers: FileList | null) => {
        if (!fichiers?.length) return;
        setEnvoi(type);
        router.post(
            `/patients/${patientId}/documents`,
            { type, fichiers: Array.from(fichiers) },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => setEnvoi(null),
                onSuccess: onDone,
            },
        );
    };
    const boutons: [string, string][] = [
        ['photo_avant', 'Photo avant'],
        ['photo_apres', 'Photo après'],
        ['radio', 'Radio'],
        ['autre', 'Autre'],
    ];
    return (
        <div className="grid grid-cols-2 gap-3">
            {boutons.map(([type, label]) => (
                <label
                    key={type}
                    className={cn(
                        'flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-teal-300 bg-teal-50/40 p-5 text-center font-semibold hover:bg-teal-50 dark:bg-teal-950/20',
                        envoi && 'pointer-events-none opacity-50',
                    )}
                >
                    <Camera className="size-8 text-teal-700" />
                    {envoi === type ? 'Envoi…' : label}
                    <input type="file" accept="image/*" capture="environment" multiple className="hidden" onChange={(e) => envoyer(type, e.target.files)} />
                </label>
            ))}
            <p className="col-span-2 text-center text-xs text-muted-foreground">
                Sur tablette, l'appareil photo s'ouvre directement. Sur PC, choisissez les fichiers.
            </p>
        </div>
    );
}

/* ---------------- Séances ---------------- */
function Seances({ seances }: { seances: Seance[] }) {
    if (seances.length === 0) return <Empty>Aucune séance pour l'instant.</Empty>;
    return (
        <div className="flex flex-col gap-3">
            {seances.map((s) => (
                <Box key={s.id}>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <b>
                            {dateFr(s.debut)} · {heure(s.debut)}–{s.fin ? heure(s.fin) : 'en cours'} {s.duree_minutes != null ? `· ${s.duree_minutes} min` : ''}
                        </b>
                        <Pill className="bg-teal-50 text-teal-800">{s.docteur.name}</Pill>
                    </div>
                    {s.actes.length > 0 && (
                        <p className="mt-1 text-sm">Actes : {s.actes.map((a) => `${a.libelle}${a.dents ? ` (${a.dents})` : ''}`).join(', ')}</p>
                    )}
                    {s.compte_rendu && <p className="mt-1 text-sm whitespace-pre-line">{s.compte_rendu}</p>}
                    {s.suite && <p className="mt-1 text-sm font-medium text-green-700">Suite : {s.suite}</p>}
                </Box>
            ))}
        </div>
    );
}

/* ---------------- Schéma dentaire ---------------- */
function Dents({ dents, etatsDents, dossier, peut }: Props) {
    const [outil, setOutil] = useState('carie');
    // Un seul schéma, comme sur le dossier papier : dents définitives à l'extérieur, dents de lait à l'intérieur
    // Affichage immédiat, puis enregistrement (plusieurs clics rapides possibles)
    const [local, setLocal] = useState<Record<string, string>>({});
    const vue = { ...dents, ...local };
    const clic = (d: number) => {
        const etat = vue[d] === outil ? 'saine' : outil;
        setLocal((l) => ({ ...l, [d]: etat }));
        router.post(
            `/dossiers/${dossier.id}/dents`,
            { dent: d, etat },
            {
                preserveScroll: true,
                preserveState: true,
                async: true,
                only: ['dents'],
            },
        );
    };

    return (
        <Box titre="Schéma dentaire" actions={<span className="text-xs text-muted-foreground">Extérieur : dents définitives · intérieur : dents de lait</span>}>
            <div className="grid gap-6 lg:grid-cols-[1fr_240px]">
                <SchemaBouche
                    dents={Object.fromEntries(Object.entries(vue).filter(([, e]) => e !== 'saine'))}
                    mode="mixte"
                    modifiable={peut.medical}
                    onClic={clic}
                />
                {peut.medical ? (
                    <div className="flex flex-col gap-2 text-sm">
                        <p className="font-semibold">1. Choisir l'état</p>
                        {etatsDents.map((e) => (
                            <button
                                key={e}
                                onClick={() => setOutil(e)}
                                className={cn(
                                    'flex items-center gap-2 rounded-lg border px-3 py-2 text-left',
                                    outil === e ? 'border-primary bg-primary/10 font-semibold ring-2 ring-primary' : 'hover:bg-muted',
                                )}
                            >
                                <span
                                    className="inline-block size-4 rounded"
                                    style={{
                                        background: COULEURS_DENT[e]?.fill,
                                        border: `1.5px solid ${COULEURS_DENT[e]?.stroke}`,
                                    }}
                                />
                                {COULEURS_DENT[e]?.texte ?? e}
                            </button>
                        ))}
                        <p className="mt-2 font-semibold">2. Cliquer sur la dent</p>
                        <p className="text-xs text-muted-foreground">Recliquer sur une dent avec le même état la remet « saine ».</p>
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">Seuls les docteurs modifient le schéma dentaire.</p>
                )}
            </div>
        </Box>
    );
}

/* ---------------- Médical ---------------- */
function Medical({ dossier, docteurs, peut, antecedentsListe }: Props) {
    const p = dossier.patient;
    const form = useForm({
        diagnostic: dossier.diagnostic ?? '',
        notes: dossier.notes ?? '',
        statut: dossier.statut,
        prochain_controle: dossier.prochain_controle?.slice(0, 10) ?? '',
        docteur_consultation_id: dossier.docteur_consultation_id ? String(dossier.docteur_consultation_id) : '',
    });
    const ante = useForm({
        antecedents: (p.antecedents as string[] | null) ?? [],
        allergies: (p.allergies as string | null) ?? '',
        traitements: (p.traitements as string | null) ?? '',
        antecedents_autres: (p.antecedents_autres as string | null) ?? '',
    });

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <Box titre="Antécédents (fiche du patient)">
                <div className="flex flex-col gap-3">
                    <div className="flex flex-wrap gap-2 text-sm">
                        {Object.entries(antecedentsListe).map(([k, v]) => {
                            const coche = ante.data.antecedents.includes(k);
                            return (
                                <button
                                    key={k}
                                    type="button"
                                    disabled={!peut.antecedents}
                                    onClick={() =>
                                        ante.setData('antecedents', coche ? ante.data.antecedents.filter((a) => a !== k) : [...ante.data.antecedents, k])
                                    }
                                    className={cn(
                                        'rounded-full border px-3 py-1',
                                        coche ? 'border-red-300 bg-red-50 font-semibold text-red-800' : 'text-muted-foreground',
                                        peut.antecedents && 'hover:bg-muted',
                                    )}
                                >
                                    {coche ? '✓ ' : ''}
                                    {v}
                                </button>
                            );
                        })}
                    </div>
                    <Field label="Allergies">
                        <input
                            className={cn(inputCls, ante.data.allergies && 'font-semibold text-red-700')}
                            disabled={!peut.antecedents}
                            value={ante.data.allergies}
                            onChange={(e) => ante.setData('allergies', e.target.value)}
                            placeholder="Aucune"
                        />
                    </Field>
                    <Field label="Traitements en cours">
                        <input
                            className={inputCls}
                            disabled={!peut.antecedents}
                            value={ante.data.traitements}
                            onChange={(e) => ante.setData('traitements', e.target.value)}
                            placeholder="—"
                        />
                    </Field>
                    <Field label="Autres antécédents">
                        <textarea
                            className={textareaCls}
                            disabled={!peut.antecedents}
                            value={ante.data.antecedents_autres}
                            onChange={(e) => ante.setData('antecedents_autres', e.target.value)}
                        />
                    </Field>
                    {peut.antecedents && (
                        <Button
                            variant="outline"
                            onClick={() => ante.put(`/dossiers/${dossier.id}/antecedents`, { preserveScroll: true })}
                            disabled={ante.processing || !ante.isDirty}
                        >
                            Enregistrer les antécédents
                        </Button>
                    )}
                </div>
            </Box>
            <Box titre="Diagnostic et suivi">
                <div className="flex flex-col gap-3">
                    <Field label="Diagnostic / cas du patient">
                        <textarea
                            className={textareaCls}
                            disabled={!peut.medical}
                            value={form.data.diagnostic}
                            onChange={(e) => form.setData('diagnostic', e.target.value)}
                        />
                    </Field>
                    <Field label="Notes">
                        <textarea
                            className={textareaCls}
                            disabled={!peut.medical}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                        />
                    </Field>
                    <div className="grid gap-3 md:grid-cols-3">
                        <Field label="Docteur de consultation">
                            <select
                                className={inputCls}
                                disabled={!peut.medical}
                                value={form.data.docteur_consultation_id}
                                onChange={(e) => form.setData('docteur_consultation_id', e.target.value)}
                            >
                                <option value="">—</option>
                                {docteurs.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Statut">
                            <select
                                className={inputCls}
                                disabled={!peut.medical}
                                value={form.data.statut}
                                onChange={(e) => form.setData('statut', e.target.value)}
                            >
                                <option value="en_traitement">En traitement</option>
                                <option value="en_suivi">En suivi</option>
                            </select>
                        </Field>
                        <Field label="Prochain contrôle">
                            <input
                                type="date"
                                className={inputCls}
                                disabled={!peut.medical}
                                value={form.data.prochain_controle}
                                onChange={(e) => form.setData('prochain_controle', e.target.value)}
                            />
                        </Field>
                    </div>
                    {peut.medical ? (
                        <Button
                            onClick={() =>
                                form.put(`/dossiers/${dossier.id}/medical`, {
                                    preserveScroll: true,
                                })
                            }
                            disabled={form.processing}
                        >
                            Enregistrer
                        </Button>
                    ) : (
                        <p className="text-xs text-muted-foreground">Le diagnostic est rempli par le docteur.</p>
                    )}
                </div>
            </Box>
        </div>
    );
}

/* ---------------- Labo ---------------- */
function Labo({ commandes, dossier, peut, radios }: Props) {
    // Demande écrite librement : la cheffe du labo l'accepte, la confie et fixe la date « prête le »
    const form = useForm({ demande: '', date_pose: '' });

    return (
        <div className="flex flex-col gap-4">
            {radios.length > 0 && (
                <Box titre={`Radios et mesures (${radios.length})`}>
                    <ul className="divide-y text-sm">
                        {radios.map((r) => (
                            <li key={r.id} className="flex flex-wrap items-start justify-between gap-3 py-2">
                                <div>
                                    <b>{r.type_label}</b> {r.dents && `· dents ${r.dents}`}{' '}
                                    <span className="text-muted-foreground">
                                        · {dateFr(r.created_at)} · {r.faite_par?.name}
                                    </span>
                                    {r.mesures && <div className="font-semibold text-teal-800 dark:text-teal-200">Mesures : {r.mesures}</div>}
                                    {r.notes && <div className="text-muted-foreground">{r.notes}</div>}
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {r.documents.map((d) => (
                                        <a key={d.id} href={`/documents/${d.id}`} target="_blank" rel="noreferrer" className="text-primary underline">
                                            Voir
                                        </a>
                                    ))}
                                    {r.vue_labo_le ? (
                                        <Pill className="bg-green-100 text-green-800">Vue par le labo</Pill>
                                    ) : (
                                        <Pill className="bg-amber-100 text-amber-800">Envoyée au labo</Pill>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </Box>
            )}
            <Box titre="Travaux envoyés au labo">
                {commandes.length === 0 ? (
                    <Empty>Aucun travail labo.</Empty>
                ) : (
                    <ul className="divide-y">
                        {commandes.map((c) => (
                            <li key={c.id} className="flex flex-wrap items-center gap-3 py-3 text-sm">
                                <Pill className={PRIORITE_LABO[c.priorite]?.cls}>{PRIORITE_LABO[c.priorite]?.label}</Pill>
                                <span className="min-w-48 flex-1">
                                    <b>{c.travail}</b> {c.dents ? `· ${c.dents}` : ''} {c.teinte ? `· teinte ${c.teinte}` : ''}{' '}
                                    {c.materiau ? `· ${c.materiau}` : ''}
                                    {c.technicien && <span className="text-xs text-muted-foreground"> · confié à {c.technicien.name}</span>}
                                    {c.parent_id && (
                                        <span className="text-orange-700">
                                            {' '}
                                            · refait
                                            {c.garantie ? ' (garantie)' : ''}
                                        </span>
                                    )}
                                </span>
                                <span>Pose : {c.date_pose ? dateFr(c.date_pose) : 'pas encore fixée'}</span>
                                {c.acceptee_le ? (
                                    <>
                                        {c.date_prete && <span className="font-semibold">Prêt le {dateFr(c.date_prete)}</span>}
                                        <Pill className="bg-slate-100 text-slate-700">{c.statut.replace('_', ' ')}</Pill>
                                    </>
                                ) : (
                                    <Pill className="bg-amber-100 text-amber-800">En attente de la cheffe du labo</Pill>
                                )}
                                {c.instructions && c.instructions !== c.travail && (
                                    <p className="w-full text-xs whitespace-pre-line text-muted-foreground">{c.instructions}</p>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </Box>
            {peut.labo && (
                <Box titre="Demande au labo">
                    <Field label="Écrivez la demande : travail, dents, matériau, teinte, remarques…" error={form.errors.demande}>
                        <textarea
                            className={cn(textareaCls, 'text-base')}
                            rows={4}
                            value={form.data.demande}
                            onChange={(e) => form.setData('demande', e.target.value)}
                            placeholder={'Couronne zircone 36\nTeinte A2, forme naturelle'}
                        />
                    </Field>
                    <div className="mt-3 flex flex-wrap items-end gap-3">
                        <Field label="Date de pose (sinon : prochain RDV)">
                            <input type="date" className={inputCls} value={form.data.date_pose} onChange={(e) => form.setData('date_pose', e.target.value)} />
                        </Field>
                        <Button
                            disabled={!form.data.demande.trim() || form.processing}
                            onClick={() => {
                                form.transform((d) => ({ demande: d.demande, date_pose: d.date_pose || null }));
                                form.post(`/dossiers/${dossier.id}/labo`, { preserveScroll: true, onSuccess: () => form.reset() });
                            }}
                        >
                            Envoyer au labo
                        </Button>
                    </div>
                    <p className="mt-2 text-xs text-muted-foreground">
                        La cheffe du labo reçoit la demande, la confie à une personne du labo et fixe la date où le travail sera prêt.
                    </p>
                </Box>
            )}
        </div>
    );
}

/* ---------------- Paiements ---------------- */
function Paiements({ paiements, finance, dossier, peut }: Props & { finance: NonNullable<Props['finance']> }) {
    const form = useForm({
        montant: '',
        methode: 'especes',
        date: new Date().toISOString().slice(0, 10),
        facture_a: '',
        notes: '',
    });
    // Reçu créé automatiquement avec les données du paiement : on l'ouvre pour l'imprimer
    const [nouveauRecu, setNouveauRecu] = useState<{
        id: number;
        numero: string;
    } | null>(null);
    useEffect(
        () =>
            router.on('flash', (ev) => {
                const r = (ev as CustomEvent).detail?.flash?.recu as { id: number; numero: string } | undefined;
                if (r) {
                    setNouveauRecu(r);
                    window.open(`/paiements/${r.id}/recu`, '_blank');
                }
            }),
        [],
    );
    return (
        <div className="flex flex-col gap-4">
            <div className="grid grid-cols-3 gap-3">
                {[
                    ['Prix convenu', finance.convenu, ''],
                    ['Reçu', finance.recu, ''],
                    ['Reste', finance.reste, finance.reste > 0 ? 'text-red-600' : 'text-green-700'],
                ].map(([l, v, c]) => (
                    <Box key={l as string} className="text-center">
                        <div className={cn('text-xl font-bold md:text-2xl', c as string)}>{dh(v as number)}</div>
                        <div className="text-xs text-muted-foreground">{l as string}</div>
                    </Box>
                ))}
            </div>
            {nouveauRecu && (
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-green-300 bg-green-50 px-4 py-3 text-green-900 dark:bg-green-950/30 dark:text-green-100">
                    <span>
                        Reçu n° <b>{nouveauRecu.numero}</b> rempli automatiquement.
                    </span>
                    <Button asChild>
                        <a href={`/paiements/${nouveauRecu.id}/recu`} target="_blank" rel="noreferrer">
                            <Printer /> Imprimer le reçu
                        </a>
                    </Button>
                </div>
            )}
            {peut.finance && (
                <Box titre="Nouveau paiement">
                    <div className="grid gap-3 md:grid-cols-4">
                        <Field label="Montant (DH)" error={form.errors.montant}>
                            <input type="number" className={inputCls} value={form.data.montant} onChange={(e) => form.setData('montant', e.target.value)} />
                        </Field>
                        <Field label="Méthode">
                            <select className={inputCls} value={form.data.methode} onChange={(e) => form.setData('methode', e.target.value)}>
                                {Object.entries(METHODES).map(([k, v]) => (
                                    <option key={k} value={k}>
                                        {v}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Date">
                            <input type="date" className={inputCls} value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                        </Field>
                        <Field label="Facturé à (si autre personne)">
                            <input className={inputCls} value={form.data.facture_a} onChange={(e) => form.setData('facture_a', e.target.value)} />
                        </Field>
                        <Field label="Objet (écrit sur le reçu)" className="md:col-span-4">
                            <input
                                className={inputCls}
                                placeholder="Soins dentaires, avance couronne 36…"
                                value={form.data.notes}
                                onChange={(e) => form.setData('notes', e.target.value)}
                            />
                        </Field>
                    </div>
                    <Button
                        className="mt-3"
                        disabled={!form.data.montant || form.processing}
                        onClick={() =>
                            form.post(`/dossiers/${dossier.id}/paiements`, {
                                preserveScroll: true,
                                onSuccess: () => form.reset('montant', 'facture_a', 'notes'),
                            })
                        }
                    >
                        Enregistrer le paiement
                    </Button>
                </Box>
            )}
            <Box titre="Reçus">
                {paiements.length === 0 ? (
                    <Empty>Aucun paiement.</Empty>
                ) : (
                    <ul className="divide-y text-sm">
                        {paiements.map((pm) => (
                            <li key={pm.id} className="flex flex-wrap items-center gap-3 py-2">
                                <b className="w-20">N° {pm.numero_recu}</b>
                                <span className="w-24">{dateFr(pm.date)}</span>
                                <span className="flex-1">
                                    {dh(pm.montant)} · {METHODES[pm.methode]} · reste {dh(pm.reste_apres)}
                                </span>
                                <span className="text-muted-foreground">{pm.encaisse_par?.name}</span>
                                <a
                                    className="flex items-center gap-1 text-primary underline"
                                    href={`/paiements/${pm.id}/recu`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Printer className="size-4" /> Reçu
                                </a>
                            </li>
                        ))}
                    </ul>
                )}
            </Box>
        </div>
    );
}

/* ---------------- Documents ---------------- */
function AjoutDocuments({
    patientId,
    typesDocuments,
    typeDefaut = 'cin',
    onDone,
    typeFixe = false,
}: {
    patientId: number;
    typesDocuments: Record<string, string>;
    typeDefaut?: string;
    onDone?: () => void;
    typeFixe?: boolean;
}) {
    const form = useForm<{ type: string; fichiers: File[] }>({
        type: typeDefaut,
        fichiers: [],
    });
    return (
        <div className="flex flex-col gap-3">
            <div className={cn('flex flex-wrap gap-2', typeFixe && 'hidden')}>
                {Object.entries(typesDocuments).map(([k, v]) => (
                    <button
                        key={k}
                        type="button"
                        onClick={() => form.setData('type', k)}
                        className={cn(
                            'rounded-full border px-3 py-1 text-sm',
                            form.data.type === k ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted',
                        )}
                    >
                        {v}
                    </button>
                ))}
            </div>
            <label className="flex cursor-pointer flex-col items-center gap-1 rounded-xl border-2 border-dashed border-teal-300 bg-teal-50/40 p-5 text-center text-sm hover:bg-teal-50 dark:bg-teal-950/20">
                <Upload className="size-6 text-teal-700" />
                <span className="font-semibold">
                    {form.data.fichiers.length ? `${form.data.fichiers.length} fichier(s) choisi(s)` : 'Prendre une photo ou choisir des fichiers'}
                </span>
                <span className="text-muted-foreground">{form.data.fichiers.map((f) => f.name).join(', ') || 'Images ou PDF, 10 Mo max par fichier'}</span>
                <input
                    type="file"
                    multiple
                    accept="image/*,application/pdf"
                    className="hidden"
                    onChange={(e) => form.setData('fichiers', Array.from(e.target.files ?? []))}
                />
            </label>
            {(form.errors.fichiers || form.errors['fichiers.0' as 'fichiers']) && (
                <p className="text-sm text-red-600">{form.errors.fichiers || form.errors['fichiers.0' as 'fichiers']}</p>
            )}
            <Button
                disabled={form.data.fichiers.length === 0 || form.processing}
                onClick={() =>
                    form.post(`/patients/${patientId}/documents`, {
                        forceFormData: true,
                        preserveScroll: true,
                        onSuccess: () => {
                            form.reset('fichiers');
                            onDone?.();
                        },
                    })
                }
            >
                <Upload /> Enregistrer dans le dossier
            </Button>
        </div>
    );
}

function Documents({ documents, typesDocuments, dossier, peut }: Props) {
    const [filtre, setFiltre] = useState<string>('');
    const estImage = (d: Doc) => /\.(jpe?g|png|webp|heic)$/i.test(d.nom_original ?? '');
    const liste = filtre ? documents.filter((d) => d.type === filtre) : documents;
    const presents = Array.from(new Set(documents.map((d) => d.type)));
    return (
        <div className="grid gap-4 lg:grid-cols-[380px_1fr]">
            {peut.documents && (
                <Box titre="Ajouter CIN / papiers">
                    <AjoutDocuments patientId={dossier.patient.id} typesDocuments={typesDocuments} />
                </Box>
            )}
            <Box titre={`Documents du patient (${documents.length})`}>
                {presents.length > 1 && (
                    <div className="mb-3 flex flex-wrap gap-2 text-sm">
                        <button onClick={() => setFiltre('')} className={cn('rounded-full border px-3 py-1', !filtre && 'bg-muted font-semibold')}>
                            Tous
                        </button>
                        {presents.map((t) => (
                            <button
                                key={t}
                                onClick={() => setFiltre(t)}
                                className={cn('rounded-full border px-3 py-1', filtre === t && 'bg-muted font-semibold')}
                            >
                                {typesDocuments[t] ?? t}
                            </button>
                        ))}
                    </div>
                )}
                {liste.length === 0 ? (
                    <Empty>Aucun document (CIN, ordonnances, radios…).</Empty>
                ) : (
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                        {liste.map((d) => (
                            <div key={d.id} className="overflow-hidden rounded-lg border bg-card text-xs hover:shadow">
                                <a href={`/documents/${d.id}`} target="_blank" rel="noreferrer">
                                    {estImage(d) ? (
                                        <img src={`/documents/${d.id}`} alt={d.nom_original ?? ''} className="h-32 w-full object-cover" />
                                    ) : (
                                        <div className="flex h-32 items-center justify-center bg-muted font-semibold">PDF</div>
                                    )}
                                </a>
                                <div className="flex items-start justify-between gap-1 p-2">
                                    <div>
                                        <b>{typesDocuments[d.type] ?? d.type}</b>
                                        <div className="text-muted-foreground">
                                            {dateFr(d.created_at)} · {d.ajoute_par?.name}
                                        </div>
                                    </div>
                                    {peut.documents && (
                                        <button
                                            title="Supprimer"
                                            className="text-muted-foreground hover:text-red-600"
                                            onClick={() => confirm('Supprimer ce document ?') && router.delete(`/documents/${d.id}`, { preserveScroll: true })}
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Box>
        </div>
    );
}

/** Les pages scannées de l'ancien dossier papier, en grand, pour que le docteur les lise. */
function DossierPapier({ documents, typesDocuments, dossier, peut }: Props) {
    const scans = documents.filter((d) => d.type === 'ancien_dossier');
    const estImage = (d: Doc) => /\.(jpe?g|png|webp|heic|gif|bmp)$/i.test(d.nom_original ?? '');
    return (
        <div className="grid gap-4 lg:grid-cols-[320px_1fr]">
            {peut.documents && (
                <Box titre="Ajouter les pages scannées">
                    <p className="mb-3 text-sm text-muted-foreground">Scannez ou photographiez chaque page du dossier papier (plusieurs fichiers possibles).</p>
                    <AjoutDocuments patientId={dossier.patient.id} typesDocuments={typesDocuments} typeDefaut="ancien_dossier" typeFixe />
                </Box>
            )}
            <Box titre={`Ancien dossier papier (${scans.length} page(s))`}>
                {scans.length === 0 ? (
                    <Empty>Pas encore de scan du dossier papier.</Empty>
                ) : (
                    <div className="flex flex-col gap-4">
                        {scans.map((d, i) => (
                            <div key={d.id} className="overflow-hidden rounded-lg border bg-card">
                                <div className="flex items-center justify-between gap-2 border-b px-3 py-2 text-xs">
                                    <span>
                                        <b>Page {i + 1}</b> · {dateFr(d.created_at)} · {d.ajoute_par?.name}
                                    </span>
                                    <span className="flex items-center gap-3">
                                        <a href={`/documents/${d.id}`} target="_blank" rel="noreferrer" className="font-semibold text-primary underline">
                                            Ouvrir en grand
                                        </a>
                                        {peut.documents && (
                                            <button
                                                title="Supprimer"
                                                className="text-muted-foreground hover:text-red-600"
                                                onClick={() =>
                                                    confirm('Supprimer cette page ?') && router.delete(`/documents/${d.id}`, { preserveScroll: true })
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        )}
                                    </span>
                                </div>
                                {estImage(d) ? (
                                    <a href={`/documents/${d.id}`} target="_blank" rel="noreferrer">
                                        <img src={`/documents/${d.id}`} alt={`Page ${i + 1}`} className="w-full bg-white object-contain" />
                                    </a>
                                ) : (
                                    <iframe src={`/documents/${d.id}`} title={`Page ${i + 1}`} className="h-[80vh] w-full bg-white" />
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </Box>
        </div>
    );
}

/* ---------------- Identité ---------------- */
function Identite({ dossier, peut, antecedentsListe }: Props) {
    const p = dossier.patient;
    const s = (k: string) => (p[k] as string | null) ?? '';
    const form = useForm({
        nom: s('nom'),
        prenom: s('prenom'),
        date_naissance: s('date_naissance').slice(0, 10),
        sexe: s('sexe'),
        cin: s('cin'),
        telephone: s('telephone'),
        email: s('email'),
        ville: s('ville'),
        adresse: s('adresse'),
        profession: s('profession'),
        antecedents: (p.antecedents as string[] | null) ?? [],
        antecedents_autres: s('antecedents_autres'),
        allergies: s('allergies'),
        traitements: s('traitements'),
    });
    const champ = (k: Exclude<keyof typeof form.data, 'antecedents'>, label: string, type = 'text') => (
        <Field label={label} error={form.errors[k]}>
            <input type={type} disabled={!peut.identite} className={inputCls} value={form.data[k]} onChange={(e) => form.setData(k, e.target.value)} />
        </Field>
    );
    const anciens = (p.anciens_telephones as string[] | null) ?? [];
    return (
        <Box titre="Identité du patient">
            <div className="grid gap-3 md:grid-cols-3">
                {champ('nom', 'Nom')}
                {champ('prenom', 'Prénom')}
                {champ('date_naissance', 'Date de naissance', 'date')}
                {champ('cin', 'N° CIN')}
                {champ('telephone', 'Téléphone')}
                {champ('email', 'E-mail')}
                {champ('ville', 'Ville')}
                {champ('adresse', 'Adresse')}
                {champ('profession', 'Profession')}
                {champ('allergies', 'Allergies')}
                {champ('traitements', 'Traitements en cours')}
                {champ('antecedents_autres', 'Autres antécédents')}
            </div>
            <div className="mt-3 flex flex-wrap gap-2 text-sm">
                {Object.entries(antecedentsListe).map(([k, v]) => (
                    <label key={k} className="flex items-center gap-1 rounded-full border px-3 py-1">
                        <input
                            type="checkbox"
                            disabled={!peut.identite}
                            checked={form.data.antecedents.includes(k)}
                            onChange={(e) =>
                                form.setData('antecedents', e.target.checked ? [...form.data.antecedents, k] : form.data.antecedents.filter((a) => a !== k))
                            }
                        />
                        {v}
                    </label>
                ))}
            </div>
            {anciens.length > 0 && <p className="mt-2 text-xs text-muted-foreground">Anciens téléphones : {anciens.join(', ')}</p>}
            {peut.identite && (
                <Button className="mt-3" onClick={() => form.put(`/patients/${p.id}`, { preserveScroll: true })} disabled={form.processing}>
                    Enregistrer
                </Button>
            )}
            {peut.identite && <Fusion dossier={dossier} />}
        </Box>
    );
}

/** Doublon : verser un autre dossier du même patient dans celui-ci. */
function Fusion({ dossier }: { dossier: Props['dossier'] }) {
    const [ouvert, setOuvert] = useState(false);
    const [autre, setAutre] = useState<Patient | null>(null);
    const autreDossier = (autre as (Patient & { dossier?: { id: number; numero: string } }) | null)?.dossier;
    if (!ouvert) {
        return (
            <button className="mt-4 block text-xs text-muted-foreground underline" onClick={() => setOuvert(true)}>
                Ce patient a un deuxième dossier (doublon) ? Fusionner les dossiers
            </button>
        );
    }
    return (
        <div className="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm dark:bg-amber-950/30">
            <b>Fusionner un doublon dans ce dossier (n° {dossier.numero})</b>
            <p className="mb-2 text-xs text-muted-foreground">
                Séances, actes, rendez-vous, paiements, labo et documents de l'autre dossier seront déplacés ici. L'autre dossier sera fermé.
            </p>
            <PatientSearch value={autre} onSelect={setAutre} />
            <div className="mt-2 flex gap-2">
                <Button
                    size="sm"
                    disabled={!autreDossier || autreDossier.id === dossier.id}
                    onClick={() =>
                        autreDossier &&
                        confirm(`Fusionner le dossier n° ${autreDossier.numero} dans le dossier n° ${dossier.numero} ? Cette action est définitive.`) &&
                        router.post(`/dossiers/${dossier.id}/fusionner`, {
                            autre_dossier_id: autreDossier.id,
                        })
                    }
                >
                    Fusionner
                </Button>
                <Button size="sm" variant="outline" onClick={() => setOuvert(false)}>
                    Annuler
                </Button>
            </div>
        </div>
    );
}

function RendezVous({
    rdvs,
    patient,
    tous,
    miens,
    aFixer,
    dossierId,
    docteurs,
    actes,
}: {
    rdvs: Rdv[];
    patient: string;
    tous: boolean;
    miens: number | null;
    aFixer: AFixer[];
    dossierId: number;
    docteurs: User[];
    actes: Acte[];
}) {
    const [modif, setModif] = useState<Rdv | null>(null);
    const [prochain, setProchain] = useState(false);
    const peutPrevoir = tous || miens !== null;
    const ouverts = actes.filter((a) => a.statut === 'a_faire' || a.statut === 'en_cours');
    const modifiable = (r: Rdv) => ['planifie', 'arrive'].includes(r.statut) && (tous || (miens !== null && r.docteur_id === miens));
    return (
        <Box>
            {peutPrevoir && (
                <div className="mb-3 flex justify-end">
                    <Button size="sm" onClick={() => setProchain(true)}>
                        <CalendarPlus /> Prochain rendez-vous
                    </Button>
                </div>
            )}
            {aFixer.length > 0 && (
                <div className="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <b>Date à fixer par la réception :</b>
                    <ul className="mt-1 list-disc pl-5">
                        {aFixer.map((a) => (
                            <li key={a.id}>
                                {a.mission} · {a.docteur?.name}
                                {a.notes && <span className="text-amber-800"> ({a.notes})</span>}
                                <span className="text-xs text-amber-700"> · demandé par {a.demande_par?.name}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            {rdvs.length === 0 && <Empty>Aucun rendez-vous.</Empty>}
            <ul className="divide-y text-sm">
                {rdvs.map((r) => (
                    <li key={r.id} className="flex flex-wrap items-center gap-3 py-2">
                        <b className="w-36">{dateHeure(r.debut)}</b>
                        <span className="flex-1">
                            {r.mission} · {r.docteur?.name}
                        </span>
                        <Pill className={STATUT_RDV[r.statut]?.cls}>{STATUT_RDV[r.statut]?.label}</Pill>
                        {modifiable(r) && (
                            <Button variant="outline" size="sm" onClick={() => setModif(r)}>
                                Modifier / supprimer
                            </Button>
                        )}
                    </li>
                ))}
            </ul>
            {prochain && <ProchainRdvModal dossierId={dossierId} docteurs={docteurs} actes={ouverts} onClose={() => setProchain(false)} />}
            {modif && <ModifierRdv rdv={{ ...modif, patient: { nom_complet: patient } }} onClose={() => setModif(null)} />}
            {tous && (
                <Link href="/agenda" className="mt-2 inline-block text-sm text-primary underline">
                    Prendre un rendez-vous
                </Link>
            )}
        </Box>
    );
}

DossierShow.layout = {
    breadcrumbs: [
        { title: 'Tous les dossiers', href: '/patients' },
        { title: 'Dossier', href: '#' },
    ],
};

/* ---------------- Carte patient (photographe) ---------------- */
function CartePatient({ carte, patient, numero }: { carte: { photo: number | null; lien: string }; patient: Patient; numero: string }) {
    const [envoi, setEnvoi] = useState(false);
    const [erreur, setErreur] = useState<string | null>(null);
    const envoyer = async (f: File) => {
        setEnvoi(true);
        setErreur(null);
        const { fichier, ok } = await detourer(f);
        router.post(
            `/patients/${patient.id}/documents`,
            { type: 'photo_carte', fichiers: [fichier] },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => !ok && setErreur("Le fond n'a pas pu être enlevé (photo gardée telle quelle)."),
                onError: (e) => setErreur(Object.values(e)[0] ?? 'Envoi impossible.'),
                onFinish: () => setEnvoi(false),
            },
        );
    };
    const photo = (input: HTMLInputElement) => {
        const f = input.files?.[0];
        input.value = ''; // permet de rechoisir une photo
        if (f) envoyer(f);
    };
    // Photo déjà enregistrée (avec fond) : on enlève le fond sans reprendre la photo
    const enleverFond = async () => {
        if (!carte.photo) return;
        const b = await (await fetch(`/documents/${carte.photo}`)).blob();
        envoyer(new File([b], 'photo.jpg', { type: b.type || 'image/jpeg' }));
    };
    const actif = (usePage().props as { espacePatient?: boolean }).espacePatient !== false;
    return (
        <div className="grid gap-4 lg:grid-cols-[1fr_360px]">
            <Box titre="Aperçu de la carte">
                {/* Même disposition que la carte imprimée : photo à gauche, QR à droite */}
                <div
                    className="relative mx-auto aspect-[85.6/54] w-full max-w-[560px] overflow-hidden rounded-xl shadow-lg"
                    style={{ background: "url('/images/carte-patient-fond.jpg?v=3') center / 100% 100% no-repeat" }}
                >
                    <div className="absolute top-[4%] left-[3%] h-[82%] w-[45%]">
                        {carte.photo ? (
                            <img
                                src={`/documents/${carte.photo}`}
                                alt="Photo"
                                className="h-full w-full object-cover object-top"
                                style={{ maskImage: 'linear-gradient(to bottom, #000 80%, transparent 100%)' }}
                            />
                        ) : (
                            <div className="absolute inset-x-[15%] inset-y-[20%] flex items-center justify-center rounded-md border border-dashed border-slate-400 text-center text-xs text-muted-foreground">
                                Pas encore de photo
                            </div>
                        )}
                    </div>
                    <div className="absolute top-[35%] left-[59.3%] flex h-[45.6%] w-[27.4%] items-center justify-center border border-dashed border-[#048ea7]/40 text-[10px] font-semibold text-[#048ea7]">
                        QR code
                    </div>
                    <div className="absolute bottom-[4.6%] left-[14.2%] text-base leading-none font-extrabold text-[#048ea7] md:text-2xl">{numero}</div>
                    <div className="absolute bottom-[3.6%] left-[47%] w-[44%] truncate text-center text-sm font-extrabold text-[#048ea7] uppercase md:text-lg">
                        {patient.nom_complet}
                    </div>
                </div>
            </Box>
            <Box titre="Carte patient">
                <div className="flex flex-col gap-3 text-sm">
                    <label
                        className={cn(
                            'flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed border-primary/50 p-4 font-semibold text-primary hover:bg-primary/5',
                            envoi && 'opacity-60',
                        )}
                    >
                        <Camera className="size-5" />{' '}
                        {envoi ? 'Détourage et envoi…' : carte.photo ? 'Changer la photo du patient' : 'Prendre la photo du patient'}
                        <input type="file" accept="image/*" className="hidden" onChange={(e) => photo(e.target)} />
                    </label>
                    {carte.photo && (
                        <Button variant="outline" disabled={envoi} onClick={enleverFond}>
                            Enlever le fond de la photo
                        </Button>
                    )}
                    {erreur && <p className="text-sm text-red-600">{erreur}</p>}
                    {!actif ? (
                        <p className="rounded-lg bg-amber-50 p-3 text-amber-800">
                            Mode local : la carte avec QR code et « Mon espace » patient seront activés après l’accord de la CNDP. Vous pouvez déjà prendre la
                            photo du patient.
                        </p>
                    ) : (
                        <>
                            <Button asChild>
                                <a href={`/patients/${patient.id}/carte?auto=1`} target="_blank" rel="noreferrer">
                                    <Printer /> Imprimer la carte
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a href={carte.lien} target="_blank" rel="noreferrer">
                                    Voir la page « Mon dossier » du patient
                                </a>
                            </Button>
                            <p className="text-muted-foreground">
                                Le QR code ouvre une page <b>toujours à jour</b> : prochaine séance, traitement, labo, séances et paiements (sans les radios).
                                Le patient tape les <b>4 derniers chiffres de son téléphone</b> pour l’ouvrir.
                            </p>
                            <button
                                className="self-start text-xs text-red-600 underline"
                                onClick={() =>
                                    confirm('Carte perdue ou volée ? Un nouveau QR code sera créé et l’ancienne carte ne marchera plus.') &&
                                    router.post(`/patients/${patient.id}/carte/nouveau-lien`, {}, { preserveScroll: true })
                                }
                            >
                                Carte perdue : créer un nouveau QR code
                            </button>
                        </>
                    )}
                </div>
            </Box>
        </div>
    );
}
