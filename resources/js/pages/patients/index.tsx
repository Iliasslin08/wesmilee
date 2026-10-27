import { Head, Link, router, useForm } from '@inertiajs/react';
import { Search, UserPlus, ArchiveRestore, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Box, Empty, Field, inputCls, Modal, Page, PageTitle, Pill } from '@/components/cabinet/ui';
import { FicheACompleter } from '@/components/cabinet/fiche-a-completer';
import { Button } from '@/components/ui/button';
import type { Patient } from '@/lib/cabinet';
import { dateFr, heure } from '@/lib/cabinet';
import { cn } from '@/lib/utils';

type Ligne = Patient & {
    derniere_visite: string | null;
    dossier: { id: number; numero: string; statut: string; ancien_numero_papier: string | null; docteur_consultation: { name: string } | null } | null;
    rendez_vous: { debut: string; mission: string; docteur: { name: string } | null }[];
};
type Pagination = { data: Ligne[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
type Filtres = { q: string; docteur?: string; statut?: string; tri?: string };

export default function Dossiers({
    patients,
    filtres,
    docteurs,
    peutCreer,
    peutSupprimer = false,
    corbeille = false,
}: {
    patients: Pagination;
    filtres: Filtres;
    docteurs: { id: number; name: string }[];
    peutCreer: boolean;
    peutSupprimer?: boolean;
    corbeille?: boolean;
}) {
    const [recherche, setRecherche] = useState(filtres.q ?? '');
    const [nouveau, setNouveau] = useState(false);
    const filtrer = (f: Partial<Filtres>) =>
        router.get('/patients', { ...filtres, q: recherche, ...(corbeille ? { corbeille: 1 } : {}), ...f }, { preserveState: true, preserveScroll: true });
    const select = cn(inputCls, 'w-auto');

    return (
        <Page>
            <Head title="Tous les dossiers" />
            <PageTitle
                titre={corbeille ? 'Corbeille : dossiers supprimés' : 'Tous les dossiers'}
                sousTitre={
                    corbeille
                        ? `${patients.total} dossier(s) supprimé(s) · cliquez sur « Restaurer » pour les remettre`
                        : `${patients.total} dossier(s) · un dossier unique à vie, partagé entre la réception, les docteurs, le labo et la finance`
                }
                actions={
                    <>
                        {peutSupprimer && (
                            <Button variant="outline" onClick={() => router.get('/patients', corbeille ? {} : { corbeille: 1 })}>
                                <Trash2 /> {corbeille ? 'Retour aux dossiers' : 'Corbeille'}
                            </Button>
                        )}
                        {peutCreer && !corbeille && (
                            <Button onClick={() => setNouveau(true)}>
                                <UserPlus /> Nouveau patient
                            </Button>
                        )}
                    </>
                }
            />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    filtrer({ q: recherche });
                }}
                className="flex flex-wrap gap-2"
            >
                <div className="relative min-w-64 flex-1">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        className={cn(inputCls, 'pl-9')}
                        placeholder="Nom, téléphone, CIN, n° de dossier ou ancien n° papier…"
                        value={recherche}
                        onChange={(e) => setRecherche(e.target.value)}
                        autoFocus
                    />
                </div>
                <select className={select} value={filtres.docteur ?? ''} onChange={(e) => filtrer({ docteur: e.target.value || undefined })}>
                    <option value="">Tous les docteurs</option>
                    {docteurs.map((d) => (
                        <option key={d.id} value={d.id}>
                            {d.name}
                        </option>
                    ))}
                </select>
                <select className={select} value={filtres.statut ?? ''} onChange={(e) => filtrer({ statut: e.target.value || undefined })}>
                    <option value="">Tous les statuts</option>
                    <option value="en_traitement">En traitement</option>
                    <option value="en_suivi">En suivi</option>
                </select>
                <select className={select} value={filtres.tri ?? ''} onChange={(e) => filtrer({ tri: e.target.value || undefined })}>
                    <option value="">Trier par nom</option>
                    <option value="recent">Dernière visite</option>
                </select>
                <Button type="submit">Rechercher</Button>
            </form>
            <Box>
                {patients.data.length === 0 ? (
                    <Empty>Aucun dossier trouvé.</Empty>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[820px] text-sm">
                            <thead className="bg-muted/60 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-3 py-2">N°</th>
                                    <th className="px-3">Patient</th>
                                    <th className="px-3">Téléphone</th>
                                    <th className="px-3">Docteur (consultation)</th>
                                    <th className="px-3">Statut</th>
                                    <th className="px-3">Dernière visite</th>
                                    <th className="px-3">Prochain rendez-vous</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {patients.data.map((p) => {
                                    const prochain = p.rendez_vous?.[0];
                                    return (
                                        <tr
                                            key={p.id}
                                            className="cursor-pointer hover:bg-muted/40"
                                            onClick={() => p.dossier && router.visit(`/dossiers/${p.dossier.id}`)}
                                        >
                                            <td className="px-3 py-2.5 font-bold text-primary">
                                                <Link href={p.dossier ? `/dossiers/${p.dossier.id}` : '#'}>{p.dossier?.numero}</Link>
                                                {p.dossier?.ancien_numero_papier && (
                                                    <div className="text-xs font-normal text-muted-foreground">papier {p.dossier.ancien_numero_papier}</div>
                                                )}
                                            </td>
                                            <td className="px-3 font-semibold">
                                                {p.nom_complet}
                                                {p.allergies && <Pill className="ml-2 bg-red-100 text-red-700">Allergie</Pill>}
                                                {p.vient_de_loin && <Pill className="ml-2 bg-orange-100 text-orange-700">{p.ville}</Pill>}
                                                {p.fiche_a_completer && (
                                                    <span className="ml-2">
                                                        <FicheACompleter patientId={p.id} />
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 whitespace-nowrap">{p.telephone}</td>
                                            <td className="px-3">{p.dossier?.docteur_consultation?.name ?? '—'}</td>
                                            <td className="px-3">
                                                <Pill
                                                    className={
                                                        p.dossier?.statut === 'en_traitement' ? 'bg-teal-100 text-teal-800' : 'bg-slate-100 text-slate-700'
                                                    }
                                                >
                                                    {p.dossier?.statut === 'en_traitement' ? 'En traitement' : 'En suivi'}
                                                </Pill>
                                            </td>
                                            <td className="px-3 whitespace-nowrap">{p.derniere_visite ? dateFr(p.derniere_visite) : '—'}</td>
                                            <td className="px-3">
                                                {prochain ? (
                                                    <>
                                                        <b>
                                                            {dateFr(prochain.debut)} {heure(prochain.debut)}
                                                        </b>
                                                        <div className="text-xs text-muted-foreground">
                                                            {prochain.mission} · {prochain.docteur?.name}
                                                        </div>
                                                    </>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                                {corbeille && (
                                                    <Button
                                                        size="sm"
                                                        className="mt-1"
                                                        onClick={() => router.post(`/patients/${p.id}/restaurer`, {}, { preserveScroll: true })}
                                                    >
                                                        <ArchiveRestore /> Restaurer
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
                {patients.links.length > 3 && (
                    <div className="mt-3 flex flex-wrap gap-1">
                        {patients.links.map((l, i) => (
                            <Button key={i} size="sm" variant={l.active ? 'default' : 'outline'} disabled={!l.url} onClick={() => l.url && router.get(l.url)}>
                                <span dangerouslySetInnerHTML={{ __html: l.label }} />
                            </Button>
                        ))}
                    </div>
                )}
            </Box>
            <NouveauPatient open={nouveau} onClose={() => setNouveau(false)} />
        </Page>
    );
}

function NouveauPatient({ open, onClose }: { open: boolean; onClose: () => void }) {
    const form = useForm({ nom: '', prenom: '', telephone: '', ville: '', cin: '', date_naissance: '', ancien_numero_papier: '', creer_quand_meme: false });
    const { data, setData, errors } = form;
    const champ = (k: Exclude<keyof typeof data, 'creer_quand_meme'>, label: string, type = 'text') => (
        <Field label={label} error={errors[k]}>
            <input type={type} className={inputCls} value={data[k]} onChange={(e) => setData(k, e.target.value)} />
        </Field>
    );
    return (
        <Modal open={open} onClose={onClose} titre="Nouveau patient (saisie par la réception)">
            <div className="grid gap-3 md:grid-cols-2">
                {champ('nom', 'Nom')}
                {champ('prenom', 'Prénom')}
                {champ('telephone', 'Téléphone')}
                {champ('ville', 'Ville')}
                {champ('cin', 'N° CIN')}
                {champ('date_naissance', 'Date de naissance', 'date')}
                {champ('ancien_numero_papier', 'Ancien n° papier (si déjà venu)')}
            </div>
            {errors.nom?.includes('existe') && (
                <label className="mt-2 flex items-center gap-2 text-sm font-medium text-amber-800">
                    <input type="checkbox" checked={data.creer_quand_meme} onChange={(e) => setData('creer_quand_meme', e.target.checked)} /> C'est une autre
                    personne : créer quand même
                </label>
            )}
            <p className="mt-2 text-xs text-muted-foreground">
                Conseil : pour un nouveau patient présent au centre, préférez la tablette (il remplit lui-même ses antécédents).
            </p>
            <Button className="mt-4 w-full" onClick={() => form.post('/patients', { onSuccess: onClose })} disabled={form.processing}>
                Créer le patient et son dossier
            </Button>
        </Modal>
    );
}

Dossiers.layout = { breadcrumbs: [{ title: 'Tous les dossiers', href: '/patients' }] };
