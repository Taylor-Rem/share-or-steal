<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import QRCode from 'qrcode';
import { useGameStore } from '../../shared/stores/game';
import { gsap, dur } from '../anim';
import { clip } from '../logic';

const store = useGameStore();
const joinUrl = computed(() => `${window.location.origin}/`);
const qr = ref('');
onMounted(async () => {
    try {
        qr.value = await QRCode.toDataURL(joinUrl.value, { margin: 1, width: 640, color: { dark: '#0f172a', light: '#ffffff' } });
    } catch {
        qr.value = '';
    }
});

const anonymous = computed(() => store.state?.mode === 'anonymous');
const count = computed(() => store.state?.player_count ?? store.players.length);
const locked = computed(() => store.status && store.status !== 'lobby');

// Each new avatar pops in.
const list = ref(null);
watch(
    () => store.players.length,
    async () => {
        await Promise.resolve();
        const last = list.value?.lastElementChild;
        if (last) gsap.from(last, { scale: 0.3, opacity: 0, duration: dur(0.5), ease: 'back.out(2)' });
    },
);
</script>

<template>
    <main class="grid h-full grid-cols-[1.1fr_1fr] gap-12 px-20 py-14">
        <section class="flex flex-col justify-center gap-8">
            <div>
                <p class="text-3xl font-semibold uppercase tracking-[0.3em] text-slate-400">Share or Steal</p>
                <p class="mt-4 text-4xl text-slate-300">Go to <span class="font-mono text-emerald-300">{{ joinUrl.replace(/^https?:\/\//, '') }}</span> and enter</p>
                <p class="mt-2 font-mono text-[11rem] font-black leading-none tracking-[0.25em] text-white">{{ store.code }}</p>
            </div>
            <div class="flex items-center gap-8">
                <img v-if="qr" :src="qr" alt="QR code for the join link" class="h-64 w-64 rounded-2xl bg-white p-2" />
                <div>
                    <p class="text-7xl font-black tabular-nums">{{ count }}</p>
                    <p class="text-2xl text-slate-400">{{ count === 1 ? 'player' : 'players' }} in</p>
                    <p v-if="locked" class="mt-3 rounded-full bg-rose-500/20 px-4 py-1 text-xl text-rose-200">Lobby locked</p>
                    <p v-else class="mt-3 text-xl text-slate-500">Waiting for the director…</p>
                </div>
            </div>
        </section>

        <section class="flex items-center">
            <p v-if="anonymous" class="w-full rounded-3xl bg-violet-500/10 px-10 py-12 text-center text-4xl leading-relaxed text-violet-100">
                Anonymous game.<br /><span class="text-2xl text-violet-300">No names on this screen. Partners appear as codenames.</span>
            </p>
            <ul v-else ref="list" class="flex max-h-full flex-wrap content-start gap-3 overflow-hidden">
                <li v-for="p in store.players" :key="p.id" class="max-w-[16rem] truncate rounded-full bg-slate-800 px-6 py-3 text-3xl font-semibold" :title="p.username">{{ clip(p.username, 18) }}</li>
            </ul>
        </section>
    </main>
</template>
