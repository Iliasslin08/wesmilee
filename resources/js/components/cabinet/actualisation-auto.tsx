import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

/** Pages qui s'actualisent déjà seules plus souvent. */
const DEJA = ['reception/salle-attente', 'docteur/patients', 'radio/index', 'labo/index', 'public/tv'];

/**
 * Actualisation automatique de toute la plateforme : chaque page se met à jour seule
 * toutes les 20 s (et dès qu'on revient sur l'onglet), sans F5 et sans perdre ce qu'on tape.
 */
export function ActualisationAuto({ secondes = 20 }: { secondes?: number }) {
    const { component } = usePage();

    useEffect(() => {
        if (DEJA.includes(component)) return;
        const actualiser = () => {
            if (document.visibilityState === 'visible') router.reload({ async: true });
        };
        const t = window.setInterval(actualiser, secondes * 1000);
        window.addEventListener('focus', actualiser);
        return () => {
            window.clearInterval(t);
            window.removeEventListener('focus', actualiser);
        };
    }, [component, secondes]);

    return null;
}
