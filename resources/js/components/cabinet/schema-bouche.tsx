import { useMemo, useState } from "react";

/**
 * Schéma dentaire dessiné comme une bouche ouverte (vue de dessus).
 * Mâchoire du haut en haut, mâchoire du bas en bas, numérotation FDI.
 * La droite du patient est à gauche de l'écran (comme quand on regarde le patient).
 */

export const COULEURS_DENT: Record<
    string,
    { fill: string; stroke: string; texte: string }
> = {
    saine: { fill: "#ffffff", stroke: "#94a3b8", texte: "Saine" },
    carie: { fill: "#ef4444", stroke: "#b91c1c", texte: "Carie" },
    a_surveiller: { fill: "#fcd34d", stroke: "#d97706", texte: "À surveiller" },
    traitee: { fill: "#38bdf8", stroke: "#0369a1", texte: "Traitée" },
    couronne: { fill: "#14b8a6", stroke: "#0f766e", texte: "Couronne" },
    implant: { fill: "#8b5cf6", stroke: "#6d28d9", texte: "Implant" },
    absente: { fill: "#f1f5f9", stroke: "#94a3b8", texte: "Absente" },
};

type TypeDent = "incisive" | "canine" | "premolaire" | "molaire";

// Largeur relative de chaque dent (position 1 = incisive centrale)
const ADULTE: { pos: number; type: TypeDent; largeur: number }[] = [
    { pos: 1, type: "incisive", largeur: 8.6 },
    { pos: 2, type: "incisive", largeur: 6.8 },
    { pos: 3, type: "canine", largeur: 7.8 },
    { pos: 4, type: "premolaire", largeur: 7.2 },
    { pos: 5, type: "premolaire", largeur: 7 },
    { pos: 6, type: "molaire", largeur: 10.4 },
    { pos: 7, type: "molaire", largeur: 9.6 },
    { pos: 8, type: "molaire", largeur: 9 },
];
const LAIT: { pos: number; type: TypeDent; largeur: number }[] = [
    { pos: 1, type: "incisive", largeur: 6.5 },
    { pos: 2, type: "incisive", largeur: 5.2 },
    { pos: 3, type: "canine", largeur: 6.8 },
    { pos: 4, type: "molaire", largeur: 7.5 },
    { pos: 5, type: "molaire", largeur: 9.2 },
];

type Place = {
    num: number;
    x: number;
    y: number;
    angle: number;
    w: number;
    type: TypeDent;
    lx: number;
    ly: number;
};

const CX = 300;

/** Place les dents d'une arcade le long d'une demi-ellipse, proportionnellement à leur largeur. */
function arcade(haut: boolean, enfant: boolean, interne = false): Place[] {
    const dents = enfant ? LAIT : ADULTE;
    // interne = dents de lait dessinées à l'intérieur des dents adultes (denture mixte)
    const rx = interne ? 116 : enfant ? 150 : 190;
    const ry = interne ? 128 : enfant ? 170 : 215;
    const cy = haut ? 300 : 340;
    const tFront = haut ? -Math.PI / 2 : Math.PI / 2; // point avant de l'arcade
    const ouverture = (interne ? 76 : enfant ? 78 : 84) * (Math.PI / 180); // angle couvert de chaque côté

    // Échantillonnage de la demi-arcade pour placer les dents selon la longueur d'arc
    const N = 400;
    const pts: { t: number; s: number }[] = [];
    let s = 0;
    let prev = { x: CX + rx * Math.cos(tFront), y: cy + ry * Math.sin(tFront) };
    for (let i = 0; i <= N; i++) {
        const t = tFront - (haut ? 1 : -1) * ouverture * (i / N);
        const p = { x: CX + rx * Math.cos(t), y: cy + ry * Math.sin(t) };
        s += Math.hypot(p.x - prev.x, p.y - prev.y);
        pts.push({ t, s });
        prev = p;
    }
    const total = pts[N].s;
    const somme = dents.reduce((a, d) => a + d.largeur, 0);
    const echelle = total / somme;

    const places: Place[] = [];
    // côté droit du patient = gauche de l'écran ; quadrants FDI
    const quadrants = haut
        ? enfant
            ? [5, 6]
            : [1, 2]
        : enfant
          ? [8, 7]
          : [4, 3];

    quadrants.forEach((q, cote) => {
        let cumul = 0;
        dents.forEach((d) => {
            const centre = (cumul + d.largeur / 2) * echelle;
            cumul += d.largeur;
            const k = pts.findIndex((p) => p.s >= centre);
            let t = pts[Math.max(k, 0)].t;
            if (cote === 1) t = 2 * tFront - t; // symétrie gauche / droite
            const x0 = CX + rx * Math.cos(t);
            const y0 = cy + ry * Math.sin(t);
            // normale extérieure à l'ellipse
            let nx = Math.cos(t) / rx;
            let ny = Math.sin(t) / ry;
            const n = Math.hypot(nx, ny);
            nx /= n;
            ny /= n;
            const angle = (Math.atan2(nx, -ny) * 180) / Math.PI;
            const w = d.largeur * echelle * (interne ? 0.86 : 0.92);
            const sens = interne ? -1 : 1; // numéros vers l'intérieur pour les dents de lait en denture mixte
            places.push({
                num: q * 10 + d.pos,
                x: x0,
                y: y0,
                angle,
                w,
                type: d.type,
                lx: x0 + sens * nx * (w * 0.55 + (interne ? 12 : 16)),
                ly: y0 + sens * ny * (w * 0.55 + (interne ? 12 : 16)),
            });
        });
    });

    // Le quadrant 1/4/5/8 (droite du patient) doit être à gauche de l'écran
    return places.map((p) => {
        const q = Math.floor(p.num / 10);
        const droitePatient = [1, 4, 5, 8].includes(q);
        const aGauche = p.x < CX;
        if (droitePatient === aGauche) return p;
        return { ...p, x: 2 * CX - p.x, lx: 2 * CX - p.lx, angle: -p.angle };
    });
}

