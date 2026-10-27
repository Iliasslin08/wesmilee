import { Link, usePage } from '@inertiajs/react';
import {
    BookUser,
    Building2,
    CalendarDays,
    ClipboardCheck,
    ClipboardList,
    Clock,
    FlaskConical,
    Landmark,
    LayoutGrid,
    ListChecks,
    MessageSquare,
    PhoneCall,
    ScanLine,
    Stethoscope,
    Tv,
    Users,
    UsersRound,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const MENUS: Record<string, NavItem[]> = {
    secretaire: [
        { title: "Salle d'attente", href: '/salle-attente', icon: UsersRound },
        { title: 'Agenda', href: '/agenda', icon: CalendarDays },
        { title: 'Fiches à valider', href: '/fiches', icon: ClipboardCheck },
        { title: 'Tous les dossiers', href: '/patients', icon: BookUser },
        { title: 'Patients à rappeler', href: '/a-rappeler', icon: PhoneCall },
        { title: 'Écran TV', href: '/tv', icon: Tv },
    ],
    docteur: [
        { title: 'Mes patients', href: '/mes-patients', icon: Stethoscope },
        { title: 'Tous les dossiers', href: '/patients', icon: BookUser },
        { title: 'Mes horaires', href: '/mes-horaires', icon: Clock },
    ],
    admin_finance: [
        { title: 'Devis à présenter', href: '/plans', icon: ClipboardList },
        { title: 'Paiements des patients', href: '/patients', icon: Wallet },
    ],
    radiologue: [
        { title: 'Radiologie', href: '/radiologie', icon: ScanLine },
        { title: 'Tous les dossiers', href: '/patients', icon: BookUser },
    ],
    docteur_labo: [
        { title: 'Commandes', href: '/labo', icon: FlaskConical },
        { title: 'Tous les dossiers', href: '/patients', icon: BookUser },
    ],
    photographe: [{ title: 'Tous les dossiers', href: '/patients', icon: BookUser }],
    admin: [
        { title: 'Tableau de bord', href: '/admin/tableau-de-bord', icon: LayoutGrid },
        { title: 'Devis à présenter', href: '/plans', icon: ClipboardList },
        { title: "Salle d'attente", href: '/salle-attente', icon: UsersRound },
        { title: 'Agenda', href: '/agenda', icon: CalendarDays },
        { title: 'Fiches à valider', href: '/fiches', icon: ClipboardCheck },
        { title: 'Tous les dossiers', href: '/patients', icon: BookUser },
        { title: 'Patients à rappeler', href: '/a-rappeler', icon: PhoneCall },
        { title: 'Labo', href: '/labo', icon: FlaskConical },
        { title: 'Impayés', href: '/impayes', icon: Wallet },
        { title: 'Clôture de caisse', href: '/caisse', icon: Landmark },
        { title: 'Catalogue des actes', href: '/admin/catalogue', icon: ListChecks },
        { title: 'Horaires des docteurs', href: '/admin/horaires', icon: Clock },
        { title: 'Commentaires patients', href: '/admin/avis', icon: MessageSquare },
        { title: 'Écran TV', href: '/tv', icon: Tv },
    ],
};

// Compte développeur (secret) : tout ce que voit la chef + utilisateurs, salles et paramètres.
MENUS.developpeur = [
    ...MENUS.admin,
    { title: 'Utilisateurs', href: '/admin/utilisateurs', icon: Users },
    { title: 'Cabinet & salles', href: '/admin/cabinet', icon: Building2 },
];

export function AppSidebar() {
    const { auth, fichesEnAttente, demandesRadio, rdvAFixer, avisNonLus, plansAValider } = usePage().props as unknown as {
        auth: { user?: Record<string, unknown> };
        fichesEnAttente?: number;
        demandesRadio?: number;
        rdvAFixer?: number;
        avisNonLus?: number;
        plansAValider?: number;
    };
    const role = (auth.user?.role as string) ?? '';
    // Labo : seule la cheffe a « Tous les dossiers »
    const menu = (MENUS[role] ?? []).filter((i) => !(role === 'docteur_labo' && i.href === '/patients' && !auth.user?.chef_labo));
    const items = menu.map((i) =>
        i.href === '/fiches'
            ? { ...i, badge: fichesEnAttente ?? 0 }
            : i.href === '/radiologie'
              ? { ...i, badge: demandesRadio ?? 0 }
              : i.href === '/agenda'
                ? { ...i, badge: rdvAFixer ?? 0 }
                : i.href === '/admin/avis'
                  ? { ...i, badge: avisNonLus ?? 0 }
                  : i.href === '/plans'
                    ? { ...i, badge: plansAValider ?? 0 }
                    : i,
    );

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} label={(auth.user?.role_label as string) ?? ''} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
