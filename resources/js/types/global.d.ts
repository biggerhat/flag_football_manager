import type { Directive } from 'vue';
import type { Auth } from '@/types/auth';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            team: {
                id: number;
                name: string;
                role: 'owner' | 'coach' | 'viewer';
                canEdit: boolean;
                teams: {
                    id: number;
                    name: string;
                    role: 'owner' | 'coach' | 'viewer';
                }[];
            } | null;
            seasons: {
                current: number | null;
                list: {
                    id: number;
                    name: string;
                    starts_on: string;
                    ends_on: string | null;
                }[];
            } | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