/** Forme d'une dent vue de dessus, centrée sur (0,0), face extérieure vers le haut. */
function FormeDent({
    type,
    w,
    fill,
    stroke,
    absente,
}: {
    type: TypeDent;
    w: number;
    fill: string;
    stroke: string;
    absente: boolean;
}) {
    const common = {
        fill,
        stroke,
        strokeWidth: 1.6,
        strokeDasharray: absente ? "4 3" : undefined,
    };
    const detail = {
        fill: "none",
        stroke: absente ? "transparent" : "rgba(15,23,42,0.28)",
        strokeWidth: 1.1,
        strokeLinecap: "round" as const,
    };
    if (type === "incisive") {
        const h = w * 0.62;
        return (
            <g>
                <path
                    d={`M ${-w / 2} ${-h * 0.2} Q ${-w / 2} ${-h / 2} 0 ${-h / 2} Q ${w / 2} ${-h / 2} ${w / 2} ${-h * 0.2} Q ${w / 2.4} ${h / 2} 0 ${h / 2} Q ${-w / 2.4} ${h / 2} ${-w / 2} ${-h * 0.2} Z`}
                    {...common}
                />
                <path
                    d={`M ${-w * 0.3} ${-h * 0.18} Q 0 ${-h * 0.3} ${w * 0.3} ${-h * 0.18}`}
                    {...detail}
                />
            </g>
        );
    }
    if (type === "canine") {
        const h = w * 0.95;
        return (
            <g>
                <path
                    d={`M 0 ${-h / 2} Q ${w / 2} ${-h / 3} ${w / 2} 0 Q ${w / 2.3} ${h / 2} 0 ${h / 2} Q ${-w / 2.3} ${h / 2} ${-w / 2} 0 Q ${-w / 2} ${-h / 3} 0 ${-h / 2} Z`}
                    {...common}
                />
                <path d={`M 0 ${-h * 0.25} L 0 ${h * 0.2}`} {...detail} />
            </g>
        );
    }
    if (type === "premolaire") {
        const h = w * 1.2;
        return (
            <g>
                <ellipse cx={0} cy={0} rx={w / 2} ry={h / 2} {...common} />
                <path
                    d={`M ${-w * 0.28} 0 Q 0 ${h * 0.08} ${w * 0.28} 0`}
                    {...detail}
                />
            </g>
        );
    }
    // molaire
    const h = w * 1.02;
    const r = w * 0.28;
    return (
        <g>
            <rect
                x={-w / 2}
                y={-h / 2}
                width={w}
                height={h}
                rx={r}
                {...common}
            />
            <path
                d={`M ${-w * 0.32} ${-h * 0.02} Q 0 ${h * 0.1} ${w * 0.32} ${-h * 0.02} M 0 ${-h * 0.3} L 0 ${h * 0.32}`}
                {...detail}
            />
        </g>
    );
}

