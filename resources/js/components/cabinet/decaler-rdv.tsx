import { useForm } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import { useState } from 'react';
import { Field, inputCls, Modal } from '@/components/cabinet/ui';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const MINUTES = [10, 15, 20, 30, 45, 60];
const maintenant = () => new Date().toTimeString().slice(0, 5);

/**
 * « Retard » : décale d'un coup tous les rendez-vous suivants d'un docteur pour la journée.
 * Réception : on choisit le docteur. Docteur : ses propres rendez-vous.
 */
export function DecalerRdv({ date, docteurs, docteurId }: { date?: string; docteurs?: { id: number; name: string }[]; docteurId?: number | null }) {
    const [ouvert, setOuvert] = useState(false);
    const aujourdhui = new Date().toISOString().slice(0, 10);
    const form = useForm({
        docteur_id: docteurId ?? docteurs?.[0]?.id ?? null,
        date: date ?? aujourdhui,
        a_partir: maintenant(),
        minutes: 15,
        sms: false,
    });
    const ouvrir = () => {
        form.setData((d) => ({
            ...d,
            a_partir: (date ?? aujourdhui) === aujourdhui ? maintenant() : '08:00',
            date: date ?? aujourdhui,
            docteur_id: docteurId ?? d.docteur_id,
        }));
        form.clearErrors();
        setOuvert(true);
    };
    return (
        <>
            <Button variant="outline" onClick={ouvrir}>
                <Clock /> Retard · décaler
            </Button>
            <Modal open={ouvert} onClose={() => setOuvert(false)} titre="Retard : décaler les rendez-vous suivants">
                <div className="flex flex-col gap-4">
                    {docteurs && (
                        <Field label="Docteur" error={form.errors.docteur_id}>
                            <select
                                className={inputCls}
                                value={form.data.docteur_id ?? ''}
                                onChange={(e) => form.setData('docteur_id', Number(e.target.value))}
                            >
                                {docteurs.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    )}
                    <Field label="Décaler de" error={form.errors.minutes}>
                        <div className="flex flex-wrap gap-2">
                            {MINUTES.map((m) => (
                                <button
                                    key={m}
                                    type="button"
                                    onClick={() => form.setData('minutes', m)}
                                    className={cn(
                                        'rounded-full border px-4 py-1.5 text-sm font-semibold',
                                        form.data.minutes === m ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted',
                                    )}
                                >
                                    +{m} min
                                </button>
                            ))}
                            <input
                                type="number"
                                className={cn(inputCls, 'w-28')}
                                value={form.data.minutes}
                                onChange={(e) => form.setData('minutes', Number(e.target.value))}
                                title="Autre (négatif pour avancer)"
                            />
                        </div>
                    </Field>
                    <Field label="Rendez-vous à partir de" error={form.errors.a_partir}>
                        <input
                            type="time"
                            className={cn(inputCls, 'w-36')}
                            value={form.data.a_partir}
                            onChange={(e) => form.setData('a_partir', e.target.value)}
                        />
                    </Field>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={form.data.sms} onChange={(e) => form.setData('sms', e.target.checked)} />
                        Prévenir les patients par SMS (seulement ceux qui ont accepté les SMS)
                    </label>
                    <p className="text-xs text-muted-foreground">
                        Tous les rendez-vous de ce docteur à partir de cette heure (et les patients déjà arrivés en salle d'attente) sont décalés d'autant. Les
                        rendez-vous en séance ou terminés ne bougent pas.
                    </p>
                    <div className="flex justify-end gap-2">
                        <Button variant="ghost" onClick={() => setOuvert(false)}>
                            Annuler
                        </Button>
                        <Button
                            disabled={form.processing || !form.data.minutes || (!!docteurs && !form.data.docteur_id)}
                            onClick={() => form.post('/agenda/decaler', { preserveScroll: true, onSuccess: () => setOuvert(false) })}
                        >
                            Décaler de {form.data.minutes} min
                        </Button>
                    </div>
                </div>
            </Modal>
        </>
    );
}
