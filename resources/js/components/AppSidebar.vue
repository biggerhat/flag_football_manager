<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    CalendarDays,
    CalendarRange,
    ClipboardList,
    BarChart3,
    Flag,
    LayoutGrid,
    Users,
    Shapes,
    Swords,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import SeasonSwitcher from '@/components/SeasonSwitcher.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { calendar, dashboard, opponents, stats } from '@/routes';
import { index as drills } from '@/routes/drills';
import { index as formations } from '@/routes/formations';
import { index as gameplans } from '@/routes/gameplans';
import { index as playbooks } from '@/routes/playbooks';
import { index as players } from '@/routes/players';
import { index as practices } from '@/routes/practices';
import type { NavItem } from '@/types';

const home: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
];

const groups: { label: string; items: NavItem[] }[] = [
    {
        label: 'Plan',
        items: [
            { title: 'Playbooks', href: playbooks(), icon: BookOpen },
            { title: 'Formations', href: formations(), icon: Shapes },
            { title: 'Drills', href: drills(), icon: ClipboardList },
        ],
    },
    {
        label: 'Schedule',
        items: [
            { title: 'Practices', href: practices(), icon: CalendarDays },
            { title: 'Calendar', href: calendar(), icon: CalendarRange },
        ],
    },
    {
        label: 'Game',
        items: [
            { title: 'Game plans', href: gameplans(), icon: Swords },
            { title: 'Opponents', href: opponents(), icon: Flag },
            { title: 'Stats', href: stats(), icon: BarChart3 },
            { title: 'Roster', href: players(), icon: Users },
        ],
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <TeamSwitcher />
            <SeasonSwitcher />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="home" label="Home" />
            <NavMain
                v-for="group in groups"
                :key="group.label"
                :items="group.items"
                :label="group.label"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