export type ModeDents = "adulte" | "enfant" | "mixte";

/** Mode d'affichage conseillé selon les dents déjà notées (et l'âge si rien n'est noté). */
export function modeDents(dents: Record<string, string>, age?: number | null): ModeDents {
    const nums = Object.keys(dents).map(Number);
    const lait = nums.some((d) => d > 50);
    const adulte = nums.some((d) => d < 50);
    if (lait && adulte) return "mixte";
    if (lait) return "enfant";
    if (adulte) return "adulte";
    if (age != null && age < 6) return "enfant";
    if (age != null && age <= 12) return "mixte";
    return "adulte";
}

export function SchemaBouche({
    dents,
    enfant: enfantProp = false,
    mode: modeProp,
    modifiable,
    onClic,
    compact = false,
}: {
    dents: Record<string, string>;
    enfant?: boolean;
    mode?: ModeDents;
    modifiable: boolean;
    onClic: (num: number) => void;
    compact?: boolean;
}) {
    const mode: ModeDents = modeProp ?? (enfantProp ? "enfant" : "adulte");
    const enfant = mode === "enfant";
    const mixte = mode === "mixte";
    const [survol, setSurvol] = useState<number | null>(null);
    const haut = useMemo(() => arcade(true, enfant), [enfant]);
    const bas = useMemo(() => arcade(false, enfant), [enfant]);
    const lait = useMemo(() => (mixte ? [...arcade(true, true, true), ...arcade(false, true, true)] : []), [mixte]);

    const arcGencive = (h: boolean) => {
        const rx = enfant ? 150 : 190;
        const ry = enfant ? 170 : 215;
        const cy = h ? 300 : 340;
        const a = ((enfant ? 84 : 90) * Math.PI) / 180;
        const tF = h ? -Math.PI / 2 : Math.PI / 2;
        const p = (t: number) =>
            `${CX + rx * Math.cos(t)} ${cy + ry * Math.sin(t)}`;
        return `M ${p(tF - a)} A ${rx} ${ry} 0 0 1 ${p(tF + a)}`;
    };

    const dent = (p: Place) => {
        const etat = dents[p.num] ?? "saine";
        const c = COULEURS_DENT[etat] ?? COULEURS_DENT.saine;
        const actif = survol === p.num;
        return (
            <g
                key={p.num}
                onClick={() => modifiable && onClic(p.num)}
                onMouseEnter={() => setSurvol(p.num)}
                onMouseLeave={() => setSurvol(null)}
                className={modifiable ? "cursor-pointer" : undefined}
                role={modifiable ? "button" : undefined}
                aria-label={`Dent ${p.num} : ${c.texte}`}
            >
                <g
                    transform={`translate(${p.x} ${p.y}) rotate(${p.angle}) scale(${actif && modifiable ? 1.12 : 1})`}
                >
                    {/* zone de clic plus large (tablette) */}
                    <circle r={p.w * 0.8} cy={-p.w * 0.25} fill="transparent" />
                    <FormeDent
                        type={p.type}
                        w={p.w}
                        fill={c.fill}
                        stroke={actif ? "#0f172a" : c.stroke}
                        absente={etat === "absente"}
                    />
                    {etat === "implant" && (
                        <circle
                            r={p.w * 0.14}
                            fill="#fff"
                            stroke="#6d28d9"
                            strokeWidth={1.2}
                        />
                    )}
                    {etat === "absente" && (
                        <path
                            d={`M ${-p.w * 0.25} ${-p.w * 0.25} L ${p.w * 0.25} ${p.w * 0.25} M ${p.w * 0.25} ${-p.w * 0.25} L ${-p.w * 0.25} ${p.w * 0.25}`}
                            stroke="#94a3b8"
                            strokeWidth={1.5}
                        />
                    )}
                </g>
                <text
                    x={p.lx}
                    y={p.ly}
                    textAnchor="middle"
                    dominantBaseline="central"
                    fontSize={mixte && p.num > 50 ? 10.5 : 13}
                    fontWeight={etat !== "saine" || actif ? 700 : 500}
                    fill={etat !== "saine" ? c.stroke : "currentColor"}
                    className="select-none"
                >
                    {p.num}
                </text>
                <title>{`Dent ${p.num} · ${c.texte}`}</title>
            </g>
        );
    };

    const notees = Object.entries(dents).filter(([, e]) => e && e !== "saine");

    return (
        <div className="flex flex-col items-center gap-3">
            <svg
                viewBox="0 20 600 600"
                className="w-full max-w-[560px] text-foreground"
                style={{ touchAction: "manipulation" }}
            >
                <g pointerEvents="none">
                    {/* gencives */}
                    <path
                        d={arcGencive(true)}
                        fill="none"
                        stroke="#f9a8d4"
                        strokeOpacity={0.35}
                        strokeWidth={enfant ? 58 : 66}
                        strokeLinecap="round"
                    />
                    <path
                        d={arcGencive(false)}
                        fill="none"
                        stroke="#f9a8d4"
                        strokeOpacity={0.35}
                        strokeWidth={enfant ? 58 : 66}
                        strokeLinecap="round"
                    />
                    {/* palais et langue */}
                    {!mixte && (
                        <>
                            <ellipse
                                cx={CX}
                                cy={enfant ? 250 : 225}
                                rx={enfant ? 80 : 105}
                                ry={enfant ? 55 : 70}
                                fill="#fce7f3"
                                fillOpacity={0.35}
                            />
                            <ellipse
                                cx={CX}
                                cy={enfant ? 390 : 415}
                                rx={enfant ? 78 : 100}
                                ry={enfant ? 55 : 72}
                                fill="#fda4af"
                                fillOpacity={0.22}
                            />
                        </>
                    )}
                    {mixte && (
                        <>
                            <path d={`M ${CX - 104} 268 A 116 128 0 0 1 ${CX + 104} 268`} fill="none" stroke="#fbcfe8" strokeOpacity={0.45} strokeWidth={34} strokeLinecap="round" />
                            <path d={`M ${CX - 104} 372 A 116 128 0 0 0 ${CX + 104} 372`} fill="none" stroke="#fbcfe8" strokeOpacity={0.45} strokeWidth={34} strokeLinecap="round" />
                            <text x={CX} y={300} textAnchor="middle" fontSize={11.5} fill="currentColor" opacity={0.55}>
                                Dents de lait (intérieur) · dents définitives (extérieur)
                            </text>
                        </>
                    )}
                    <text
                        x={CX}
                        y={mixte ? 262 : enfant ? 250 : 225}
                        textAnchor="middle"
                        fontSize={13}
                        fill="currentColor"
                        opacity={0.55}
                    >
                        Mâchoire du haut
                    </text>
                    <text
                        x={CX}
                        y={mixte ? 346 : enfant ? 392 : 418}
                        textAnchor="middle"
                        fontSize={13}
                        fill="currentColor"
                        opacity={0.55}
                    >
                        Mâchoire du bas
                    </text>
                    <line
                        x1={CX}
                        y1={40}
                        x2={CX}
                        y2={600}
                        stroke="currentColor"
                        strokeOpacity={0.12}
                        strokeDasharray="4 6"
                    />
                    <text
                        x={24}
                        y={320}
                        fontSize={12}
                        fill="currentColor"
                        opacity={0.6}
                    >
                        Droite
                    </text>
                    <text
                        x={576}
                        y={320}
                        fontSize={12}
                        textAnchor="end"
                        fill="currentColor"
                        opacity={0.6}
                    >
                        Gauche
                    </text>
                </g>
                {haut.map(dent)}
                {bas.map(dent)}
                {lait.map(dent)}
            </svg>

            {!compact && (<div className="flex flex-wrap justify-center gap-3 text-xs">
                {Object.entries(COULEURS_DENT).map(([k, c]) => (
                    <span key={k} className="flex items-center gap-1">
                        <span
                            className="inline-block size-3 rounded-sm border"
                            style={{
                                background: c.fill,
                                borderColor: c.stroke,
                            }}
                        />
                        {c.texte}
                    </span>
                ))}
            </div>)}
            {!compact && notees.length > 0 && (
                <p className="text-center text-sm">
                    <span className="text-muted-foreground">
                        Dents notées :{" "}
                    </span>
                    {notees
                        .sort(([a], [b]) => Number(a) - Number(b))
                        .map(
                            ([d, e]) =>
                                `${d} ${COULEURS_DENT[e]?.texte.toLowerCase() ?? e}`,
                        )
                        .join(" · ")}
                </p>
            )}
        </div>
    );
}
