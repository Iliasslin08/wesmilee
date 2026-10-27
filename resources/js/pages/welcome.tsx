import { Head, Link } from '@inertiajs/react';

// Page non utilisée : « / » redirige vers la connexion ou l'espace de chacun.
export default function Welcome() {
    return (
        <div className="flex min-h-screen items-center justify-center bg-[#0b5f6b] text-white">
            <Head title="We Smile" />
            <Link href="/login" className="rounded-lg bg-white px-6 py-3 font-bold text-[#0b5f6b]">
                Se connecter
            </Link>
        </div>
    );
}
