import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/** Déconnexion automatique après X minutes sans activité (souris, clavier, toucher). */
export function DeconnexionAuto() {
    const { reglages } = usePage().props as unknown as { reglages?: { inactiviteMinutes: number } };
    const minutes = reglages?.inactiviteMinutes ?? 0;
    const derniere = useRef(Date.now());
    const [reste, setReste] = useState<number | null>(null);

    useEffect(() => {
        if (!minutes) return;
        const activite = () => {
            derniere.current = Date.now();
            setReste(null);
        };
        const evts = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll'];
        evts.forEach((e) => window.addEventListener(e, activite, { passive: true }));
        const t = setInterval(() => {
            const inactif = (Date.now() - derniere.current) / 1000;
            const limite = minutes * 60;
            if (inactif >= limite) {
                clearInterval(t);
                router.post('/logout');
            } else if (limite - inactif <= 60) {
                setReste(Math.ceil(limite - inactif));
            }
        }, 1000);
        return () => {
            evts.forEach((e) => window.removeEventListener(e, activite));
            clearInterval(t);
        };
    }, [minutes]);

    if (reste === null) return null;
    return (
        <div className="fixed inset-x-0 bottom-4 z-50 mx-auto w-fit rounded-xl bg-slate-900 px-5 py-3 text-sm text-white shadow-2xl">
            Déconnexion automatique dans <b>{reste} s</b> (inactivité). Bougez la souris pour rester connecté.
        </div>
    );
}
