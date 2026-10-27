import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Field, inputCls, Modal, textareaCls } from '@/components/cabinet/ui';
import { Button } from '@/components/ui/button';
import { conseilles, faitCeSoin } from '@/lib/docteurs';
import type { DocSoins } from '@/lib/docteurs';
import { cn } from '@/lib/utils';

type Doc = DocSoins;
type ActeO = { id: number; libelle: string; dents: string | null };

export type Prochain = {
    mode: 'reception' | 'fixer';
    docteur_id: string;
    acte_id: string;
    mission: string;
    notes: string;
    date: string;
    heure: string;
    duree: number;
    forcer: boolean;
};

const demain = () => {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    return d.toISOString().slice(0, 10);
};

export const prochainVide = (mission = ''): Prochain => ({
    mode: 'reception',
    docteur_id: '',
    acte_id: '',
    mission,
    notes: '',
    date: demain(),
    heure: '',
    duree: 30,
    forcer: false,
});

/** Créneaux libres d'un docteur pour une date (boutons cliquables). */
export function useCreneaux(docteurId: string | number, date: string, duree: number) {
    const [creneaux, setCreneaux] = useState<string[] | null>(null);
    useEffect(() => {
        if (!docteurId || !date) {
            setCreneaux(null);
            return;
        }
        let ok = true;
        fetch(`/agenda/creneaux?docteur_id=${docteurId}&date=${date}&duree=${duree || 30}`, { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : []))
            .then((c) => ok && setCreneaux(Array.isArray(c) ? c : []))
            .catch(() => ok && setCreneaux([]));
        return () => {
            ok = false;
        };
    }, [docteurId, date, duree]);
    return creneaux;
}

export function Creneaux({ creneaux, value, onPick }: { creneaux: string[] | null; value: string; onPick: (h: string) => void }) {
    if (creneaux === null) return null;
    if (creneaux.length === 0) return <p className="text-xs text-orange-700">Aucun créneau libre ce jour-là (ou docteur absent). Essayez un autre jour.</p>;
    return (
        <div className="flex flex-wrap gap-1.5">
            {creneaux.map((h) => (
                <button
                    type="button"
                    key={h}
                    onClick={() => onPick(h)}
                    className={cn(
                        'rounded-md border px-2.5 py-1 text-sm font-medium',
                        value === h ? 'border-primary bg-primary text-white' : 'bg-background hover:border-primary',
                    )}
                >
                    {h}
                </button>
            ))}
        </div>
    );
}

