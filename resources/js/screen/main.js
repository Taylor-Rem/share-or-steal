import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import Screen from './pages/Screen.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [{ path: '/screen/:code', component: Screen, props: true }],
});

createApp(App).use(createPinia()).use(router).mount('#app');
