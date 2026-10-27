import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function Page({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('flex flex-1 flex-col gap-4 p-4 md:p-6', className)}>{children}</div>;
}

export function PageTitle({ titre, sousTitre, actions }: { titre: ReactNode; sousTitre?: ReactNode; actions?: ReactNode }) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 className="text-xl font-semibold text-teal-900 md:text-2xl dark:text-teal-100">{titre}</h1>
                {sousTitre && <p className="mt-1 text-sm text-muted-foreground">{sousTitre}</p>}
            </div>
            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </div>
    );
}

export function Box({ children, className, titre, actions }: { children: ReactNode; className?: string; titre?: ReactNode; actions?: ReactNode }) {
    return (
        <section className={cn('rounded-xl border bg-card p-4 shadow-xs', className)}>
            {(titre || actions) && (
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    {titre && <h2 className="font-semibold text-teal-900 dark:text-teal-100">{titre}</h2>}
                    {actions}
                </div>
            )}
            {children}
        </section>
    );
}

export function Pill({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap', className)}>{children}</span>
    );
}

export function Field({ label, error, children, className }: { label: string; error?: string; children: ReactNode; className?: string }) {
    return (
        <div className={cn('flex flex-col gap-1 text-sm', className)}>
            <span className="font-medium text-muted-foreground">{label}</span>
            {children}
            {error && <span className="text-xs text-red-600">{error}</span>}
        </div>
    );
}

export const inputCls =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40';
export const textareaCls =
    'min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40';

export function Empty({ children }: { children: ReactNode }) {
    return <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">{children}</p>;
}

export function Tabs<T extends string>({ value, onChange, items }: { value: T; onChange: (v: T) => void; items: { value: T; label: ReactNode }[] }) {
    return (
        <div className="-mx-1 flex gap-1 overflow-x-auto border-b px-1">
            {items.map((it) => (
                <button
                    key={it.value}
                    type="button"
                    onClick={() => onChange(it.value)}
                    className={cn(
                        '-mb-px shrink-0 border-b-2 px-3 py-2 text-sm font-semibold whitespace-nowrap transition-colors',
                        value === it.value ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {it.label}
                </button>
            ))}
        </div>
    );
}

export function Modal({
    open,
    onClose,
    titre,
    children,
    large,
    xl,
}: {
    open: boolean;
    onClose: () => void;
    titre: string;
    children: ReactNode;
    large?: boolean;
    xl?: boolean;
}) {
    if (!open) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 md:items-center" onClick={onClose}>
            <div
                className={cn('w-full rounded-xl bg-background p-5 shadow-xl', xl ? 'max-w-[1400px]' : large ? 'max-w-3xl' : 'max-w-lg')}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-semibold">{titre}</h3>
                    <button type="button" onClick={onClose} className="text-2xl leading-none text-muted-foreground" aria-label="Fermer">
                        ×
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}
