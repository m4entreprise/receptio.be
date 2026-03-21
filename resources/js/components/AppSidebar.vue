<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BellRing, Bot, Cable, LayoutGrid, Phone, PhoneCall, Settings2 } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Vue d’ensemble',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Appels',
        href: '/dashboard/calls',
        icon: PhoneCall,
    },
    {
        title: 'Messages',
        href: '/dashboard/messages',
        icon: BellRing,
    },
    {
        title: 'Agent',
        href: '/dashboard/agent',
        icon: Bot,
    },
    {
        title: 'Numéros & routage',
        href: '/dashboard/numbers',
        icon: Phone,
    },
    {
        title: 'Intégrations',
        href: '/dashboard/integrations',
        icon: Cable,
    },
    {
        title: 'Paramètres',
        href: '/dashboard/workspace',
        icon: Settings2,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Profil',
        href: '/settings/profile',
        icon: Settings2,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
