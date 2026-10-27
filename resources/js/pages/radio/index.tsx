import { Head, Link, usePoll } from '@inertiajs/react';
import { ScanLine } from 'lucide-react';
import { useState } from 'react';
import { PatientSearch } from '@/components/cabinet/patient-search';
import { RadioForm } from '@/components/cabinet/radio-form';
import { Box, Empty, Modal, Page, PageTitle, Pill } from '@/components/cabinet/ui';
import { Button } from '@/components/ui/button';
import type { Patient } from '@/lib/cabinet';
import { heure, STATUT_RDV } from '@/lib/cabinet';

type PatientDossier = Patient & { dossier?: { id: number; numero: string } | null };
type Rdv = { id: number; debut: string; mission: string; statut: string; patient: PatientDossier; docteur: { name: string } | null };
type Radio = {
    id: number;
    type: string;
    dents: string | null;
    mesures: string | null;
    created_at: string;
    vue_labo_le: string | null;
    dossier: { id: number; numero: string; patient: { nom_complet: string } };
    documents: { id: number; nom_original: string | null }[];
};

type Demande = {
    id: number;
    type: string;
    notes: string | null;
    created_at: string;
    demande_par: { name: string } | null;
    dossier: { id: number; numero: string; patient: { nom_complet: string; telephone: string | null } };
};

/** Regroupe les demandes par patient (une ligne par patient). */
function parPatient<T extends { dossier: { id: number } }>(liste: T[]): T[][] {
    const g = new Map<number, T[]>();
    liste.forEach((d) => g.set(d.dossier.id, [...(g.get(d.dossier.id) ?? []), d]));
    return [...g.values()];
}

export default function Radiologie({
    patients,
    radios,
    types,
    demandes = [],
}: {
    patients: Rdv[];
    radios: Radio[];
    types: Record<string, string>;
    demandes?: Demande[];
}) {
    usePoll(10000, { only: ['demandes', 'patients', 'radios'] });
    const [pour, setPour] = useState<PatientDossier | null>(null);
    const [demande, setDemande] = useState<Demande | null>(null);
    const [recherche, setRecherche] = useState<PatientDossier | null>(null);

    return (
        <Page>
            <Head title="Radiologie" />
            <PageTitle titre="Radiologie" sousTitre="Chaque radio est ajoutée au dossier du patient et envoyée automatiquement au labo, avec les mesures." />

            <Box titre={`Radios demandées (${demandes.length})`}>
                {demandes.length === 0 ? (
                    <Empty>Aucune demande en attente.</Empty>
                ) : (
                    <ul className="divide-y">
                        {/* Une seule ligne par patient, avec toutes ses radios demandées */}
                        {parPatient(demandes).map((groupe) => (
                            <li key={groupe[0].dossier.id} className="flex flex-wrap items-start gap-3 py-3">
                                <span className="w-14 pt-1 font-bold">{heure(groupe[0].created_at)}</span>
                                <div className="min-w-48 flex-1">
                                    <b>{groupe[0].dossier.patient.nom_complet}</b>{' '}
                                    <span className="text-sm text-muted-foreground">· dossier {groupe[0].dossier.numero}</span>
                                    {groupe.map((d) => (
                                        <div key={d.id} className="text-xs text-muted-foreground">
                                            {types[d.type] ?? d.type} : demandée par {d.demande_par?.name ?? '—'} {d.notes && `· ${d.notes}`}
                                        </div>
                                    ))}
                                </div>
                                <div className="flex flex-col items-end gap-2">
                                    {groupe.map((d) => (
                                        <Button key={d.id} size="sm" onClick={() => setDemande(d)}>
                                            <ScanLine /> Faire : {types[d.type] ?? d.type}
                                        </Button>
                                    ))}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Box>

            <Box titre="Autre patient (sans rendez-vous aujourd'hui)">
                <div className="flex flex-wrap items-center gap-2">
                    <div className="min-w-72 flex-1">
                        <PatientSearch value={recherche} onSelect={(p) => setRecherche(p as PatientDossier | null)} />
                    </div>
                    <Button disabled={!recherche?.dossier} onClick={() => setPour(recherche)}>
                        <ScanLine /> Nouvelle radio
                    </Button>
                </div>
            </Box>

            <div className="grid gap-4 lg:grid-cols-2">
                <Box titre={`Patients du jour (${patients.length})`}>
                    {patients.length === 0 ? (
                        <Empty>Aucun patient prévu aujourd'hui.</Empty>
                    ) : (
                        <ul className="divide-y">
                            {patients.map((r) => (
                                <li key={r.id} className="flex flex-wrap items-center gap-3 py-3">
                                    <span className="w-12 font-bold">{heure(r.debut)}</span>
                                    <div className="min-w-40 flex-1">
                                        <b>{r.patient.nom_complet}</b>
                                        <div className="text-xs text-muted-foreground">
                                            {r.mission} · {r.docteur?.name ?? '—'}
                                        </div>
                                    </div>
                                    <Pill className={STATUT_RDV[r.statut]?.cls}>{STATUT_RDV[r.statut]?.label}</Pill>
                                    <Button size="sm" onClick={() => setPour(r.patient)}>
                                        <ScanLine /> Radio
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Box>

                <Box titre={`Radios faites aujourd'hui (${radios.length})`}>
                    {radios.length === 0 ? (
                        <Empty>Aucune radio pour l'instant.</Empty>
                    ) : (
                        <ul className="divide-y text-sm">
                            {radios.map((r) => (
                                <li key={r.id} className="py-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <Link href={`/dossiers/${r.dossier.id}`} className="font-semibold text-primary underline">
                                            {r.dossier.patient.nom_complet}
                                        </Link>
                                        {r.vue_labo_le ? (
                                            <Pill className="bg-green-100 text-green-800">Vue par le labo</Pill>
                                        ) : (
                                            <Pill className="bg-amber-100 text-amber-800">Envoyée au labo</Pill>
                                        )}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {heure(r.created_at)} · {types[r.type] ?? r.type} {r.dents && `· dents ${r.dents}`} · {r.documents.length} fichier(s)
                                    </div>
                                    {r.mesures && <div>Mesures : {r.mesures}</div>}
                                </li>
                            ))}
                        </ul>
                    )}
                </Box>
            </div>

            <Modal
                open={!!demande}
                onClose={() => setDemande(null)}
                titre={`${demande ? types[demande.type] : ''} · ${demande?.dossier.patient.nom_complet ?? ''}`}
                large
            >
                {demande && (
                    <RadioForm dossierId={demande.dossier.id} types={types} typeDefaut={demande.type} demandeId={demande.id} onDone={() => setDemande(null)} />
                )}
            </Modal>
            <Modal open={!!pour?.dossier} onClose={() => setPour(null)} titre={`Nouvelle radio · ${pour?.nom_complet ?? ''}`} large>
                {pour?.dossier && <RadioForm dossierId={pour.dossier.id} types={types} onDone={() => setPour(null)} />}
            </Modal>
        </Page>
    );
}

Radiologie.layout = { breadcrumbs: [{ title: 'Radiologie', href: '/radiologie' }] };
