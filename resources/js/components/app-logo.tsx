import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { cabinet } = usePage().props;

    return (
        <>
            <img src="/images/logo-wesmile.png" alt="We Smile" className="h-11 w-auto shrink-0 group-data-[collapsible=icon]:h-7" />
            <div className="ml-1 grid flex-1 text-left text-xs group-data-[collapsible=icon]:hidden">
                <span className="truncate text-muted-foreground">{String(cabinet ?? 'Centre dentaire')}</span>
            </div>
        </>
    );
}
