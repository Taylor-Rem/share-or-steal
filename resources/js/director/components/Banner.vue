<script setup>
/** One line the director must see: an error, a warning, or a lost connection. */
defineProps({ tone: { type: String, default: 'error' }, text: { type: String, required: true } });
defineEmits(['dismiss']);
const classes = {
    error: 'border-rose-500/50 bg-rose-500/10 text-rose-100',
    warning: 'border-amber-500/50 bg-amber-500/10 text-amber-100',
    info: 'border-sky-500/50 bg-sky-500/10 text-sky-100',
};
</script>

<template>
    <div class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm" :class="classes[tone]" role="alert">
        <p class="flex-1">{{ text }}</p>
        <button type="button" class="touch-manipulation text-lg leading-none opacity-70" aria-label="Dismiss" @click="$emit('dismiss')">×</button>
    </div>
</template>
