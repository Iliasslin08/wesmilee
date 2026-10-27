import { Head, Link } from '@inertiajs/react';

// L'inscription publique est désactivée : c'est le chef (admin) qui crée les comptes.
export default function Register() {
    return (
        <>
            <Head title="Inscription" />
            <p className="text-center text-sm text-muted-foreground">
                Les comptes sont créés par le chef du centre.{' '}
                <Link href="/login" className="text-primary underline">
                    Se connecter
                </Link>
            </p>
        </>
    );
}

Register.layout = { title: 'Inscription désactivée', description: '' };
