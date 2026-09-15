import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const prefix = (env.APP_PATH_PREFIX || 'flc').replace(/^\/+|\/+$/g, '');
    const base = prefix ? `/${prefix}/` : '/';

    return {
        base,
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
                fonts: [
                    bunny('Instrument Sans', {
                        weights: [400, 500, 600, 700],
                        optimizedFallbacks: false,
                    }),
                ],
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            tailwindcss(),
        ],
        resolve: {
            alias: {
                '@': '/resources/js',
            },
        },
        define: {
            'import.meta.env.VITE_APP_PATH_PREFIX': JSON.stringify(env.APP_PATH_PREFIX || 'flc'),
            'import.meta.env.VITE_API_PATH_PREFIX': JSON.stringify(env.API_PATH_PREFIX || 'flc/api'),
        },
        server: {
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
