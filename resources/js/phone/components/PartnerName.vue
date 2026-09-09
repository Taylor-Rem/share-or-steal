<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import Avatar from '../../shared/Avatar.vue';

const props = defineProps({ partner: { type: Object, default: null } });
const store = useGameStore();
const partner = computed(() => props.partner ?? store.partner);
</script>

<template>
    <span v-if="partner" class="inline-flex items-center gap-1.5">
        <Avatar v-if="partner.avatar" :avatar="partner.avatar" :name="partner.display_name" :size="1.5" />
        <span class="font-semibold text-white">{{ partner.display_name }}</span>
        <span v-if="partner.is_bot" class="rounded bg-slate-700 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-300">bot</span>
        <span v-else-if="partner.is_codename" class="rounded bg-violet-500/20 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-violet-300">codename</span>
    </span>
    <span v-else class="text-slate-400">your partner</span>
</template>
