import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** False for viewers, who can look at and print team content but not change it. */
export function useCanEdit() {
    const page = usePage();

    return computed(() => page.props.team?.canEdit ?? true);
}
