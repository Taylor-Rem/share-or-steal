<script setup>
import { ref } from 'vue';

const props = defineProps({ label: { type: String, required: true }, value: { type: String, required: true } });
const copied = ref(false);

async function copy() {
    try {
        await navigator.clipboard.writeText(props.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch {
        window.prompt('Copy this:', props.value);
    }
}
</script>

<template>
    <div class="flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2">
        <div class="min-w-0 flex-1">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ label }}</div>
            <a :href="value" target="_blank" rel="noopener" class="block truncate font-mono text-sm text-sky-300">{{ value }}</a>
        </div>
        <button type="button" class="h-10 shrink-0 touch-manipulation rounded-lg bg-slate-800 px-3 text-sm font-semibold active:scale-95" @click="copy">{{ copied ? 'Copied' : 'Copy' }}</button>
    </div>
</template>
