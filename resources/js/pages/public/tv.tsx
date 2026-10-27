import { Head, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Appel = { id: number; numero_appel: number; appele_le: string; patient: string | null; docteur: { name: string; salle: string | null } | null };
type Salle = { nom: string; docteur: string | null; patient: string | null; numero: number | null; etat: 'occupee' | 'libre' | 'fermee' };

/** Écran TV de la salle d'attente : se met à jour tout seul toutes les 4 secondes. */
export default function Tv({ appels, attente, salles = [], cabinet }: { appels: Appel[]; attente: number[]; salles?: Salle[]; cabinet: string }) {
    usePoll(4000, { only: ['appels', 'attente', 'salles'] });
    const [dernier, ...avant] = appels;
    const [heure, setHeure] = useState(() => new Date());
    useEffect(() => {
        const t = setInterval(() => setHeure(new Date()), 15000);
        return () => clearInterval(t);
    }, []);

    return (
        <div className="flex min-h-screen flex-col bg-[#f3f9f9] text-[#12303a]">
            <Head title="Écran salle d'attente" />
            <header className="flex items-center justify-between bg-white px-8 py-4 shadow-sm">
                <img src="/images/logo-wesmile.png" alt="We Smile" className="h-16 w-auto" />
                <span className="text-xl text-[#6b8189]">{cabinet}</span>
                <span className="text-4xl font-bold text-[#04a3b5]">{heure.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}</span>
            </header>

            <div className="grid flex-1 gap-6 p-8 lg:grid-cols-[1.1fr_1fr]">
                {/* Dernier patient appelé */}
                <div className="flex flex-col items-center justify-center rounded-3xl bg-gradient-to-br from-[#04a3b5] to-[#0b6f8a] p-8 text-center text-white shadow-xl">
                    {dernier ? (
                        <>
                            <div className="text-3xl opacity-90">Patient appelé</div>
                            <div className="my-2 text-[11vw] leading-none font-extrabold lg:text-[9vw]">N° {dernier.numero_appel}</div>
                            {dernier.patient && <div className="text-4xl font-bold">{dernier.patient}</div>}
                            <div className="mt-4 rounded-2xl bg-white/15 px-8 py-3 text-4xl font-bold text-[#d9f7b8]">
                                → {dernier.docteur?.salle ?? 'Cabinet'} · {dernier.docteur?.name}
                            </div>
                        </>
                    ) : (
                        <div className="text-4xl">Bienvenue, merci de patienter</div>
                    )}
                </div>

                {/* Toutes les salles */}
                <div className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        {salles.map((s) => (
                            <div
                                key={s.nom}
                                className={`rounded-2xl border-l-8 bg-white p-5 shadow ${s.etat === 'occupee' ? 'border-[#04a3b5]' : s.etat === 'libre' ? 'border-[#80bb4b]' : 'border-slate-300'}`}
                            >
                                <div className="flex items-center justify-between">
                                    <span className="text-3xl font-extrabold">{s.nom}</span>
                                    <span
                                        className={`rounded-full px-3 py-1 text-sm font-bold ${s.etat === 'occupee' ? 'bg-[#dff4f6] text-[#037786]' : s.etat === 'libre' ? 'bg-[#e7f4db] text-[#4c8a1f]' : 'bg-slate-100 text-slate-500'}`}
                                    >
                                        {s.etat === 'occupee' ? 'En soin' : s.etat === 'libre' ? 'Libre' : 'Fermée'}
                                    </span>
                                </div>
                                <div className="mt-2 text-2xl">
                                    {s.patient ? (
                                        <>
                                            <b>
                                                N° {s.numero} · {s.patient}
                                            </b>{' '}
                                            <span className="text-[#6b8189]">→ {s.docteur}</span>
                                        </>
                                    ) : (
                                        <span className="text-[#6b8189]">{s.docteur ?? '—'}</span>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                    <div className="rounded-2xl bg-white p-5 shadow">
                        <div className="mb-2 text-lg text-[#6b8189]">Prochains</div>
                        <div className="flex flex-wrap gap-3 text-3xl font-bold">
                            {attente.length ? attente.map((n) => (
                                <span key={n} className="rounded-xl bg-[#e7f4db] px-4 py-2 text-[#3f7a17]">
                                    N° {n}
                                </span>
                            )) : <span className="text-xl font-normal text-[#6b8189]">Personne en attente</span>}
                        </div>
                        {avant.length > 0 && (
                            <div className="mt-3 text-lg text-[#6b8189]">
                                Appels précédents : {avant.map((a) => `N° ${a.numero_appel} → ${a.docteur?.salle ?? a.docteur?.name}`).join(' · ')}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
