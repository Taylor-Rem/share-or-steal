<script setup>
import { onMounted } from 'vue';
import Placeholder from '../../shared/Placeholder.vue';
import { useGameStore } from '../../shared/stores/game';
import { deviceToken, remember } from '../../shared/device';

const props = defineProps({ code: { type: String, required: true } });
const store = useGameStore();

onMounted(() => {
    remember('last_code', props.code.toUpperCase());
    // Before a phone has joined, it subscribes to the session channel by code alone.
    // Session 2 adds the join call, after which `me` is set and player.{id} is subscribed too.
    store.connect({ code: props.code, kind: 'phone', deviceToken: deviceToken() }).catch(() => {});
});
</script>

<template>
    <Placeholder title="Phone">
        <p class="mb-4 text-slate-400">device token: {{ store.identity.deviceToken }}</p>
    </Placeholder>
</template>
