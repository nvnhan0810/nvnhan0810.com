/**
 * Path helpers for shared-host deployment (no Traefik strip).
 * Web: /flc/...   API: /flc/api/...
 */

function normalizePrefix(raw, fallback) {
    return String(raw ?? fallback).replace(/^\/+|\/+$/g, '');
}

function joinPrefix(prefix, path) {
    const normalized = path.startsWith('/') ? path : `/${path}`;
    return prefix === '' ? normalized : `/${prefix}${normalized}`;
}

export function appPath(path) {
    return joinPrefix(
        normalizePrefix(import.meta.env.VITE_APP_PATH_PREFIX, 'flc'),
        path,
    );
}

/** Accepts `/api/...` or `/word-chat/...` → `/flc/api/...` */
export function apiPath(path) {
    let normalized = path.startsWith('/') ? path : `/${path}`;
    if (normalized.startsWith('/api/')) {
        normalized = normalized.slice(4);
    }

    return joinPrefix(
        normalizePrefix(import.meta.env.VITE_API_PATH_PREFIX, 'flc/api'),
        normalized,
    );
}
