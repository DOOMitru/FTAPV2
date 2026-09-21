<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import Card from '../../../Components/Card.vue';
import Table from '../../../Components/Table.vue';
import Btn from '../../../Components/Btn.vue';
import Rank from '../../../Components/Rank.vue';
import Action from '../../../Components/Action.vue';
import Alert from '../../../Components/Alert.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Pagination from '../../../Components/Pagination.vue';
import { useConfirm } from '../../../composables/useConfirm';
import type { Paginated, PageProps } from '../../../types/inertia';

interface Structure {
    id: string;
    place: number;
    points_label: string;
    can_delete: boolean;
    delete_confirm: string;
}

interface Props {
    structures: Paginated<Structure>;
}

const props = defineProps<Props>();
// computed, not a plain const: usePage().props.flash read once at setup is
// the flash that ARRIVED WITH THIS COMPONENT. Inertia reuses a component when
// a visit renders the same page, so every redirect back to the page you are on
// -- a delete returning to its listing, a form returning to itself -- left the
// old value in place and showed nothing.
const flash = computed(() => usePage<PageProps>().props.flash);
const { ask } = useConfirm();

const remove = async (structure: Structure): Promise<void> => {
    if (await ask(structure.delete_confirm)) {
        router.delete(`/poker/points-structure/${structure.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="Setup" title="Points Structure">
                <template #actions>
                    <Btn variant="primary" href="/poker/points-structure/create">Add Place</Btn>
                </template>
            </PageHeader>
        </template>

        <div class="l-container l-stack">
            <!-- Rendered here, not by the layout: a page that wants its flash
                 asks for it. -->
            <Alert v-if="flash.status" variant="success" :message="flash.status" />
            <Alert v-if="flash.error" variant="danger" :message="flash.error" />

            <Card flush>
                <Table>
                    <template #head>
                        <th scope="col">Place</th>
                        <th scope="col" class="table__num">Points</th>
                        <th scope="col" class="table__actions">Actions</th>
                    </template>

                    <tr v-for="structure in props.structures.data" :key="structure.id">
                        <td><Rank :place="structure.place" /></td>

                        <td class="table__num">{{ structure.points_label }}</td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                                <Action icon="edit" label="Edit"
                                        :href="`/poker/points-structure/${structure.id}/edit`" />

                                <!--
                                    The deepest place only. The ladder shrinks
                                    from the bottom, so offering Delete on a
                                    middle row would be offering a control the
                                    controller refuses.
                                -->
                                <form v-if="structure.can_delete" @submit.prevent="remove(structure)">
                                    <Action icon="delete" label="Delete" danger />
                                </form>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="props.structures.data.length === 0">
                        <td colspan="3"><EmptyState title="No points structure defined." /></td>
                    </tr>
                </Table>

                <div v-if="props.structures.status_label" class="card__pager">
                    <Pagination :links="props.structures.links" :status-label="props.structures.status_label" />
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
