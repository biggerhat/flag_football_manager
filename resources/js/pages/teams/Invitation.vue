<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { accept } from '@/routes/invitations';
import { login, register } from '@/routes';

const props = defineProps<{
    token: string;
    team: string;
    role: string;
    email: string;
    expired: boolean;
    canRegister: boolean;
    club: string | null;
}>();

defineOptions({
    layout: { title: 'Team invitation' },
});

const form = useForm({});
const page = usePage();

function join() {
    form.post(accept.url(props.token));
}
</script>

<template>
    <Head title="Team invitation" />

    <div class="flex flex-col gap-5 text-center">
        <p v-if="expired" class="text-sm text-muted-foreground">
            This invitation to <strong>{{ team }}</strong> has expired. Ask the
            team owner to send a new one.
        </p>
        <template v-else>
            <p v-if="club" class="text-sm">
                You’ve been invited to join the club
                <strong>{{ club }}</strong>
                <template v-if="role === 'owner'">
                    as an administrator, with full access to all of its teams.
                </template>
                <template v-else>
                    as a <strong>{{ role }}</strong> on all of its teams.
                </template>
            </p>
            <p v-else class="text-sm">
                You’ve been invited to join <strong>{{ team }}</strong> as a
                <strong>{{ role }}</strong
                >.
            </p>

            <template v-if="$page.props.auth.user">
                <Button :disabled="form.processing" @click="join">
                    Join {{ team }}
                </Button>
                <InputError :message="page.props.errors?.invitation" />
                <p class="text-xs text-muted-foreground">
                    Signed in as {{ $page.props.auth.user.email }}. The
                    invitation was sent to {{ email }}.
                </p>
            </template>
            <template v-else>
                <p class="text-xs text-muted-foreground">
                    Use <strong>{{ email }}</strong> to log in or create an
                    account, then you’ll land back here to join.
                </p>
                <div class="flex justify-center gap-2">
                    <Button as-child>
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button v-if="canRegister" variant="outline" as-child>
                        <Link :href="register({ query: { email } })"
                            >Create account</Link
                        >
                    </Button>
                </div>
            </template>
        </template>
    </div>
</template>
