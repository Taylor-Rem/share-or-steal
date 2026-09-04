<script setup>
import { onMounted } from 'vue';
import Placeholder from '../../shared/Placeholder.vue';
import { useGameStore } from '../../shared/stores/game';

const props = defineProps({ code: { type: String, required: true } });
const store = useGameStore();

onMounted(() => {
    store.connect({ code: props.code, kind: 'screen' }).catch(() => {});
});
</script>

<template>
    <Placeholder title="Big screen" />
</template>
