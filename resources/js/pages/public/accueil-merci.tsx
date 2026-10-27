import { Head, router } from '@inertiajs/react';
import { useEffect } from 'react';

export default function Merci({ lang = 'fr' }: { lang?: 'fr' | 'ar' }) {
    useEffect(() => {
        // retour automatique à une fiche vide (en français) pour le patient suivant
        const t = setTimeout(() => router.visit('/accueil'), 8000);
        return () => clearTimeout(t);
    }, []);

    const ar = lang === 'ar';
    return (
        <div className="flex min-h-screen items-center justify-center bg-[#0b5f6b] p-6 text-center text-white" dir={ar ? 'rtl' : 'ltr'}>
            <Head title="Merci" />
            <div>
                <div className="text-6xl">✓</div>
                <h1 className="mt-4 text-3xl font-bold">{ar ? 'شكرا، تم إرسال بطاقتكم' : 'Merci, votre fiche est envoyée'}</h1>
                <p className="mt-3 text-lg text-teal-100">
                    {ar ? 'المرجو التوجه إلى الاستقبال مع بطاقتكم الوطنية.' : 'Merci de vous présenter à la réception avec votre carte nationale.'}
                </p>
            </div>
        </div>
    );
}
