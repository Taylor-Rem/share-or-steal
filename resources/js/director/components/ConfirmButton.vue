<script setup>
import { onScopeDispose, ref } from 'vue';

/**
 * A button that, when `confirm` is set, needs a second tap within three seconds. One
 * hand, no dialog, no accidental End.
 */
const props = defineProps({
    label: { type: String, required: true },
    confirm: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
    tone: { type: String, default: 'primary' }, // primary | danger | neutral
    size: { type: String, default: 'md' },
});
const emit = defineEmits(['confirmed']);
const armed = ref(false);
let timer = null;

function tap() {
    if (props.disabled || props.busy) return;
    if (props.confirm && !armed.value) {
        armed.value = true;
        timer = setTimeout(() => (armed.value = false), 3000);
        return;
    }
    clearTimeout(timer);
    armed.value = false;
    emit('confirmed');
}
onScopeDispose(() => clearTimeout(timer));

const tones = {
    primary: 'bg-emerald-500 text-slate-950',
    danger: 'bg-rose-600 text-white',
    neutral: 'bg-slate-800 text-slate-100',
};
</script>

<template>
    <button
        type="button"
        class="touch-manipulation select-none rounded-2xl font-bold transition active:scale-95 disabled:opacity-40 motion-reduce:transition-none"
        :class="[armed ? 'bg-amber-400 text-slate-950 ring-4 ring-amber-200/60' : tones[tone], size === 'lg' ? 'h-20 text-2xl' : 'h-12 px-4 text-base']"
        :disabled="disabled || busy"
        :aria-live="armed ? 'assertive' : 'off'"
        @click="tap"
    >
        <template v-if="busy">…</template>
        <template v-else-if="armed">Tap again to {{ label.toLowerCase() }}</template>
        <template v-else>{{ label }}</template>
    </button>
</template>
