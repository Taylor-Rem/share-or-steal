import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

// Front-end unit tests: the store's event handling, the clock, and the fixture.
// Kept apart from vite.config.js so the Laravel plugin never runs under Vitest.
export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.js'],
        clearMocks: true,
    },
});
