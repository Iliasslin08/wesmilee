/** Membre de l'équipe du labo, avec sa mission. */
export type MembreLabo = { id: number; name: string; taches_principales: string | null; taches_secondaires: string | null };

const sansAccent = (t: string) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

/** Mission du labo qui correspond au travail demandé. */
export function missionPour(travail: string): 'cad' | 'ceram' | 'prothes' {
    const t = sansAccent(travail);
    if (/zircon|cad|cam|usin|fraisa|scan|numeri/.test(t)) return 'cad';
    if (/ceram|e\.?max|facette|stratif|maquill|hollywood/.test(t)) return 'ceram';
    return 'prothes';
}

/** Ce membre est-il conseillé pour ce travail (selon sa mission) ? */
export function conseille(m: MembreLabo, travail: string): boolean {
    if (!travail.trim()) return false;
    const taches = sansAccent(`${m.taches_principales ?? ''} ${m.taches_secondaires ?? ''}`);
    return taches.includes(missionPour(travail));
}

export const mission = (m: MembreLabo) => [m.taches_principales, m.taches_secondaires].filter(Boolean).join(' · ');
