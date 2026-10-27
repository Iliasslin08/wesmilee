import { useForm } from '@inertiajs/react';
import { ScanLine } from 'lucide-react';
import { Field, inputCls, textareaCls } from '@/components/cabinet/ui';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/** Formulaire de la radiologie : fichiers + mesures. À l'envoi, la radio va dans le dossier et au labo. */
export function RadioForm({ dossierId, types, onDone, typeDefaut, demandeId }: { dossierId: number; types: Record<string, string>; onDone?: () => void; typeDefaut?: string; demandeId?: number }) {
    const form = useForm<{ type: string; dents: string; mesures: string; notes: string; fichiers: File[]; demande_id: number | null }>({
        type: typeDefaut ?? Object.keys(types)[0] ?? 'panoramique',
        demande_id: demandeId ?? null,
        dents: '',
        mesures: '',
        notes: '',
        fichiers: [],
    });
    const errs = form.errors as Record<string, string>;
    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap gap-2">
                {Object.entries(types).map(([k, v]) => (
                    <button
                        key={k}
                        type="button"
                        onClick={() => form.setData('type', k)}
                        className={cn('rounded-full border px-3 py-1 text-sm', form.data.type === k ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted')}
                    >
                        {v}
                    </button>
                ))}
            </div>
            <label className="flex cursor-pointer flex-col items-center gap-1 rounded-xl border-2 border-dashed border-teal-300 bg-teal-50/40 p-5 text-center text-sm hover:bg-teal-50 dark:bg-teal-950/20">
                <ScanLine className="size-7 text-teal-700" />
                <span className="font-semibold">{form.data.fichiers.length ? `${form.data.fichiers.length} photo(s) de la radio prête(s)` : 'Ajouter la photo de la radio (appareil photo ou fichier)'}</span>
                <span className="text-muted-foreground">{form.data.fichiers.map((f) => f.name).join(', ') || 'Le labo verra cette photo avec les mesures'}</span>
                <input type="file" multiple accept="image/*,application/pdf,.dcm,.stl,.zip" className="hidden" onChange={(e) => form.setData('fichiers', Array.from(e.target.files ?? []))} />
            </label>
            {(errs.fichiers || errs['fichiers.0']) && <p className="text-sm text-red-600">{errs.fichiers || errs['fichiers.0']}</p>}
            <div className="grid gap-3 md:grid-cols-[1fr_2fr]">
                <Field label="Dent(s)">
                    <input className={inputCls} placeholder="16, 26" value={form.data.dents} onChange={(e) => form.setData('dents', e.target.value)} />
                </Field>
                <Field label="Mesures / taille pour le labo" error={form.errors.mesures}>
                    <input className={inputCls} placeholder="ex. largeur 10,5 mm · hauteur 8 mm · implant Ø 4,1 × 10 mm" value={form.data.mesures} onChange={(e) => form.setData('mesures', e.target.value)} />
                </Field>
            </div>
            <Field label="Remarques">
                <textarea className={textareaCls} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
            </Field>
            <Button
                disabled={form.data.fichiers.length === 0 || form.processing}
                onClick={() =>
                    form.post(`/dossiers/${dossierId}/radios`, {
                        forceFormData: true,
                        preserveScroll: true,
                        onSuccess: () => {
                            form.reset();
                            onDone?.();
                        },
                    })
                }
            >
                Envoyer la photo au labo
            </Button>
        </div>
    );
}
