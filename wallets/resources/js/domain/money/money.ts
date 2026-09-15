export function formatMoney(value: unknown, withSuffix: boolean = true): string {
    const n = Math.round(Number(value) || 0);
    const formatted = new Intl.NumberFormat('vi-VN').format(n);
    return withSuffix ? `${formatted} ₫` : formatted;
}

export function parseMoney(value: unknown): number {
    const digits = String(value ?? '').replace(/\D/g, '');
    return digits ? parseInt(digits, 10) : 0;
}
