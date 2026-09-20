<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import Card from '../../../Components/Card.vue';
import Alert from '../../../Components/Alert.vue';
import Field from '../../../Components/Field.vue';
import InputError from '../../../Components/InputError.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Monogram from '../../../Components/Monogram.vue';
import Btn from '../../../Components/Btn.vue';
import type { PageProps } from '../../../types/inertia';

interface Player {
    id: string;
    /** "First Last" -- what is posted as user_name and shown once chosen. */
    name: string;
    /** What the row reads, nickname included. */
    label: string;
    email: string;
    initials: string;
    /** Lowercased name, nickname and email in one string. */
    search: string;
}

interface Props {
    venues: { id: string; name: string }[];
    /** Pre-selected when arriving from a venue's page, or back from a save. */
    venue_id: string | null;
    event_date: string | null;
    /** Both halves of the sitting are already answered. */
    returning: boolean;
    players: Player[];
}

const props = defineProps<Props>();

const flash = computed(() => usePage<PageProps>().props.flash);

const form = useForm({
    venue_id: props.venue_id ?? '',
    event_date: props.event_date ?? '',

    // Posted as fields rather than written into hidden inputs. user_name is
    // stored beside user_id so a record still says who it was for after an
    // account is gone: user_id is nullable with nullOnDelete, and a departed
    // player must leave a readable row behind.
    user_id: '',
    user_name: '',
    amount: '' as number | string,
});

const q = ref('');
const chosen = ref<Player | null>(null);

const matches = computed<Player[]>(() => {
    const needle = q.value.trim().toLowerCase();

    return needle === ''
        ? props.players
        : props.players.filter((player) => player.search.includes(needle));
});

const choose = (player: Player): void => {
    chosen.value = player;
    form.user_id = player.id;
    form.user_name = player.name;

    // On to the amount, which is the only thing left to say.
    nextTick(() => document.getElementById('amount')?.focus());
};

const change = (): void => {
    chosen.value = null;
    q.value = '';
    form.user_id = '';
    form.user_name = '';
};

/*
 * The venue and the date stay; the player and the amount do not.
 *
 * store() redirects back here with the sitting in the query string, and
 * Inertia re-renders THIS component rather than building a new one -- so the
 * last player and the last amount would still be sitting in the form, and the
 * search box's autofocus, which only fires when an input enters the document,
 * would not fire again. Both are done by hand for that reason.
 */
const submit = (): void => {
    form.post('/poker/venue-points', {
        onSuccess: () => {
            change();
            form.amount = '';
            nextTick(() => document.getElementById('player-search')?.focus());
        },
    });
};

const backHref = computed(() =>
    props.venue_id ? `/poker/venues/${props.venue_id}` : '/poker/venues');
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="League" title="Add Venue Points">
                <!-- In the header, not beside Save. The form returns to itself
                     after every entry, so leaving it is a different kind of act
                     from the entry being typed.

                     Back to the VENUE, which is where this form is reached from
                     and where the points will be read. It used to return to the
                     venue-points listing, which no longer exists; with no venue
                     chosen yet -- a link followed without one -- the venues list
                     is the honest fallback. -->
                <template #actions>
                    <Btn variant="ghost" :href="backHref">Back</Btn>
                </template>
            </PageHeader>
        </template>

        <div class="l-container l-stack">
            <!-- The form comes back to itself after every save, so this is the
                 only place the confirmation can land. -->
            <Alert v-if="flash.status" variant="success" :message="flash.status" />

            <Card>
                <form class="l-stack" @submit.prevent="submit">
                    <!-- Venue and date first, because they are the sitting: one
                         venue on one night, and a dozen players who were there.
                         Both carry over to the next entry, so they are asked once
                         and then left alone. -->
                    <Field name="venue_id" label="Venue" :error="form.errors.venue_id">
                        <select v-model="form.venue_id" class="field__control"
                                name="venue_id" id="venue_id" required :autofocus="! returning">
                            <option value="">Select Venue</option>

                            <option v-for="venue in venues" :key="venue.id" :value="venue.id">
                                {{ venue.name }}
                            </option>
                        </select>
                    </Field>

                    <Field v-model="form.event_date" name="event_date" label="Event Date"
                           type="date" required :error="form.errors.event_date" />

                    <!-- The player, from a searchable list rather than a select of
                         every account. A select is fine for a handful of options and
                         useless at two hundred: it cannot be searched past the
                         browser's type-to-jump, which matches the start of the label
                         only, so an administrator who knows a nickname or an email
                         address had no way to use it. -->
                    <div class="l-stack l-stack--tight">
                        <span class="field__label">Player</span>

                        <!-- Once chosen, the list gives way to the name. Twelve
                             entries for one night means twelve trips through this
                             control, and a two-hundred-row list left open under a
                             made choice is noise on every one of them. -->
                        <div v-if="chosen" class="field-row">
                            <p class="field__static field-row__grow">{{ chosen.name }}</p>

                            <div class="field-row__actions">
                                <div class="l-cluster l-cluster--end">
                                    <Btn variant="ghost" @click="change">Change</Btn>
                                </div>
                            </div>
                        </div>

                        <div v-else class="l-stack l-stack--tight">
                            <label class="u-visually-hidden" for="player-search">Search players</label>

                            <input v-model="q" id="player-search" type="search"
                                   class="field__control" placeholder="Name, nickname or email"
                                   :autofocus="returning">

                            <EmptyState v-if="players.length === 0" title="No players yet">
                                Venue points are recorded against an account, and there are none.
                            </EmptyState>

                            <ul v-else class="picker">
                                <li v-for="player in matches" :key="player.id" class="picker__item">
                                    <!-- type="button": this sits inside the form
                                         that Save submits, and a bare button
                                         would post it. -->
                                    <button type="button" class="picker__btn" @click="choose(player)">
                                        <Monogram :initials="player.initials" size="sm" decorative
                                                  class="picker__face" />

                                        <span class="picker__name">{{ player.label }}</span>

                                        <span class="picker__meta">{{ player.email }}</span>
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- user_id is posted without a control of its own, so
                             its failure has nowhere else to sit. -->
                        <InputError :messages="form.errors.user_id" />
                        <InputError :messages="form.errors.user_name" />
                    </div>

                    <Field v-model="form.amount" name="amount" label="Amount" type="number"
                           hint="Whole dollars only, rounded to the nearest dollar." hint-inline
                           required :error="form.errors.amount" />

                    <div class="l-cluster l-cluster--end">
                        <Btn variant="primary" :disabled="form.processing">Save</Btn>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