/** Champs « prochain rendez-vous » : la réception fixe la date, ou on la fixe tout de suite. */
export function ProchainRdvChamps({
    value: v,
    onChange,
    errors = {},
    docteurs,
    actes = [],
}: {
    value: Prochain;
    onChange: (p: Prochain) => void;
    errors?: Record<string, string | undefined>;
    docteurs: Doc[];
    actes?: ActeO[];
}) {
    const set = (p: Partial<Prochain>) => onChange({ ...v, ...p });
    const creneaux = useCreneaux(v.mode === 'fixer' ? v.docteur_id : '', v.date, v.duree);
    const suggeres = conseilles(docteurs, v.mission);
    const choisi = docteurs.find((d) => String(d.id) === v.docteur_id);
    const exception = choisi && faitCeSoin(choisi, v.mission) === false;

    return (
        <div className="flex flex-col gap-3">
            <div className="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1 text-sm">
                {(
                    [
                        ['reception', 'La réception fixe la date'],
                        ['fixer', 'Je fixe la date maintenant'],
                    ] as const
                ).map(([m, lib]) => (
                    <button
                        type="button"
                        key={m}
                        onClick={() => set({ mode: m })}
                        className={cn('rounded-md px-3 py-2 font-medium', v.mode === m ? 'bg-white text-primary shadow' : 'text-muted-foreground')}
                    >
                        {lib}
                    </button>
                ))}
            </div>
            <div className="grid gap-3 md:grid-cols-2">
                <Field label="Chez quel docteur ?" error={errors.docteur_id}>
                    <select
                        className={inputCls}
                        value={v.docteur_id}
                        onChange={(e) => {
                            const d = docteurs.find((x) => String(x.id) === e.target.value);
                            set({ docteur_id: e.target.value, heure: '', duree: d?.duree_rdv || v.duree });
                        }}
                    >
                        <option value="">Choisir</option>
                        {[...suggeres, ...docteurs.filter((d) => !suggeres.includes(d))].map((d) => (
                            <option key={d.id} value={d.id}>
                                {suggeres.includes(d) ? '★ ' : ''}
                                {d.name} {d.specialite ? `· ${d.specialite}` : ''}
                            </option>
                        ))}
                    </select>
                    {exception && (
                        <span className="text-xs text-amber-700">Exception : {choisi.name} ne fait pas habituellement ce soin (possible quand même).</span>
                    )}
                </Field>
                <Field label="Soin à faire" error={errors.mission}>
                    <input
                        className={inputCls}
                        value={v.mission}
                        onChange={(e) => set({ mission: e.target.value, acte_id: '' })}
                        placeholder="Détartrage, couronne…"
                    />
                </Field>
            </div>
            {actes.length > 0 && (
                <div className="flex flex-wrap gap-1.5 text-xs">
                    <span className="self-center text-muted-foreground">Du plan :</span>
                    {actes.map((a) => (
                        <button
                            type="button"
                            key={a.id}
                            onClick={() => set({ acte_id: String(a.id), mission: `${a.libelle}${a.dents ? ` · ${a.dents}` : ''}` })}
                            className={cn(
                                'rounded-full border px-2.5 py-1',
                                v.acte_id === String(a.id) ? 'border-primary bg-primary/10 text-primary' : 'bg-background',
                            )}
                        >
                            {a.libelle} {a.dents ? `· ${a.dents}` : ''}
                        </button>
                    ))}
                </div>
            )}
            {v.mode === 'reception' ? (
                <Field label="Note pour la réception (délai, préférence du patient…)">
                    <textarea
                        className={textareaCls}
                        rows={2}
                        value={v.notes}
                        onChange={(e) => set({ notes: e.target.value })}
                        placeholder="Ex. dans 2 semaines, plutôt le matin"
                    />
                </Field>
            ) : (
                <>
                    <div className="grid grid-cols-3 gap-2">
                        <Field label="Date" error={errors.date}>
                            <input type="date" className={inputCls} value={v.date} onChange={(e) => set({ date: e.target.value, heure: '' })} />
                        </Field>
                        <Field label="Heure" error={errors.heure}>
                            <input type="time" step={900} className={inputCls} value={v.heure} onChange={(e) => set({ heure: e.target.value })} />
                        </Field>
                        <Field label="Durée (min)">
                            <input
                                type="number"
                                min={10}
                                step={5}
                                className={inputCls}
                                value={v.duree}
                                onChange={(e) => set({ duree: Number(e.target.value) })}
                            />
                        </Field>
                    </div>
                    {v.docteur_id ? (
                        <Creneaux creneaux={creneaux} value={v.heure} onPick={(h) => set({ heure: h })} />
                    ) : (
                        <p className="rounded-lg border border-dashed px-3 py-2 text-sm text-muted-foreground">
                            Choisissez d'abord le docteur : ses heures disponibles s'affichent ici.
                        </p>
                    )}
                    {errors.heure && (
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={v.forcer} onChange={(e) => set({ forcer: e.target.checked })} /> Forcer quand même (exception)
                        </label>
                    )}
                </>
            )}
        </div>
    );
}

export const prochainIncomplet = (p: Prochain) => !p.docteur_id || !p.mission || (p.mode === 'fixer' && (!p.date || !p.heure));

/** Depuis le dossier : prévoir le prochain rendez-vous (docteur ou réception). */
export function ProchainRdvModal({ dossierId, docteurs, actes, onClose }: { dossierId: number; docteurs: Doc[]; actes: ActeO[]; onClose: () => void }) {
    const form = useForm<Prochain>(prochainVide(actes[0] ? `${actes[0].libelle}${actes[0].dents ? ` · ${actes[0].dents}` : ''}` : ''));
    return (
        <Modal open onClose={onClose} titre="Prochain rendez-vous">
            <div className="flex flex-col gap-3">
                <ProchainRdvChamps value={form.data} onChange={(p) => form.setData(p)} errors={form.errors} docteurs={docteurs} actes={actes} />
                <Button
                    disabled={form.processing || prochainIncomplet(form.data)}
                    onClick={() => form.post(`/dossiers/${dossierId}/prochain-rdv`, { preserveScroll: true, onSuccess: onClose })}
                >
                    {form.data.mode === 'fixer' ? 'Enregistrer le rendez-vous' : 'Envoyer à la réception'}
                </Button>
            </div>
        </Modal>
    );
}
