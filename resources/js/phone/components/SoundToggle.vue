<script setup>
import { useAudio } from '../../shared/audio';

const audio = useAudio();
</script>

<template>
    <button
        type="button"
        class="flex h-10 w-10 touch-manipulation items-center justify-center rounded-full bg-slate-800 text-lg transition active:scale-90 motion-reduce:transition-none"
        :aria-pressed="audio.enabled.value"
        :aria-label="audio.enabled.value ? 'Sound on' : 'Sound off'"
        @click="audio.toggle()"
    >
        <span aria-hidden="true">{{ audio.enabled.value ? '🔊' : '🔇' }}</span>
    </button>
</template>
