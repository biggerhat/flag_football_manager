<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Users } from '@lucide/vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { show } from '@/routes/team';
import { show as clubPage } from '@/routes/club';
import { index, switchMethod } from '@/routes/teams';

const page = usePage();
const { isMobile, state } = useSidebar();
</script>

<template>
    <SidebarMenu v-if="page.props.team">
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent"
                        data-test="team-switcher"
                    >
                        <div
                            class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-accent"
                        >
                            <Users class="size-4" />
                        </div>
                        <div
                            class="grid flex-1 text-left text-sm leading-tight"
                        >
                            <span class="truncate font-medium">{{
                                page.props.team.name
                            }}</span>
                            <span
                                class="truncate text-xs text-muted-foreground capitalize"
                                >{{ page.props.team.role }}</span
                            >
                        </div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="min-w-56 rounded-lg"
                    align="start"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'right'
                              : 'bottom'
                    "
                >
                    <DropdownMenuLabel>Teams</DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="team in page.props.team.teams"
                        :key="team.id"
                        @select="
                            team.id !== page.props.team.id &&
                            router.post(switchMethod.url(team.id))
                        "
                    >
                        <span class="flex-1 truncate">{{ team.name }}</span>
                        <Check
                            v-if="team.id === page.props.team.id"
                            class="size-4"
                        />
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link :href="index()" class="cursor-pointer">
                            All my teams
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                        <Link :href="clubPage()" class="cursor-pointer">
                            Club
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                        <Link :href="show()" class="cursor-pointer">
                            Members &amp; teams
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
