import { Head, useForm, usePoll } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { Field, inputCls, textareaCls } from '@/components/cabinet/ui';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Langue = 'fr' | 'ar';

/** Textes de la fiche en français et en arabe (le patient choisit sa langue). */
const T = {
    fr: {
        titre: 'Bienvenue au Wesmile Center',
        intro: 'Merci de remplir votre fiche. La réception la vérifiera avec vous.',
        identite: '1 · Votre identité',
        nom: 'Nom *',
        prenom: 'Prénom *',
        naissance: 'Date de naissance',
        sexe: 'Sexe',
        femme: 'Femme',
        homme: 'Homme',
        telephone: 'Téléphone *',
        ville: 'Ville',
        profession: 'Profession',
        adresse: 'Adresse',
        sante: '2 · Votre santé',
        cochez: 'Cochez ce qui vous concerne :',
        allergies: 'Allergies (médicaments, latex…)',
        aucune: 'Aucune',
        traitements: 'Médicaments pris actuellement',
        autre: 'Autre chose à signaler',
        confidentiel: 'Vos données restent confidentielles au sein du centre.',
        envoyer: 'Envoyer ma fiche',
        bonjour: 'Bonjour',
        completer: 'votre rendez-vous est enregistré. Merci de compléter votre fiche.',
        antecedents: {
            diabete: 'Diabète',
            hypertension: 'Hypertension',
            cardiaque: 'Maladie cardiaque',
            anticoagulants: 'Anticoagulants',
            asthme: 'Asthme',
            enceinte: 'Enceinte',
            fumeur: 'Fumeur',
        } as Record<string, string>,
    },
    ar: {
        titre: 'مرحبا بكم في مركز Wesmile لطب الأسنان',
        intro: 'المرجو ملء هذه البطاقة. سيتحقق منها موظف الاستقبال معكم.',
        identite: '١ · معلوماتكم الشخصية',
        nom: 'الاسم العائلي *',
        prenom: 'الاسم الشخصي *',
        naissance: 'تاريخ الازدياد',
        sexe: 'الجنس',
        femme: 'أنثى',
        homme: 'ذكر',
        telephone: 'رقم الهاتف *',
        ville: 'المدينة',
        profession: 'المهنة',
        adresse: 'العنوان',
        sante: '٢ · حالتكم الصحية',
        cochez: 'اختاروا ما ينطبق عليكم:',
        allergies: 'الحساسية (أدوية، لاتكس…)',
        aucune: 'لا شيء',
        traitements: 'الأدوية التي تتناولونها حاليا',
        autre: 'ملاحظات أخرى',
        confidentiel: 'معطياتكم تبقى سرية داخل المركز.',
        envoyer: 'إرسال البطاقة',
        bonjour: 'مرحبا',
        completer: 'موعدكم مسجل. المرجو إتمام بطاقتكم.',
        antecedents: {
            diabete: 'السكري',
            hypertension: 'ارتفاع ضغط الدم',
            cardiaque: 'مرض في القلب',
            anticoagulants: 'أدوية مميعة للدم',
            asthme: 'الربو',
            enceinte: 'حامل',
            fumeur: 'مدخن',
        } as Record<string, string>,
    },
};

type Attendu = { id: number; nom: string; prenom: string; telephone: string | null } | null;

