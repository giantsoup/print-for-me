<script setup lang="ts">
import LuminousAppLayout from '@/layouts/LuminousAppLayout.vue';
import { formatDateOnly, isoDateToMaskedDisplay, maskDateInput, maskedDateToIso, stripNonDigits } from '@/lib/prints';
import { formatMinutes, joinMinutes, type LinkableRequest } from '@/lib/queue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Minus, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    prefill: { print_request_id: number; title: string; for_whom: string | null; needed_by_date: string | null; notes: string | null } | null;
    linkableRequests: LinkableRequest[];
}>();
function newPlate(index: number): { name: string; hours: string; minutes: string; filament: string; estimated_minutes: number | null } {
    return { name: 'Plate ' + (index + 1), hours: '', minutes: '', filament: '', estimated_minutes: null };
}
const initialRequestId: number | null = props.prefill?.print_request_id ?? null;
const form = useForm({
    title: props.prefill?.title ?? '',
    print_request_id: initialRequestId,
    for_whom: props.prefill?.for_whom ?? '',
    needed_by_date: isoDateToMaskedDisplay(props.prefill?.needed_by_date),
    filament: '',
    notes: props.prefill?.notes ?? '',
    add_to_top: false,
    plates: [newPlate(0)],
});
const errors = computed<Record<string, string | undefined>>(() => form.errors);
const plateCount = ref('1');
const neededByDateIso = computed(() => maskedDateToIso(form.needed_by_date.trim()));
const neededByDateClientError = computed(() => {
    if (!form.needed_by_date.trim()) return null;
    if (stripNonDigits(form.needed_by_date).length < 8) return 'Finish the needed-by date as MM/DD/YYYY.';
    if (!neededByDateIso.value) return 'Enter a real calendar date as MM/DD/YYYY.';
    return null;
});
const totalMinutes = computed(() => form.plates.reduce((sum, plate) => sum + (joinMinutes(plate.hours, plate.minutes) ?? 0), 0));
const canSubmit = computed(
    () =>
        Boolean(form.title.trim()) &&
        form.plates.length > 0 &&
        form.plates.every((plate) => Boolean(plate.name.trim())) &&
        !neededByDateClientError.value &&
        !form.processing,
);
watch(
    () => form.print_request_id,
    (id) => {
        if (!form.for_whom.trim()) form.for_whom = props.linkableRequests.find((request) => request.id === id)?.user_name ?? '';
    },
);
function setPlateCount(value: number): void {
    const count = Math.max(1, Math.min(50, Math.floor(value) || 1));
    while (form.plates.length < count) form.plates.push(newPlate(form.plates.length));
    form.plates.splice(count);
    plateCount.value = String(count);
}
function commitPlateCount(): void {
    setPlateCount(Number(plateCount.value));
}
function onNeededByDateInput(event: Event): void {
    if (event.target instanceof HTMLInputElement) {
        form.needed_by_date = maskDateInput(event.target.value);
        event.target.value = form.needed_by_date;
        form.clearErrors('needed_by_date');
    }
}
function submit(): void {
    commitPlateCount();
    if (!canSubmit.value) return;
    form.transform((data) => ({
        ...data,
        needed_by_date: maskedDateToIso(data.needed_by_date.trim()),
        plates: data.plates.map((plate) => ({
            name: plate.name,
            filament: plate.filament,
            estimated_minutes: joinMinutes(plate.hours, plate.minutes),
        })),
    })).post(route('admin.print-queue.store'));
}
</script>

