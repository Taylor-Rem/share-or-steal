<script setup>
import { onMounted } from 'vue';
import Placeholder from '../../shared/Placeholder.vue';
import { useGameStore } from '../../shared/stores/game';
import { recall } from '../../shared/device';

const props = defineProps({ code: { type: String, required: true } });
const store = useGameStore();

onMounted(() => {
    store.connect({ code: props.code, kind: 'director', directorKey: recall('director_key') }).catch(() => {});
});
</script>

<template>
    <Placeholder title="Director" />
</template>
