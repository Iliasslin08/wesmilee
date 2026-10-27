import { Head, router, useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import { Box, Field, inputCls, Modal, Page, PageTitle, Pill } from '@/components/cabinet/ui';
import { Button } from '@/components/ui/button';

type U = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    telephone: string | null;
    specialite: string | null;
    couleur: string | null;
    salle: string | null;
    actif: boolean;
    fait_consultation: boolean;
    assistante: string | null;
    taches_principales: string | null;
    duree_rdv: number | null;
    taches_secondaires: string | null;
    autres_taches: string | null;
    nb_patients: number | null;
    travaux_en_cours: number | null;
};

export default function Utilisateurs({ utilisateurs, roles, salles }: { utilisateurs: U[]; roles: { value: string; label: string }[]; salles: string[] }) {
    const [edite, setEdite] = useState<U | null | 'nouveau'>(null);
    return (
        <Page>
            <Head title="Utilisateurs" />
            <PageTitle
                titre="Utilisateurs"
                sousTitre="Chaque personne a son propre login. Désactivez un compte au lieu de le supprimer."
                actions={
                    <Button onClick={() => setEdite('nouveau')}>
                        <UserPlus /> Nouveau compte
                    </Button>
                }
            />
            <Box>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[640px] text-sm">
                        <thead className="text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="py-2">Nom</th>
                                <th>Identifiant</th>
                                <th>Rôle</th>
                                <th>Spécialité / tâches</th>
                                <th>Salle</th>
                                <th>Patients</th>
                                <th>État</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {utilisateurs.map((u) => (
                                <tr key={u.id}>
                                    <td className="py-2 font-semibold">
                                        {u.couleur && <span className="mr-2 inline-block size-3 rounded-full" style={{ background: u.couleur }} />}
                                        {u.name}
                                    </td>
                                    <td>{u.email}</td>
                                    <td>{u.role_label}</td>
                                    <td className="max-w-72">
                                        {u.specialite}
                                        {u.fait_consultation && <Pill className="ml-1 bg-teal-100 text-teal-800">Consultation</Pill>}
                                        {u.taches_principales && <div className="text-xs text-muted-foreground">{u.taches_principales}</div>}
                                        {u.assistante && <div className="text-xs text-muted-foreground">Assistante : {u.assistante}</div>}
                                    </td>
                                    <td>{u.salle}</td>
                                    <td className="whitespace-nowrap">
                                        {u.nb_patients === null ? (
                                            <span className="text-muted-foreground">—</span>
                                        ) : (
                                            <>
                                                <b>{u.nb_patients}</b> patient{u.nb_patients > 1 ? 's' : ''}
                                                {u.travaux_en_cours !== null && (
                                                    <div className="text-xs text-muted-foreground">{u.travaux_en_cours} travail(s) en cours</div>
                                                )}
                                            </>
                                        )}
                                    </td>
                                    <td>
                                        {u.actif ? (
                                            <Pill className="bg-green-100 text-green-800">Actif</Pill>
                                        ) : (
                                            <Pill className="bg-slate-100 text-slate-500">Désactivé</Pill>
                                        )}
                                    </td>
                                    <td className="text-right">
                                        <Button size="sm" variant="outline" onClick={() => setEdite(u)}>
                                            Modifier
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="ml-1 text-red-600"
                                            onClick={() =>
                                                confirm(`Supprimer le compte de ${u.name} ?`) &&
                                                router.delete(`/admin/utilisateurs/${u.id}`, { preserveScroll: true })
                                            }
                                        >
                                            Supprimer
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Box>
            {edite && <FormUser u={edite === 'nouveau' ? null : edite} roles={roles} salles={salles} onClose={() => setEdite(null)} />}
        </Page>
    );
}

function FormUser({ u, roles, salles, onClose }: { u: U | null; roles: { value: string; label: string }[]; salles: string[]; onClose: () => void }) {
    const form = useForm({
        name: u?.name ?? '',
        email: u?.email ?? '',
        role: u?.role ?? 'docteur',
        telephone: u?.telephone ?? '',
        specialite: u?.specialite ?? '',
        couleur: u?.couleur ?? '#04a3b5',
        salle: u?.salle ?? '',
        actif: u?.actif ?? true,
        fait_consultation: u?.fait_consultation ?? false,
        assistante: u?.assistante ?? '',
        taches_principales: u?.taches_principales ?? '',
        duree_rdv: u?.duree_rdv ?? '',
        taches_secondaires: u?.taches_secondaires ?? '',
        autres_taches: u?.autres_taches ?? '',
        password: '',
    });
    const { data, setData, errors } = form;
    return (
        <Modal open large onClose={onClose} titre={u ? `Modifier ${u.name}` : 'Nouveau compte'}>
            <div className="grid gap-3 md:grid-cols-2">
                <Field label="Nom affiché" error={errors.name}>
                    <input className={inputCls} value={data.name} onChange={(e) => setData('name', e.target.value)} />
                </Field>
                <Field label="Identifiant (e-mail)" error={errors.email}>
                    <input className={inputCls} value={data.email} onChange={(e) => setData('email', e.target.value)} />
                </Field>
                <Field label="Rôle" error={errors.role}>
                    <select className={inputCls} value={data.role} onChange={(e) => setData('role', e.target.value)}>
                        {roles.map((r) => (
                            <option key={r.value} value={r.value}>
                                {r.label}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Téléphone">
                    <input className={inputCls} value={data.telephone} onChange={(e) => setData('telephone', e.target.value)} />
                </Field>
                {data.role === 'docteur' && (
                    <>
                        <Field label="Spécialités (pour attribuer les missions)">
                            <input
                                className={inputCls}
                                placeholder="Prothèse, esthétique"
                                value={data.specialite}
                                onChange={(e) => setData('specialite', e.target.value)}
                            />
                        </Field>
                        <Field label="Salle habituelle">
                            <select className={inputCls} value={data.salle} onChange={(e) => setData('salle', e.target.value)}>
                                <option value="">—</option>
                                {[...new Set([...salles, ...(data.salle ? [data.salle] : [])])].map((s) => (
                                    <option key={s} value={s}>
                                        {s}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Assistante">
                            <input className={inputCls} value={data.assistante} onChange={(e) => setData('assistante', e.target.value)} />
                        </Field>
                        <label className="flex items-center gap-2 self-end pb-2 text-sm">
                            <input type="checkbox" checked={data.fait_consultation} onChange={(e) => setData('fait_consultation', e.target.checked)} /> Fait les
                            consultations
                        </label>
                        <Field label="Durée habituelle d'un RDV (min)">
                            <input
                                type="number"
                                min={5}
                                step={5}
                                className={inputCls}
                                value={data.duree_rdv}
                                onChange={(e) => setData('duree_rdv', e.target.value === '' ? '' : Number(e.target.value))}
                            />
                        </Field>
                        <Field label="Soins habituels (★ dans l'agenda)" className="md:col-span-2">
                            <input
                                className={inputCls}
                                placeholder="Couronnes, facettes, Hollywood Smile"
                                value={data.taches_principales}
                                onChange={(e) => setData('taches_principales', e.target.value)}
                            />
                        </Field>
                        <Field label="Tâches secondaires" className="md:col-span-2">
                            <input className={inputCls} value={data.taches_secondaires} onChange={(e) => setData('taches_secondaires', e.target.value)} />
                        </Field>
                        <Field label="Autres tâches" className="md:col-span-2">
                            <input className={inputCls} value={data.autres_taches} onChange={(e) => setData('autres_taches', e.target.value)} />
                        </Field>
                        <Field label="Couleur dans l'agenda">
                            <input type="color" className="h-10 w-20" value={data.couleur} onChange={(e) => setData('couleur', e.target.value)} />
                        </Field>
                    </>
                )}
                <Field label={u ? 'Nouveau mot de passe (laisser vide)' : 'Mot de passe'} error={errors.password}>
                    <input type="password" className={inputCls} value={data.password} onChange={(e) => setData('password', e.target.value)} />
                </Field>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={data.actif} onChange={(e) => setData('actif', e.target.checked)} /> Compte actif
                </label>
            </div>
            <Button
                className="mt-4 w-full"
                disabled={form.processing}
                onClick={() => (u ? form.put(`/admin/utilisateurs/${u.id}`, { onSuccess: onClose }) : form.post('/admin/utilisateurs', { onSuccess: onClose }))}
            >
                Enregistrer
            </Button>
        </Modal>
    );
}

Utilisateurs.layout = { breadcrumbs: [{ title: 'Utilisateurs', href: '/admin/utilisateurs' }] };
