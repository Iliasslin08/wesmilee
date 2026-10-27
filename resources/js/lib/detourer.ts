/**
 * Enlève le fond d'une photo de patient (dans le navigateur, sans internet).
 * Modèle MediaPipe « selfie segmentation » servi depuis /public/mediapipe/selfie.
 */
type Segmenteur = {
    setOptions(o: { modelSelection: number }): void;
    onResults(cb: (r: { segmentationMask: CanvasImageSource }) => void): void;
    initialize(): Promise<void>;
    send(i: { image: HTMLCanvasElement }): Promise<void>;
};

let charge: Promise<Segmenteur> | null = null;

function segmenteur(): Promise<Segmenteur> {
    if (charge) return charge;
    charge = new Promise<Segmenteur>((ok, ko) => {
        const s = document.createElement('script');
        s.src = '/mediapipe/selfie/selfie_segmentation.js';
        s.onload = async () => {
            try {
                const Classe = (window as unknown as { SelfieSegmentation: new (o: { locateFile: (f: string) => string }) => Segmenteur }).SelfieSegmentation;
                const seg = new Classe({ locateFile: (f) => `/mediapipe/selfie/${f}` });
                seg.setOptions({ modelSelection: 0 });
                await seg.initialize();
                ok(seg);
            } catch (e) {
                ko(e);
            }
        };
        s.onerror = ko;
        document.head.appendChild(s);
    });
    charge.catch(() => (charge = null));
    return charge;
}

function chargerImage(f: File): Promise<HTMLImageElement> {
    return new Promise((ok, ko) => {
        const img = new Image();
        img.onload = () => ok(img);
        img.onerror = ko;
        img.src = URL.createObjectURL(f);
    });
}

/** Photo réduite (max px) ; fond enlevé si possible → PNG transparent, sinon JPEG normal. */
export async function photoSansFond(f: File, max = 900): Promise<File> {
    return (await detourer(f, max)).fichier;
}

export async function detourer(f: File, max = 900): Promise<{ fichier: File; ok: boolean }> {
    let img: HTMLImageElement;
    try {
        img = await chargerImage(f);
    } catch {
        return { fichier: f, ok: false };
    }
    const r = Math.min(1, max / Math.max(img.width, img.height));
    const c = document.createElement('canvas');
    c.width = Math.round(img.width * r);
    c.height = Math.round(img.height * r);
    const ctx = c.getContext('2d')!;
    ctx.drawImage(img, 0, 0, c.width, c.height);
    URL.revokeObjectURL(img.src);

    try {
        const seg = await segmenteur();
        const masque = await new Promise<CanvasImageSource>((ok) => {
            seg.onResults((res) => ok(res.segmentationMask));
            seg.send({ image: c });
        });
        const sortie = document.createElement('canvas');
        sortie.width = c.width;
        sortie.height = c.height;
        const o = sortie.getContext('2d')!;
        o.filter = 'blur(1.5px)'; // bords plus doux (cheveux, barbe)
        o.drawImage(masque, 0, 0, c.width, c.height);
        o.filter = 'none';
        o.globalCompositeOperation = 'source-in';
        o.drawImage(c, 0, 0);
        const b = await new Promise<Blob | null>((ok) => sortie.toBlob(ok, 'image/png'));
        if (b) return { fichier: new File([b], 'photo-carte.png', { type: 'image/png' }), ok: true };
    } catch (e) {
        console.error('Détourage impossible', e);
        // pas de détourage possible : on garde la photo normale
    }
    const b = await new Promise<Blob | null>((ok) => c.toBlob(ok, 'image/jpeg', 0.85));
    return { fichier: b ? new File([b], 'photo-carte.jpg', { type: 'image/jpeg' }) : f, ok: false };
}
