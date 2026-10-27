import { useEffect, useState } from 'react';
import { inputCls } from '@/components/cabinet/ui';
import type { Patient } from '@/lib/cabinet';

/** Recherche d'un patient par nom, téléphone, CIN ou n° de dossier. */
export function PatientSearch({ value, onSelect }: { value?: Patient | null; onSelect: (p: Patient | null) => void }) {
    const [q, setQ] = useState('');
    const [res, setRes] = useState<Patient[]>([]);

    useEffect(() => {
        if (q.trim().length < 2) {
            setRes([]);
            return;
        }
        const ctrl = new AbortController();
        const t = setTimeout(() => {
            fetch(`/recherche-patients?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' }, signal: ctrl.signal })
                .then((r) => r.json())
                .then((d: Patient[]) => setRes(d))
                .catch(() => undefined);
        }, 250);
        return () => {
            clearTimeout(t);
            ctrl.abort();
        };
    }, [q]);

    if (value) {
        return (
            <div className="flex items-center justify-between rounded-md border bg-teal-50 px-3 py-2 text-sm dark:bg-teal-950">
                <span>
                    <b>{value.nom_complet}</b> {value.dossier && <span className="text-muted-foreground">· Dossier {value.dossier.numero}</span>}
                </span>
                <button type="button" className="text-xs text-primary underline" onClick={() => onSelect(null)}>
                    Changer
                </button>
            </div>
        );
    }

    return (
        <div className="relative">
            <input className={inputCls} placeholder="Nom, téléphone, CIN ou n° de dossier…" value={q} onChange={(e) => setQ(e.target.value)} autoFocus />
            {res.length > 0 && (
                <ul className="absolute z-10 mt-1 max-h-64 w-full overflow-auto rounded-md border bg-background shadow-lg">
                    {res.map((p) => (
                        <li key={p.id}>
                            <button
                                type="button"
                                className="w-full px-3 py-2 text-left text-sm hover:bg-accent"
                                onClick={() => {
                                    onSelect(p);
                                    setQ('');
                                    setRes([]);
                                }}
                            >
                                <b>{p.nom_complet}</b>
                                <span className="text-muted-foreground">
                                    {' '}
                                    · {p.telephone ?? '—'} {p.ville ? `· ${p.ville}` : ''} {p.dossier ? `· Dossier ${p.dossier.numero}` : ''}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