<template>
    <LuminousAppLayout
        active-nav="queue"
        eyebrow="Print Queue"
        title="Add to the queue"
        intro="Name the print, split it into plates, and add timing so the queue can plan ahead."
        :show-dock="false"
    >
        <Head title="Add to queue" />
        <form class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]" @submit.prevent="submit">
            <section class="space-y-6">
                <article class="luminous-panel px-5 py-5">
                    <label for="title" class="field-label">Title</label>
                    <input id="title" v-model="form.title" class="luminous-input" type="text" maxlength="160" required />
                    <p v-if="form.errors.title" class="mt-2 text-sm text-rose-300">{{ form.errors.title }}</p>
                </article>
                <article class="luminous-panel px-5 py-5">
                    <label for="print_request_id" class="field-label">Linked request</label>
                    <select id="print_request_id" v-model="form.print_request_id" class="luminous-input">
                        <option :value="null">No linked request</option>
                        <option v-for="request in props.linkableRequests" :key="request.id" :value="request.id">{{ request.label }}</option>
                    </select>
                    <p v-if="form.errors.print_request_id" class="mt-2 text-sm text-rose-300">{{ form.errors.print_request_id }}</p>
                </article>
                <article class="luminous-panel px-5 py-5">
                    <label for="for_whom" class="field-label">For whom</label>
                    <input id="for_whom" v-model="form.for_whom" class="luminous-input" maxlength="120" />
                    <p v-if="form.errors.for_whom" class="mt-2 text-sm text-rose-300">{{ form.errors.for_whom }}</p>
                </article>
                <article class="luminous-panel px-5 py-5">
                    <label for="needed_by_date" class="field-label">Needed by</label>
                    <input
                        id="needed_by_date"
                        :value="form.needed_by_date"
                        type="text"
                        inputmode="numeric"
                        maxlength="10"
                        placeholder="MM/DD/YYYY"
                        class="luminous-input"
                        @input="onNeededByDateInput"
                    />
                    <p v-if="neededByDateClientError || form.errors.needed_by_date" class="mt-2 text-sm text-rose-300">
                        {{ neededByDateClientError || form.errors.needed_by_date }}
                    </p>
                </article>
                <article class="luminous-panel px-5 py-5">
                    <label for="plate_count" class="field-label">Plates</label>
                    <div class="mt-3 flex items-center gap-3">
                        <button
                            type="button"
                            class="pill-button pill-button-secondary disabled:cursor-not-allowed disabled:opacity-45"
                            :disabled="form.plates.length === 1"
                            @click="setPlateCount(form.plates.length - 1)"
                        >
                            <Minus class="h-4 w-4" /><span class="sr-only sm:not-sr-only">Remove plate</span>
                        </button>
                        <input
                            id="plate_count"
                            v-model="plateCount"
                            type="text"
                            inputmode="numeric"
                            class="luminous-input w-20 text-center"
                            @blur="commitPlateCount"
                        />
                        <button
                            type="button"
                            class="pill-button pill-button-secondary disabled:cursor-not-allowed disabled:opacity-45"
                            :disabled="form.plates.length === 50"
                            @click="setPlateCount(form.plates.length + 1)"
                        >
                            <Plus class="h-4 w-4" /><span class="sr-only sm:not-sr-only">Add plate</span>
                        </button>
                    </div>
                    <p v-if="errors.plates" class="mt-2 text-sm text-rose-300">{{ errors.plates }}</p>
                    <div class="mt-4 grid gap-3">
                        <div v-for="(plate, index) in form.plates" :key="index" class="rounded-[1.35rem] bg-white/[0.04] px-4 py-4">
                            <label :for="'plate_name_' + index" class="field-label">Name</label>
                            <input :id="'plate_name_' + index" v-model="plate.name" class="luminous-input mt-2" maxlength="120" required />
                            <p v-if="errors['plates.' + index + '.name']" class="mt-2 text-sm text-rose-300">
                                {{ errors['plates.' + index + '.name'] }}
                            </p>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <label class="grid gap-2"
                                    ><span class="field-label">Hours</span
                                    ><input v-model="plate.hours" type="text" inputmode="numeric" pattern="[0-9]*" class="luminous-input"
                                /></label>
                                <label class="grid gap-2"
                                    ><span class="field-label">Min</span
                                    ><input v-model="plate.minutes" type="text" inputmode="numeric" pattern="[0-9]*" class="luminous-input"
                                /></label>
                            </div>
                            <p v-if="errors['plates.' + index + '.estimated_minutes']" class="mt-2 text-sm text-rose-300">
                                {{ errors['plates.' + index + '.estimated_minutes'] }}
                            </p>
                            <label class="mt-3 grid gap-2"
                                ><span class="field-label">Filament</span><input v-model="plate.filament" class="luminous-input" maxlength="160"
                            /></label>
                            <p v-if="errors['plates.' + index + '.filament']" class="mt-2 text-sm text-rose-300">
                                {{ errors['plates.' + index + '.filament'] }}
                            </p>
                        </div>
                    </div>
                </article>
                <article class="luminous-panel px-5 py-5">
                    <label for="filament" class="field-label">Filament</label
                    ><input id="filament" v-model="form.filament" class="luminous-input" maxlength="160" />
                    <p v-if="form.errors.filament" class="mt-2 text-sm text-rose-300">{{ form.errors.filament }}</p>
                </article>
                <article class="luminous-panel px-5 py-5">
                    <label for="notes" class="field-label">Notes</label
                    ><textarea id="notes" v-model="form.notes" class="luminous-textarea" maxlength="5000" />
                    <p v-if="form.errors.notes" class="mt-2 text-sm text-rose-300">{{ form.errors.notes }}</p>
                </article>
            </section>
            <aside class="space-y-6">
                <article class="luminous-panel px-5 py-5">
                    <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Queue Summary</p>
                    <dl class="mt-6 space-y-3">
                        <div class="flex items-center justify-between rounded-2xl bg-white/[0.04] px-4 py-4">
                            <dt class="text-muted-soft text-sm">Plates</dt>
                            <dd class="text-right text-sm font-semibold text-white">{{ form.plates.length }}</dd>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl bg-white/[0.04] px-4 py-4">
                            <dt class="text-muted-soft text-sm">Estimated total</dt>
                            <dd class="text-right text-sm font-semibold text-white">{{ formatMinutes(totalMinutes) }}</dd>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl bg-white/[0.04] px-4 py-4">
                            <dt class="text-muted-soft text-sm">Needed by</dt>
                            <dd class="text-right text-sm font-semibold text-white">
                                {{ neededByDateIso ? formatDateOnly(neededByDateIso) : 'Flexible timing' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="sr-only">Add to top</dt>
                            <dd>
                                <label
                                    for="add_to_top"
                                    class="flex min-h-12 cursor-pointer items-center justify-between gap-3 rounded-2xl bg-white/[0.04] px-4 py-4"
                                >
                                    <span class="text-muted-soft text-sm">Add to top</span>
                                    <input
                                        id="add_to_top"
                                        v-model="form.add_to_top"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-white/15 bg-transparent text-primary"
                                    />
                                </label>
                            </dd>
                        </div>
                    </dl>
                </article>
                <article class="luminous-panel px-5 py-5">
                    <button
                        type="submit"
                        :disabled="!canSubmit"
                        class="pill-button pill-button-primary w-full disabled:cursor-not-allowed disabled:opacity-45"
                    >
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />{{ form.processing ? 'Adding to queue' : 'Add to queue' }}
                    </button>
                </article>
            </aside>
        </form>
    </LuminousAppLayout>
</template>
