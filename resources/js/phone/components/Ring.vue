<script setup>
/** A draining ring: `fraction` 1 → 0 over the choose window. Driven by the clock, not CSS. */
const props = defineProps({
    fraction: { type: Number, required: true },
    seconds: { type: Number, required: true },
    urgent: { type: Boolean, default: false },
});
const R = 46;
const C = 2 * Math.PI * R;
</script>

<template>
    <div class="relative h-28 w-28" role="timer" :aria-label="`${seconds} seconds left`">
        <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90">
            <circle cx="50" cy="50" :r="R" fill="none" stroke="currentColor" stroke-width="6" class="text-slate-800" />
            <circle
                cx="50"
                cy="50"
                :r="R"
                fill="none"
                stroke="currentColor"
                stroke-width="6"
                stroke-linecap="round"
                :stroke-dasharray="C"
                :stroke-dashoffset="C * (1 - props.fraction)"
                :class="props.urgent ? 'text-rose-400' : 'text-emerald-400'"
            />
        </svg>
        <span class="absolute inset-0 flex items-center justify-center font-mono text-4xl font-bold tabular-nums" :class="props.urgent ? 'text-rose-300' : 'text-white'">
            {{ seconds }}
        </span>
    </div>
</template>
