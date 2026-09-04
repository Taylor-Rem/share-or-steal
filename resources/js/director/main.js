import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import Home from './pages/Home.vue';
import Session from './pages/Session.vue';
import Analysis from './pages/Analysis.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/director', component: Home },
        { path: '/director/:code', component: Session, props: true },
        { path: '/director/:code/analysis', component: Analysis, props: true },
    ],
});

createApp(App).use(createPinia()).use(router).mount('#app');
