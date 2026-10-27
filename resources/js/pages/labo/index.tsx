import { cn } from '@/lib/utils';
import { Head, router, useForm, usePoll } from '@inertiajs/react';
import { MapPin, ScanLine, Archive, ArchiveRestore, Inbox } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Box, Empty, Page, PageTitle, Pill, Tabs } from '@/components/cabinet/ui';
import { dateFr, PRIORITE_LABO } from '@/lib/cabinet';
import type { MembreLabo } from '@/lib/labo';
import { conseille, mission } from '@/lib/labo';
import { inputCls } from '@/components/cabinet/ui';

type Commande = {
    id: number;
    technicien_id: number | null;
    technicien: { id: number; name: string } | null;
    travail: string;
    dents: string | null;
    materiau: string | null;
    teinte: string | null;
    instructions: string | null;
    date_pose: string | null;
    date_prete?: string | null;
    created_at?: string;
    envoye_par?: { id: number; name: string } | null;
    statut: string;
    statut_label: string;
    priorite: string;
    garantie: boolean;
    parent: { id: number; travail: string } | null;
    dossier: { id: number; numero: string; patient: { nom: string; prenom: string; ville: string | null; vient_de_loin: boolean; nom_complet: string } };
};

type RadioRecue = {
    id: number;
    type_label: string;
    dents: string | null;
    mesures: string | null;
    notes: string | null;
    created_at: string;
    vue_labo_le: string | null;
    faite_par: { name: string } | null;
    dossier: { id: number; numero: string; patient: { nom_complet: string; ville: string | null; vient_de_loin: boolean } };
    documents: { id: number; nom_original: string | null }[];
};

const estImage = (n: string | null) => /\.(jpe?g|png|webp)$/i.test(n ?? '');

