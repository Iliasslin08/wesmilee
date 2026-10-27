export const dh = (n: number | string | null | undefined) =>
    `${Number(n ?? 0).toLocaleString('fr-FR', { maximumFractionDigits: 2 })} DH`;

export const dateFr = (d?: string | null) =>
    d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—';

export const heure = (d?: string | null) =>
    d ? new Date(d).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '';

export const dateHeure = (d?: string | null) => (d ? `${dateFr(d)} ${heure(d)}` : '—');

export const depuis = (d?: string | null) => {
    if (!d) return '';
    const min = Math.max(0, Math.round((Date.now() - new Date(d).getTime()) / 60000));
    return min < 60 ? `${min} min` : `${Math.floor(min / 60)} h ${min % 60} min`;
};

export type Patient = {
    id: number;
    nom: string;
    prenom: string;
    nom_complet: string;
    age?: number | null;
    ville?: string | null;
    telephone?: string | null;
    cin?: string | null;
    allergies?: string | null;
    vient_de_loin?: boolean;
    fiche_a_completer?: boolean;
    dossier?: { id: number; numero: string } | null;
    [k: string]: unknown;
};

export const STATUT_RDV: Record<string, { label: string; cls: string }> = {
    planifie: { label: 'Planifié', cls: 'bg-slate-100 text-slate-700' },
    arrive: { label: 'En salle d’attente', cls: 'bg-amber-100 text-amber-800' },
    en_cours: { label: 'En séance', cls: 'bg-teal-100 text-teal-800' },
    termine: { label: 'Terminé', cls: 'bg-green-100 text-green-800' },
    annule: { label: 'Annulé', cls: 'bg-slate-100 text-slate-400 line-through' },
    absent: { label: 'Absent', cls: 'bg-red-100 text-red-700' },
};

export const ETAT_DOCTEUR: Record<string, { label: string; cls: string; dot: string }> = {
    pret: { label: 'Prêt', cls: 'bg-green-100 text-green-800', dot: 'bg-green-500' },
    occupe: { label: 'Occupé', cls: 'bg-red-100 text-red-700', dot: 'bg-red-500' },
    pause: { label: 'Pause', cls: 'bg-amber-100 text-amber-800', dot: 'bg-amber-500' },
    absent: { label: 'Absent', cls: 'bg-slate-100 text-slate-500', dot: 'bg-slate-400' },
};

export const PRIORITE_LABO: Record<string, { label: string; cls: string }> = {
    urgent: { label: 'Urgent', cls: 'bg-red-100 text-red-700' },
    bientot: { label: 'Bientôt', cls: 'bg-orange-100 text-orange-700' },
    normal: { label: 'Normal', cls: 'bg-green-100 text-green-800' },
    en_attente: { label: 'Sans RDV de pose', cls: 'bg-slate-100 text-slate-500' },
    termine: { label: 'Prêt', cls: 'bg-teal-100 text-teal-800' },
};

export const STATUT_ACTE: Record<string, string> = {
    a_faire: 'À faire',
    en_cours: 'En cours',
    fait: 'Fait',
    annule: 'Annulé',
};

export const METHODES: Record<string, string> = {
    especes: 'Espèces',
    carte: 'Carte',
    cheque: 'Chèque',
    virement: 'Virement',
};
