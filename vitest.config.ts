import { fileURLToPath, URL } from 'node:url';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import { svelteTesting } from '@testing-library/svelte/vite';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [svelte(), svelteTesting()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['./resources/js/testing/setup.ts'],
        include: ['resources/js/**/*.test.ts'],
        restoreMocks: true,
        clearMocks: true,
        mockReset: true,
    },
});
