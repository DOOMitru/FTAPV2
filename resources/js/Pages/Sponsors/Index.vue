<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Card from '../../Components/Card.vue';
import Table from '../../Components/Table.vue';
import Btn from '../../Components/Btn.vue';
import Badge from '../../Components/Badge.vue';
import Action from '../../Components/Action.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { useConfirm } from '../../composables/useConfirm';

interface Sponsor {
    id: string;
    name: string;
    website_url: string | null;
    logo_url: string;
    is_premium: boolean;
    tier_label: string;
    delete_confirm: string;
}

interface Props {
    sponsors: Sponsor[];
}

const props = defineProps<Props>();

const { ask } = useConfirm();

const remove = async (sponsor: Sponsor): Promise<void> => {
    if (await ask(sponsor.delete_confirm)) {
        router.delete(`/sponsors/${sponsor.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="Setup" title="Sponsors">
                <template #actions>
                    <Btn variant="primary" href="/sponsors/create">Add Sponsor</Btn>
                </template>
            </PageHeader>
        </template>

        <div class="l-container l-stack">
            <Card flush>
                <!--
                    Listed in ordered() sequence, the same scope the home page
                    uses, so this list is what the page will actually render.
                -->
                <Table cards class="sponsors-index__table">
                    <template #head>
                        <th scope="col">Logo</th>
                        <th scope="col">Name</th>
                        <th scope="col">Tier</th>
                        <th scope="col" class="table__actions">Actions</th>
                    </template>

                    <tr v-for="sponsor in props.sponsors" :key="sponsor.id">
                        <!--
                            The logo carries the link when there is a site to
                            reach, which is why the Website column is gone.

                            alt is not the same in both branches, and that is
                            not a slip: an image is the accessible NAME of a
                            link that contains nothing else, so an empty alt
                            here would leave a screen reader announcing a link
                            with no name at all. Unlinked it stays decorative,
                            because the name sits in the very next cell.
                        -->
                        <td class="sponsors-index__logo">
                            <a v-if="sponsor.website_url" class="sponsor-thumb-link"
                               :href="sponsor.website_url" target="_blank" rel="noopener noreferrer">
                                <img class="sponsor-thumb" :src="sponsor.logo_url" :alt="sponsor.name">
                                <span class="u-visually-hidden">(opens in a new tab)</span>
                            </a>
                            <img v-else class="sponsor-thumb" :src="sponsor.logo_url" alt="">
                        </td>

                        <td class="sponsors-index__name">
                            <div class="entry__title">{{ sponsor.name }}</div>

                            <div v-if="sponsor.website_url" class="entry__meta">
                                <span>{{ sponsor.website_url }}</span>
                            </div>
                        </td>

                        <td class="sponsors-index__tier">
                            <Badge :variant="sponsor.is_premium ? 'primary' : null">{{ sponsor.tier_label }}</Badge>
                        </td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                                <Action icon="edit" label="Edit" :href="`/sponsors/${sponsor.id}/edit`" />

                                <form @submit.prevent="remove(sponsor)">
                                    <Action icon="delete" label="Delete" danger />
                                </form>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="props.sponsors.length === 0">
                        <td colspan="4">
                            <EmptyState title="No sponsors yet.">
                                Sponsors added here appear on the home page. The wall is hidden entirely while this list is empty.
                            </EmptyState>
                        </td>
                    </tr>
                </Table>
            </Card>
        </div>
    </AppLayout>
</template>
