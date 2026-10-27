/**
 * Quel docteur fait habituellement quel soin.
 * On compare le soin demandé avec la liste du docteur par familles de mots (synonymes).
 * Ce n'est qu'un conseil : un autre docteur reste toujours possible (exception).
 */
export type DocSoins = {
    id: number;
    name: string;
    specialite?: string | null;
    taches_principales?: string | null;
    fait_consultation?: boolean;
    duree_rdv?: number | null;
};

const FAMILLES: [string, RegExp][] = [
    ['consultation', /consult|control|premiere|1re|bilan|examen|urgence/],
    ['extraction', /extract|avulsion|dent de sagesse/],
    ['implant', /implant|all on|greffe|sinus|pilier/],
    ['chirurgie', /chirurg|greffe|sinus|dent de sagesse|kyste|exerese|curetage|maxillo/],
    ['endo', /endo|canal|devital|pulp/],
    ['soin', /soin|carie|composite|obturation|restaur/],
    ['detartrage', /detartr|surfacage|paro|gencive/],
    ['prothese', /prothe|couronne|bridge|pilier|inlay|onlay|dentier|stellite|all on/],
    ['ortho', /ortho|odf|appareil|goutti|aligneur|bague/],
    ['esthetique', /esthet|facette|blanchi|eclairci|hollywood/],
];

const norm = (s: string) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

export const familles = (texte: string) => {
    const t = norm(texte);
    return new Set(FAMILLES.filter(([, re]) => re.test(t)).map(([f]) => f));
};

/** Le docteur fait-il habituellement ce soin ? null = soin non reconnu (on ne dit rien). */
export function faitCeSoin(d: DocSoins, soin: string): boolean | null {
    const demande = familles(soin);
    if (demande.size === 0) return null;
    const siens = familles(`${d.taches_principales ?? ''} ${d.specialite ?? ''}`);
    if (d.fait_consultation) siens.add('consultation');
    return [...demande].some((f) => siens.has(f));
}

export const conseilles = <T extends DocSoins>(docteurs: T[], soin: string): T[] => docteurs.filter((d) => faitCeSoin(d, soin) === true);
