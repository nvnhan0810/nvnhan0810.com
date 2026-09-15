/// <reference types="vite/client" />

import type { SharedPageProps } from '@/types/inertia';

declare module '@inertiajs/core' {
    interface PageProps extends SharedPageProps {}
}

declare global {
    function route(
        name?: string,
        params?: Record<string, unknown> | number | string | undefined,
        absolute?: boolean,
        config?: unknown,
    ): string;
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof route;
    }
}

interface ImportMetaEnv {
    readonly VITE_APP_NAME: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}

declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent<object, object, unknown>;
    export default component;
}

export {};
