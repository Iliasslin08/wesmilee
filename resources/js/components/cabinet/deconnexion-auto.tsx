import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const CLE = 'wesmile-derniere-activite';
const lire = () => {
    try {
        return Number(localStorage.getItem(CLE)) || 0;
    } catch {
        return 0;
    }
};
const ecrire = (t: number) => {
    try {
        localStorage.setItem(CLE, String(t));
    } catch {
        /* navigation privée : on garde seulement l'onglet */
    }
};

/**
 * Déconnexion automatique après X minutes sans activité (souris, clavier, toucher).
 * L'activité est partagée entre tous les onglets : un onglet oublié en arrière-plan
 * ne déconnecte plus la personne qui travaille dans un autre onglet.
 */
export function DeconnexionAuto() {
    const { reglages } = usePage().props as unknown as { reglages?: { inactiviteMinutes: number } };
    const minutes = reglages?.inactiviteMinutes ?? 0;
    const derniere = useRef(Date.now());
    const [reste, setReste] = useState<number | null>(null);

    useEffect(() => {
        if (!minutes) return;
        derniere.current = Date.now();
        ecrire(derniere.current);
        let dernierEnvoi = 0;
        const activite = () => {
            const t = Date.now();
            derniere.current = t;
            setReste(null);
            if (t - dernierEnvoi > 5000) {
                dernierEnvoi = t;
                ecrire(t);
            }
        };
        const evts = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'focus'];
        evts.forEach((e) => window.addEventListener(e, activite, { passive: true }));
        const t = setInterval(() => {
            const derniereActivite = Math.max(derniere.current, lire());
            const inactif = (Date.now() - derniereActivite) / 1000;
            const limite = minutes * 60;
            if (inactif >= limite) {
                clearInterval(t);
                router.post('/logout');
            } else if (limite - inactif <= 60) {
                setReste(Math.ceil(limite - inactif));
            } else {
                setReste(null);
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
