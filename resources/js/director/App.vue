<script setup>
import { useDirector } from './useDirector';
import Gate from './components/Gate.vue';

const director = useDirector();
</script>

<template>
    <Gate v-if="!director.authed.value" />
    <router-view v-else />
</template>
