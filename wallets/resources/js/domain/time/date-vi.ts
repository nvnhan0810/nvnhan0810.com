const DMY_RE = /^\d{1,2}\/\d{1,2}\/\d{4}$/;

type PhpDateObject = {
    date?: unknown;
};

function unwrapPhpDate(value: unknown): unknown {
    if (value && typeof value === 'object' && 'date' in (value as PhpDateObject)) {
        return (value as PhpDateObject).date;
    }
    return value;
}

export function formatDateVi(value: unknown): string {
    if (!value) {
        return '';
    }

    const raw = unwrapPhpDate(value);
    if (raw instanceof Date) {
        return formatParts(raw);
    }

    if (typeof raw === 'string') {
        if (DMY_RE.test(raw)) {
            return raw;
        }
        const d = new Date(raw.includes(' ') ? raw.replace(' ', 'T') : raw);
        if (!Number.isNaN(d.getTime())) {
            return formatParts(d);
        }
        return raw;
    }

    if (typeof raw === 'object' && raw !== null) {
        return '';
    }

    const d = new Date(String(raw));
    if (Number.isNaN(d.getTime())) {
        return String(raw);
    }
    return formatParts(d);
}

function formatParts(d: Date): string {
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    return `${dd}/${mm}/${d.getFullYear()}`;
}

export function todayVi(): string {
    return formatDateVi(new Date());
}

export function greetingLabel(now: Date = new Date()): string {
    const h = now.getHours();
    if (h < 12) {
        return 'Chào buổi sáng';
    }
    if (h < 18) {
        return 'Chào buổi chiều';
    }
    return 'Chào buổi tối';
}
