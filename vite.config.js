import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        vue()
    ],
    build: {
    outDir: 'assets/dist',
    rollupOptions: { input: 'resources/js/app.js' }
  },
server: {
    origin: 'http://localhost:5173',
    cors: true,
    strictPort: true,
},
});