function RadiosRecues({ radios, archives = false }: { radios: RadioRecue[]; archives?: boolean }) {
    const nouvelles = archives ? 0 : radios.filter((r) => !r.vue_labo_le).length;
    return (
        <Box
            titre={
                <span className="flex items-center gap-2">
                    <ScanLine className="size-5" /> {archives ? 'Radios archivées' : 'Radios reçues de la radiologie'}{' '}
                    {nouvelles > 0 && <Pill className="bg-red-600 text-white">{nouvelles} nouvelle(s)</Pill>}
                </span>
            }
        >
            {radios.length === 0 ? (
                <Empty>Aucune radio reçue.</Empty>
            ) : (
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {radios.map((r) => (
                        <div
                            key={r.id}
                            className={cn(
                                'rounded-lg border p-3 text-sm',
                                !archives && !r.vue_labo_le && 'border-amber-400 bg-amber-50/60 dark:bg-amber-950/20',
                            )}
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <b>{r.dossier.patient.nom_complet}</b> <span className="text-muted-foreground">· dossier {r.dossier.numero}</span>
                                    {r.dossier.patient.vient_de_loin && <Pill className="ml-1 bg-orange-100 text-orange-700">{r.dossier.patient.ville}</Pill>}
                                </div>
                                {!archives && !r.vue_labo_le && <Pill className="bg-amber-200 text-amber-900">Nouvelle</Pill>}
                            </div>
                            <div className="text-muted-foreground">
                                {r.type_label} {r.dents && `· dents ${r.dents}`} · {dateFr(r.created_at)} · {r.faite_par?.name}
                            </div>
                            {r.mesures && (
                                <div className="mt-1 rounded bg-teal-50 px-2 py-1 font-semibold text-teal-900 dark:bg-teal-950 dark:text-teal-100">
                                    Mesures : {r.mesures}
                                </div>
                            )}
                            {r.notes && <div className="mt-1">{r.notes}</div>}
                            <div className="mt-2 flex flex-wrap gap-2">
                                {r.documents.map((d) =>
                                    estImage(d.nom_original) ? (
                                        <a key={d.id} href={`/documents/${d.id}`} target="_blank" rel="noreferrer">
                                            <img src={`/documents/${d.id}`} alt="Radio" className="h-36 w-full max-w-64 rounded-lg border object-cover" />
                                        </a>
                                    ) : (
                                        <a
                                            key={d.id}
                                            href={`/documents/${d.id}`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="rounded border px-2 py-1 text-xs underline"
                                        >
                                            {d.nom_original ?? 'Fichier'}
                                        </a>
                                    ),
                                )}
                            </div>
                            {!archives && (
                                <div className="mt-2 flex gap-4 text-xs">
                                    {!r.vue_labo_le && (
                                        <button
                                            className="text-primary underline"
                                            onClick={() => router.post(`/labo/radios/${r.id}/vue`, {}, { preserveScroll: true })}
                                        >
                                            Marquer comme vue
                                        </button>
                                    )}
                                    <button
                                        className="text-muted-foreground underline"
                                        onClick={() => router.post(`/labo/radios/${r.id}/archiver`, {}, { preserveScroll: true })}
                                    >
                                        Archiver
                                    </button>
                                </div>
                            )}
                            {archives && (
                                <button
                                    className="mt-2 text-xs text-primary underline"
                                    onClick={() => router.post(`/labo/radios/${r.id}/desarchiver`, {}, { preserveScroll: true })}
                                >
                                    Désarchiver
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </Box>
    );
}

/** Demande écrite par un docteur / la finance : la cheffe la confie et fixe la date « prêt le ». */
function Demande({ c, equipe }: { c: Commande; equipe: MembreLabo[] }) {
    const form = useForm({ travail: c.travail, technicien_id: '', date_prete: '' });
    const suggeres = equipe.filter((m) => conseille(m, c.instructions ?? c.travail));
    return (
        <div className="rounded-lg border border-amber-300 bg-amber-50/50 p-3 text-sm dark:bg-amber-950/20">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <b>{c.dossier.patient.nom_complet}</b> <span className="text-muted-foreground">· dossier {c.dossier.numero}</span>
                    {c.dossier.patient.vient_de_loin && <Pill className="ml-1 bg-orange-100 text-orange-700">{c.dossier.patient.ville}</Pill>}
                </div>
                <span className="text-xs text-muted-foreground">
                    Demandé par {c.envoye_par?.name ?? '—'} · {c.created_at ? dateFr(c.created_at) : ''} · pose :{' '}
                    {c.date_pose ? dateFr(c.date_pose) : 'pas fixée'}
                </span>
            </div>
            <p className="my-2 rounded bg-background px-3 py-2 whitespace-pre-line">{c.instructions ?? c.travail}</p>
            <div className="grid gap-2 md:grid-cols-[2fr_1.5fr_1fr_auto]">
                <input
                    className={inputCls}
                    value={form.data.travail}
                    onChange={(e) => form.setData('travail', e.target.value)}
                    placeholder="Travail (titre court)"
                />
                <select className={inputCls} value={form.data.technicien_id} onChange={(e) => form.setData('technicien_id', e.target.value)}>
                    <option value="">Confier à…</option>
                    {[...suggeres, ...equipe.filter((m) => !suggeres.includes(m))].map((m) => (
                        <option key={m.id} value={m.id}>
                            {suggeres.includes(m) ? '★ ' : ''}
                            {m.name} · {mission(m)}
                        </option>
                    ))}
                </select>
                <input
                    type="date"
                    className={inputCls}
                    title="Prêt le"
                    value={form.data.date_prete}
                    onChange={(e) => form.setData('date_prete', e.target.value)}
                />
                <Button
                    disabled={!form.data.travail.trim() || !form.data.technicien_id || !form.data.date_prete || form.processing}
                    onClick={() => form.post(`/labo/${c.id}/accepter`, { preserveScroll: true })}
                >
                    Accepter et confier
                </Button>
            </div>
            {Object.values(form.errors)[0] && <p className="mt-1 text-xs text-red-700">{Object.values(form.errors)[0]}</p>}
        </div>
    );
}

export default function Labo({
    commandes,
    historique,
    statuts,
    radios = [],
    equipe = [],
    qui = 'tous',
    chef = false,
    aAccepter = [],
}: {
    chef?: boolean;
    aAccepter?: Commande[];
    commandes: Commande[];
    historique: boolean;
    statuts: { value: string; label: string }[];
    radios?: RadioRecue[];
    equipe?: MembreLabo[];
    qui?: string;
}) {
    usePoll(10000);
    const filtrer = (q: string) => router.get('/labo', { ...(historique ? { historique: 1 } : {}), qui: q }, { preserveScroll: true });
    const urgents = commandes.filter((c) => c.priorite === 'urgent').length;

    return (
        <Page>
            <Head title="Labo" />
            <PageTitle
                titre={chef ? 'Commandes du labo' : 'Mes travaux'}
                sousTitre={
                    chef
                        ? 'Vous acceptez les demandes, les confiez à une personne du labo et fixez la date où le travail doit être prêt.'
                        : 'Les travaux qui vous sont confiés, avec la date où ils doivent être prêts.'
                }
            />
            {chef && !historique && (
                <Box
                    titre={
                        <span className="flex items-center gap-2">
                            <Inbox className="size-5" /> Nouvelles demandes{' '}
                            {aAccepter.length > 0 && <Pill className="bg-red-600 text-white">{aAccepter.length}</Pill>}
                        </span>
                    }
                >
                    {aAccepter.length === 0 ? (
                        <Empty>Aucune nouvelle demande.</Empty>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {aAccepter.map((c) => (
                                <Demande key={c.id} c={c} equipe={equipe} />
                            ))}
                        </div>
                    )}
                </Box>
            )}
            <Tabs
                value={historique ? 'h' : 'c'}
                onChange={(v) => router.get('/labo', { ...(v === 'h' ? { historique: 1 } : {}), qui })}
                items={[
                    { value: 'c', label: 'En cours' },
                    { value: 'h', label: 'Archive' },
                ]}
            />
            {/* Filtre par personne du labo (selon la mission de chacun) : seulement pour la cheffe */}
            <div className={cn('flex flex-wrap gap-2 text-sm', !chef && 'hidden')}>
                {[
                    ['moi', 'Mes travaux'],
                    ['tous', 'Toute l’équipe'],
                ].map(([v, l]) => (
                    <button
                        key={v}
                        onClick={() => filtrer(v)}
                        className={cn('rounded-full border px-3 py-1', qui === v && 'border-primary bg-primary text-primary-foreground')}
                    >
                        {l}
                    </button>
                ))}
                {equipe.map((m) => (
                    <button
                        key={m.id}
                        onClick={() => filtrer(String(m.id))}
                        title={mission(m)}
                        className={cn('rounded-full border px-3 py-1', qui === String(m.id) && 'border-primary bg-primary/10 font-semibold')}
                    >
                        {m.name} <span className="text-xs text-muted-foreground">· {m.taches_principales}</span>
                    </button>
                ))}
            </div>
            {(radios.length > 0 || !historique) && <RadiosRecues radios={radios} archives={historique} />}
            {!historique && urgents > 0 && (
                <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 font-semibold text-red-800">
                    À préparer en priorité : {urgents} travail(s) avec une pose très proche.
                </div>
            )}
            <Box>
                {commandes.length === 0 ? (
                    <Empty>{historique ? 'Archive vide.' : 'Aucune commande en cours.'}</Empty>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-sm">
                            <thead className="text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="py-2">Priorité</th>
                                    <th>Patient</th>
                                    <th>Travail</th>
                                    <th>Prêt le</th>
                                    <th>Pose prévue</th>
                                    <th>Confié à</th>
                                    <th>Avancement</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {commandes.map((c) => (
                                    <tr key={c.id} className="align-top">
                                        <td className="py-3">
                                            <Pill className={PRIORITE_LABO[c.priorite]?.cls}>{PRIORITE_LABO[c.priorite]?.label}</Pill>
                                        </td>
                                        <td className="py-3">
                                            <b>{c.dossier.patient.nom_complet}</b>
                                            <div className="text-xs text-muted-foreground">Dossier {c.dossier.numero}</div>
                                            {c.dossier.patient.vient_de_loin && (
                                                <Pill className="mt-1 bg-orange-100 text-orange-700">
                                                    <MapPin className="size-3" /> Vient de {c.dossier.patient.ville}
                                                </Pill>
                                            )}
                                        </td>
                                        <td className="py-3">
                                            <b>{c.travail}</b> {c.dents && `· ${c.dents}`}
                                            <div className="text-xs text-muted-foreground">
                                                {[c.materiau, c.teinte && `teinte ${c.teinte}`].filter(Boolean).join(' · ')}
                                            </div>
                                            {c.parent && <div className="text-xs text-orange-700">Refait{c.garantie ? ' sous garantie' : ''}</div>}
                                            {c.instructions && <div className="text-xs">{c.instructions}</div>}
                                        </td>
                                        <td className="py-3 font-bold text-teal-800 dark:text-teal-200">
                                            {chef ? (
                                                <input
                                                    type="date"
                                                    className={cn(inputCls, 'h-9 w-40', !c.date_prete && 'border-amber-400 bg-amber-50 dark:bg-amber-950/30')}
                                                    title={c.date_prete ? 'Prêt le' : 'Date à fixer'}
                                                    value={c.date_prete ? c.date_prete.slice(0, 10) : ''}
                                                    onChange={(e) =>
                                                        router.put(`/labo/${c.id}`, { date_prete: e.target.value || null }, { preserveScroll: true })
                                                    }
                                                />
                                            ) : c.date_prete ? (
                                                dateFr(c.date_prete)
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="py-3">{c.date_pose ? dateFr(c.date_pose) : 'Pas de RDV fixé'}</td>
                                        <td className="py-3">
                                            {!chef ? (
                                                <span>{c.technicien?.name ?? '—'}</span>
                                            ) : (
                                                <select
                                                    className={cn(inputCls, 'h-9 w-48', !c.technicien_id && 'border-amber-400 bg-amber-50')}
                                                    value={c.technicien_id ?? ''}
                                                    onChange={(e) =>
                                                        router.put(`/labo/${c.id}`, { technicien_id: e.target.value || null }, { preserveScroll: true })
                                                    }
                                                >
                                                    <option value="">— À attribuer —</option>
                                                    {equipe.map((m) => (
                                                        <option key={m.id} value={m.id}>
                                                            {conseille(m, c.travail) ? '★ ' : ''}
                                                            {m.name} · {mission(m)}
                                                        </option>
                                                    ))}
                                                </select>
                                            )}
                                        </td>
                                        <td className="py-3">
                                            <select
                                                className={cn(inputCls, 'h-9 w-44')}
                                                value={c.statut}
                                                onChange={(e) => router.put(`/labo/${c.id}`, { statut: e.target.value }, { preserveScroll: true })}
                                            >
                                                {statuts.map((s) => (
                                                    <option key={s.value} value={s.value}>
                                                        {s.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="py-3 text-right">
                                            {historique ? (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => router.post(`/labo/${c.id}/desarchiver`, {}, { preserveScroll: true })}
                                                >
                                                    <ArchiveRestore /> Désarchiver
                                                </Button>
                                            ) : (
                                                ['pret', 'livre', 'pose'].includes(c.statut) && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => router.post(`/labo/${c.id}/archiver`, {}, { preserveScroll: true })}
                                                    >
                                                        <Archive /> Archiver
                                                    </Button>
                                                )
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <p className="mt-3 text-xs text-muted-foreground">
                    Le labo voit uniquement ses commandes, pas le reste du dossier médical ni la partie financière.
                </p>
            </Box>
        </Page>
    );
}

Labo.layout = { breadcrumbs: [{ title: 'Labo', href: '/labo' }] };
