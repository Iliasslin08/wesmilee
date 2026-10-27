import { Head, router, useForm, usePoll } from '@inertiajs/react';
import { IdCard, Paperclip } from 'lucide-react';
import { useState } from 'react';
import { Box, Empty, Field, inputCls, Page, PageTitle, Pill } from '@/components/cabinet/ui';
import { Button } from '@/components/ui/button';
import { dateHeure } from '@/lib/cabinet';

type Fiche = {
    id: number;
    nom: string;
    prenom: string;
    date_naissance: string | null;
    sexe: string | null;
    telephone: string | null;
    ville: string | null;
    adresse: string | null;
    profession: string | null;
    cin: string | null;
    antecedents: string[] | null;
    antecedents_autres: string | null;
    allergies: string | null;
    traitements: string | null;
    created_at: string;
    doublons: { id: number; nom_complet: string; telephone: string | null; cin: string | null; ville: string | null; dossier: { id: number; numero: string } | null; raisons: string[] }[];
};

export default function Fiches({ fiches, antecedents }: { fiches: Fiche[]; antecedents: Record<string, string> }) {
    // Les nouvelles fiches de la tablette apparaissent toutes seules
    usePoll(5000, { only: ['fiches', 'fichesEnAttente'] });
    const [ouverte, setOuverte] = useState<number | null>(fiches[0]?.id ?? null);
    const ouverteId = ouverte ?? fiches[0]?.id ?? null;

    return (
        <Page>
            <Head title="Fiches à valider" />
            <PageTitle
                titre="Fiches à valider"
                sousTitre="Fiches remplies par les patients sur la tablette. Vérifiez avec le patient, ajoutez sa CIN, puis validez : le dossier est créé."
            />
            {fiches.length === 0 && <Empty>Aucune fiche en attente. La tablette est à l'adresse /accueil.</Empty>}
            {fiches.map((f) => (
                <Box
                    key={f.id}
                    titre={
                        <button className="text-left" onClick={() => setOuverte(ouverteId === f.id ? -1 : f.id)}>
                            {f.nom.toUpperCase()} {f.prenom} <span className="text-sm font-normal text-muted-foreground">· {f.telephone} · reçue le {dateHeure(f.created_at)}</span>
                        </button>
                    }
                    actions={
                        f.allergies ? <Pill className="bg-red-100 text-red-700">Allergie : {f.allergies}</Pill> : undefined
                    }
                >
                    {f.doublons.length > 0 && (
                        <div className="mb-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                            <b>Attention : ce patient a peut-être déjà un dossier.</b>
                            <ul className="mt-2 flex flex-col gap-2">
                                {f.doublons.map((d) => (
                                    <li key={d.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md bg-white/70 px-3 py-2 dark:bg-black/20">
                                        <span>
                                            <b>{d.nom_complet}</b> · {d.telephone ?? '—'} {d.dossier && `· dossier n° ${d.dossier.numero}`}{' '}
                                            <span className="text-amber-700">({d.raisons.join(', ')})</span>
                                        </span>
                                        <span className="flex gap-2">
                                            {d.dossier && (
                                                <a href={`/dossiers/${d.dossier.id}`} target="_blank" rel="noreferrer" className="self-center text-xs underline">
                                                    Voir le dossier
                                                </a>
                                            )}
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    confirm(`Rattacher cette fiche au dossier de ${d.nom_complet} ? Ses nouvelles infos (téléphone, allergies…) seront ajoutées.`) &&
                                                    router.post(`/fiches/${f.id}/rattacher/${d.id}`)
                                                }
                                            >
                                                C'est le même patient
                                            </Button>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                            <p className="mt-2 text-xs">Si c'est une autre personne, validez normalement plus bas.</p>
                        </div>
                    )}
                    {ouverteId === f.id && <FormFiche fiche={f} antecedents={antecedents} />}
                </Box>
            ))}
        </Page>
    );
}

function FormFiche({ fiche, antecedents }: { fiche: Fiche; antecedents: Record<string, string> }) {
    const form = useForm({
        nom: fiche.nom,
        prenom: fiche.prenom,
        date_naissance: fiche.date_naissance?.slice(0, 10) ?? '',
        sexe: fiche.sexe ?? '',
        cin: fiche.cin ?? '',
        telephone: fiche.telephone ?? '',
        ville: fiche.ville ?? '',
        adresse: fiche.adresse ?? '',
        profession: fiche.profession ?? '',
        antecedents: fiche.antecedents ?? [],
        antecedents_autres: fiche.antecedents_autres ?? '',
        allergies: fiche.allergies ?? '',
        traitements: fiche.traitements ?? '',
        ancien_numero_papier: '',
        cin_fichiers: [] as File[],
        papiers: [] as File[],
    });
    const { data, setData, errors } = form;
    const champ = (k: keyof typeof data, label: string, type = 'text') => (
        <Field label={label} error={errors[k]}>
            <input type={type} className={inputCls} value={data[k] as string} onChange={(e) => setData(k, e.target.value as never)} />
        </Field>
    );

    return (
        <div className="flex flex-col gap-4">
            <div className="grid gap-3 md:grid-cols-3">
                {champ('nom', 'Nom')}
                {champ('prenom', 'Prénom')}
                {champ('date_naissance', 'Date de naissance', 'date')}
                {champ('cin', 'N° CIN')}
                {champ('telephone', 'Téléphone')}
                {champ('ville', 'Ville')}
                {champ('profession', 'Profession')}
                {champ('adresse', 'Adresse')}
                {champ('ancien_numero_papier', 'Ancien n° de dossier papier (si déjà venu)')}
            </div>
            <div className="flex flex-wrap gap-2 text-sm">
                {Object.entries(antecedents).map(([k, v]) => (
                    <label key={k} className="flex items-center gap-1 rounded-full border px-3 py-1">
                        <input
                            type="checkbox"
                            checked={data.antecedents.includes(k)}
                            onChange={(e) => setData('antecedents', e.target.checked ? [...data.antecedents, k] : data.antecedents.filter((a) => a !== k))}
                        />
                        {v}
                    </label>
                ))}
            </div>
            <div className="grid gap-3 md:grid-cols-2">
                {champ('allergies', 'Allergies')}
                {champ('traitements', 'Traitements en cours')}
            </div>
            <div className="grid gap-3 md:grid-cols-2">
                <label className="flex cursor-pointer flex-col gap-1 rounded-xl border-2 border-dashed border-teal-300 bg-teal-50/50 p-4 text-sm hover:bg-teal-50 dark:bg-teal-950/20">
                    <span className="flex items-center gap-2 font-semibold text-teal-900 dark:text-teal-100">
                        <IdCard className="size-5" /> Carte nationale (recto / verso)
                    </span>
                    <span className="text-muted-foreground">
                        {data.cin_fichiers.length ? data.cin_fichiers.map((f) => f.name).join(', ') : 'Prendre une photo ou choisir un scan'}
                    </span>
                    <input type="file" multiple accept="image/*,application/pdf" capture="environment" className="hidden" onChange={(e) => setData('cin_fichiers', Array.from(e.target.files ?? []))} />
                    {errors.cin_fichiers && <span className="text-red-600">{errors.cin_fichiers}</span>}
                </label>
                <label className="flex cursor-pointer flex-col gap-1 rounded-xl border-2 border-dashed border-slate-300 p-4 text-sm hover:bg-muted">
                    <span className="flex items-center gap-2 font-semibold">
                        <Paperclip className="size-5" /> Autres papiers (ordonnance, radio, ancien dossier…)
                    </span>
                    <span className="text-muted-foreground">{data.papiers.length ? data.papiers.map((f) => f.name).join(', ') : 'Facultatif'}</span>
                    <input type="file" multiple accept="image/*,application/pdf" className="hidden" onChange={(e) => setData('papiers', Array.from(e.target.files ?? []))} />
                    {errors.papiers && <span className="text-red-600">{errors.papiers}</span>}
                </label>
            </div>
            <div className="flex flex-wrap justify-end gap-2">
                <Button variant="outline" onClick={() => confirm('Supprimer cette fiche ?') && router.delete(`/fiches/${fiche.id}`)}>
                    Supprimer (erreur / doublon)
                </Button>
                <Button onClick={() => form.post(`/fiches/${fiche.id}/valider`, { forceFormData: true })} disabled={form.processing}>
                    Valider et créer le dossier
                </Button>
            </div>
            <p className="text-xs text-muted-foreground">Vous pourrez aussi ajouter la CIN et d'autres papiers plus tard, avec le bouton « Ajouter CIN / papiers » du dossier.</p>
        </div>
    );
}

Fiches.layout = { breadcrumbs: [{ title: 'Fiches à valider', href: '/fiches' }] };