export default function Accueil({ antecedents, attendu = null }: { antecedents: Record<string, string>; attendu?: Attendu }) {
    // La réception peut « envoyer » un patient à la tablette : sa fiche s'ouvre à son nom.
    usePoll(4000, { only: ['attendu'] });
    const [langue, setLangue] = useState<Langue>(() => (new URLSearchParams(window.location.search).get('lang') === 'ar' ? 'ar' : 'fr'));
    const t = T[langue];
    const form = useForm({
        nom: '',
        prenom: '',
        date_naissance: '',
        sexe: '',
        telephone: '',
        ville: '',
        adresse: '',
        profession: '',
        antecedents: [] as string[],
        antecedents_autres: '',
        allergies: '',
        traitements: '',
        langue: 'fr' as Langue,
    });
    const { data, setData, errors, processing } = form;

    useEffect(() => {
        if (attendu) {
            form.setData((d) => ({ ...d, nom: attendu.nom, prenom: attendu.prenom, telephone: attendu.telephone ?? '' }));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [attendu?.id]);

    const basculer = (k: string) => setData('antecedents', data.antecedents.includes(k) ? data.antecedents.filter((a) => a !== k) : [...data.antecedents, k]);

    const envoyer = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, langue, patient_attendu_id: attendu?.id ?? null }));
        form.post('/accueil');
    };

    const big = cn(inputCls, 'h-12 text-base');

    return (
        <div className="min-h-screen bg-[#0b5f6b] px-4 py-6 md:py-10">
            <Head title="Fiche d'accueil" />
            <form
                onSubmit={envoyer}
                dir={langue === 'ar' ? 'rtl' : 'ltr'}
                lang={langue}
                className="mx-auto max-w-3xl rounded-2xl bg-white p-5 shadow-xl md:p-8 dark:bg-neutral-900"
            >
                {/* Choix de la langue */}
                <div className="mb-4 flex justify-center" dir="ltr">
                    <div className="flex rounded-full bg-muted p-1 text-base">
                        {(
                            [
                                ['fr', 'Français'],
                                ['ar', 'العربية'],
                            ] as const
                        ).map(([l, lib]) => (
                            <button
                                key={l}
                                type="button"
                                onClick={() => setLangue(l)}
                                className={cn(
                                    'rounded-full px-6 py-2 font-semibold',
                                    langue === l ? 'bg-[#04a3b5] text-white shadow' : 'text-muted-foreground',
                                )}
                            >
                                {lib}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-[#0b5f6b] dark:text-teal-200">{t.titre}</h1>
                        <p className="mt-1 text-muted-foreground">{t.intro}</p>
                    </div>
                    <img src="/images/logo-wesmile.png" alt="We Smile" className="h-20 w-auto shrink-0" />
                </div>

                {attendu && (
                    <div className="mt-5 rounded-xl border-2 border-[#04a3b5] bg-[#e6f7f9] p-4 text-lg text-[#0b5f6b]">
                        <b>
                            {t.bonjour} {attendu.prenom} {attendu.nom},
                        </b>{' '}
                        {t.completer}
                    </div>
                )}

                <h2 className="mt-6 mb-3 font-semibold text-[#0b5f6b]">{t.identite}</h2>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field label={t.nom} error={errors.nom}>
                        <input className={big} value={data.nom} onChange={(e) => setData('nom', e.target.value)} required />
                    </Field>
                    <Field label={t.prenom} error={errors.prenom}>
                        <input className={big} value={data.prenom} onChange={(e) => setData('prenom', e.target.value)} required />
                    </Field>
                    <Field label={t.naissance} error={errors.date_naissance}>
                        <input type="date" className={big} value={data.date_naissance} onChange={(e) => setData('date_naissance', e.target.value)} />
                    </Field>
                    <Field label={t.sexe}>
                        <select className={big} value={data.sexe} onChange={(e) => setData('sexe', e.target.value)}>
                            <option value="">—</option>
                            <option value="F">{t.femme}</option>
                            <option value="M">{t.homme}</option>
                        </select>
                    </Field>
                    <Field label={t.telephone} error={errors.telephone}>
                        <input type="tel" dir="ltr" className={big} value={data.telephone} onChange={(e) => setData('telephone', e.target.value)} required />
                    </Field>
                    <Field label={t.ville} error={errors.ville}>
                        <input className={big} value={data.ville} onChange={(e) => setData('ville', e.target.value)} />
                    </Field>
                    <Field label={t.profession}>
                        <input className={big} value={data.profession} onChange={(e) => setData('profession', e.target.value)} />
                    </Field>
                    <Field label={t.adresse}>
                        <input className={big} value={data.adresse} onChange={(e) => setData('adresse', e.target.value)} />
                    </Field>
                </div>

                <h2 className="mt-6 mb-3 font-semibold text-[#0b5f6b]">{t.sante}</h2>
                <p className="mb-2 text-sm text-muted-foreground">{t.cochez}</p>
                <div className="flex flex-wrap gap-2">
                    {Object.keys(antecedents).map((k) => (
                        <button
                            type="button"
                            key={k}
                            onClick={() => basculer(k)}
                            className={cn(
                                'rounded-full border px-4 py-2 text-base font-medium',
                                data.antecedents.includes(k) ? 'border-[#04a3b5] bg-[#04a3b5] text-white' : 'bg-background',
                            )}
                        >
                            {t.antecedents[k] ?? antecedents[k]}
                        </button>
                    ))}
                </div>
                <div className="mt-4 grid gap-4">
                    <Field label={t.allergies}>
                        <input className={big} value={data.allergies} onChange={(e) => setData('allergies', e.target.value)} placeholder={t.aucune} />
                    </Field>
                    <Field label={t.traitements}>
                        <input className={big} value={data.traitements} onChange={(e) => setData('traitements', e.target.value)} />
                    </Field>
                    <Field label={t.autre}>
                        <textarea className={textareaCls} value={data.antecedents_autres} onChange={(e) => setData('antecedents_autres', e.target.value)} />
                    </Field>
                </div>

                <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <span className="text-xs text-muted-foreground">{t.confidentiel}</span>
                    <Button type="submit" size="lg" className="h-12 px-8 text-base" disabled={processing}>
                        {t.envoyer}
                    </Button>
                </div>
            </form>
        </div>
    );
}
