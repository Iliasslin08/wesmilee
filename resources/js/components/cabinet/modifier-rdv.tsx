import { router, useForm } from '@inertiajs/react';
import { Field, inputCls, Modal } from '@/components/cabinet/ui';
import { Button } from '@/components/ui/button';

type RdvMin = { id: number; debut: string; fin: string | null; mission: string; docteur_id?: number | null; statut?: string; patient: { nom_complet: string } };

const pad = (n: number) => String(n).padStart(2, '0');
const local = (iso: string) => {
    const d = new Date(iso);
    return { date: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`, heure: `${pad(d.getHours())}:${pad(d.getMinutes())}` };
};

/** Modifier ou annuler un rendez-vous (réception, ou le docteur pour les siens). */
export function ModifierRdv({ rdv, docteurs, onClose }: { rdv: RdvMin; docteurs?: { id: number; name: string }[]; onClose: () => void }) {
    const { date, heure } = local(rdv.debut);
    const duree = rdv.fin ? Math.max(10, Math.round((new Date(rdv.fin).getTime() - new Date(rdv.debut).getTime()) / 60000)) : 30;
    const form = useForm({ date, heure, duree, mission: rdv.mission, docteur_id: rdv.docteur_id ? String(rdv.docteur_id) : '', forcer: false });

    return (
        <Modal open onClose={onClose} titre={`Rendez-vous · ${rdv.patient.nom_complet}`}>
            <div className="flex flex-col gap-3">
                {rdv.statut && ['absent', 'annule'].includes(rdv.statut) && (
                    <div className="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        Ce rendez-vous est {rdv.statut === 'absent' ? 'marqué absent' : 'annulé'} : en enregistrant, il revient dans l’agenda (reprogrammé).
                    </div>
                )}
                {rdv.statut && ['termine', 'en_cours'].includes(rdv.statut) && (
                    <div className="rounded-md bg-slate-100 px-3 py-2 text-sm text-slate-700">
                        Rendez-vous déjà passé : vous pouvez corriger la mission, l’heure ou le docteur.
                    </div>
                )}
                <Field label="Mission" error={form.errors.mission}>
                    <input className={inputCls} value={form.data.mission} onChange={(e) => form.setData('mission', e.target.value)} />
                </Field>
                {docteurs && (
                    <Field label="Docteur">
                        <select className={inputCls} value={form.data.docteur_id} onChange={(e) => form.setData('docteur_id', e.target.value)}>
                            {docteurs.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                )}
                <div className="grid grid-cols-3 gap-2">
                    <Field label="Date">
                        <input type="date" className={inputCls} value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                    </Field>
                    <Field label="Heure" error={form.errors.heure}>
                        <input type="time" step={900} className={inputCls} value={form.data.heure} onChange={(e) => form.setData('heure', e.target.value)} />
                    </Field>
                    <Field label="Durée (min)">
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
                        <input type="checkbox" checked={form.data.forcer} onChange={(e) => form.setData('forcer', e.target.checked)} /> Forcer quand même
                    </label>
                )}
                <div className="flex flex-wrap justify-between gap-2">
                    {!rdv.statut || ['planifie', 'arrive'].includes(rdv.statut) ? (
                        <Button
                            variant="outline"
                            className="text-red-600"
                            onClick={() =>
                                confirm('Annuler ce rendez-vous ?') &&
                                router.post(`/rendez-vous/${rdv.id}/annuler`, {}, { preserveScroll: true, onSuccess: onClose })
                            }
                        >
                            Annuler le rendez-vous
                        </Button>
                    ) : (
                        <span />
                    )}
                    <Button disabled={form.processing} onClick={() => form.put(`/rendez-vous/${rdv.id}`, { preserveScroll: true, onSuccess: onClose })}>
                        Enregistrer
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
