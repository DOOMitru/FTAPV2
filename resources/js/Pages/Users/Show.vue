<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Card from '../../Components/Card.vue';
import Alert from '../../Components/Alert.vue';
import Badge from '../../Components/Badge.vue';
import Btn from '../../Components/Btn.vue';
import Monogram from '../../Components/Monogram.vue';
import { useConfirm } from '../../composables/useConfirm';

interface Props {
    /** "User Details: Dana Whitlock", composed in PHP. */
    title: string;
    user: {
        id: string;
        full_name: string;
        nickname_label: string;
        email: string;
        display_name: string;
        initials: string;
        role_label: string;
        role_badge: string;
        role_tone: 'accent' | 'open' | 'primary' | null;
        is_admin: boolean;
        member_since_label: string;
        approval_label: string;
        approval_tone: 'accent' | 'open' | 'primary' | null;
        decided_label: string | null;
        can_verify: boolean;
        can_approve: boolean;
        can_reject: boolean;
        /** Carries U+2063 emphasis markers; the dialog renders them. */
        reject_confirm: string;
    };
    invite_url: string | null;
    verification_url: string | null;
}

const props = defineProps<Props>();

const { ask } = useConfirm();

const sendInvite = (): void => {
    router.post(`/users/${props.user.id}/invite`, {}, { preserveScroll: true });
};

const sendVerification = (): void => {
    router.post(`/users/${props.user.id}/verification`, {}, { preserveScroll: true });
};

const approve = (): void => {
    router.patch(`/users/${props.user.id}/approve`, {}, { preserveScroll: true });
};

const reject = async (): Promise<void> => {
    if (await ask(props.user.reject_confirm)) {
        router.patch(`/users/${props.user.id}/reject`, {}, { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="Setup" :title="title">
                <template #actions>
                    <Btn variant="ghost" href="/users">Back to List</Btn>
                    <Btn variant="primary" :href="`/users/${user.id}/edit`">Edit User</Btn>
                </template>
            </PageHeader>
        </template>

        <!-- Its own container, above the sidebar grid rather than inside it: a
             direct child of .l-sidebar becomes one of its two columns, so an
             alert placed there would render as a sidebar rather than a notice. -->
        <div v-if="invite_url || verification_url"
             class="l-container l-stack">

            <!-- Shown as well as sent. MAIL_MAILER is log, so a link that is
                 only emailed reaches nobody; these stay useful once a mailer
                 exists, for when a player says it never arrived.

                 The URL sits beside the alert rather than inside it, as it
                 does on the players list: Alert takes its message as a prop
                 so that <Emph> can render the markers in it, which leaves no
                 slot to nest a .u-mono line in. -->
            <Alert v-if="invite_url" variant="info"
                   message="Password link — expires, and can only be used once." />
            <p v-if="invite_url" class="u-mono">{{ invite_url }}</p>

            <Alert v-if="verification_url" variant="info"
                   message="Verification link — expires, and can only be used once." />
            <p v-if="verification_url" class="u-mono">{{ verification_url }}</p>
        </div>

        <div class="l-container l-sidebar">
            <Card title="Personal Information">
                <dl class="rows">
                    <div class="row">
                        <dt class="row__label">Name</dt>
                        <dd class="row__value">{{ user.full_name }}</dd>
                    </div>

                    <div class="row">
                        <dt class="row__label">Nickname</dt>
                        <dd class="row__value">{{ user.nickname_label }}</dd>
                    </div>

                    <div class="row">
                        <dt class="row__label">Email</dt>
                        <dd class="row__value">{{ user.email }}</dd>
                    </div>
                </dl>
            </Card>

            <Card title="Account Details">
                <div class="field__media">
                    <Monogram :initials="user.initials" size="lg" decorative />

                    <div>
                        <div class="entry__title">{{ user.display_name }}</div>

                        <div class="entry__meta">
                            <span>{{ user.role_label }}</span>
                        </div>
                    </div>
                </div>

                <!-- The card content is an l-stack so the gap lands BETWEEN
                     the <dl> and the cluster below. Putting l-stack ON the
                     cluster would be wrong: it sets margin-block-start on
                     CHILDREN, so in a flex row it offsets each button instead
                     of spacing the group. -->
                <div class="l-stack">
                    <dl class="rows">
                        <div class="row">
                            <dt class="row__label">Role</dt>
                            <dd class="row__value">
                                <Badge :variant="user.role_tone">{{ user.role_badge }}</Badge>
                            </dd>
                        </div>

                        <div class="row">
                            <dt class="row__label">Member Since</dt>
                            <dd class="row__value">{{ user.member_since_label }}</dd>
                        </div>

                        <div class="row">
                            <dt class="row__label">Approval</dt>
                            <dd class="row__value">
                                <Badge :variant="user.approval_tone">{{ user.approval_label }}</Badge>
                            </dd>
                        </div>

                        <!-- Null for the accounts the migration grandfathered:
                             no person made that decision, so naming one would
                             be a false audit trail. -->
                        <div v-if="user.decided_label" class="row">
                            <dt class="row__label">Decided</dt>
                            <dd class="row__value">{{ user.decided_label }}</dd>
                        </div>
                    </dl>

                    <!-- This page, not only the queue, is the full control
                         surface. A rejected account has left the queue, so
                         this is the only place the decision can be reversed. -->
                    <div class="l-cluster">
                        <Btn variant="ghost" type="button" @click="sendInvite">Send password link</Btn>

                        <Btn v-if="user.can_verify" variant="ghost" type="button"
                             @click="sendVerification">Send verification link</Btn>

                        <Btn v-if="user.can_approve" variant="primary" type="button"
                             @click="approve">Approve</Btn>

                        <Btn v-if="user.can_reject" variant="danger" type="button"
                             @click="reject">Reject</Btn>
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
