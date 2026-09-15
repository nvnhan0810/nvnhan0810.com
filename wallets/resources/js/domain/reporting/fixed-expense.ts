export const FixedExpenseGranularity = {
    Week: 'week',
    Month: 'month',
    Year: 'year',
    Custom: 'custom',
} as const;

export type FixedExpenseGranularityId =
    (typeof FixedExpenseGranularity)[keyof typeof FixedExpenseGranularity];

export const FIXED_EXPENSE_DEFAULT_DURATION = 5;
export const FIXED_EXPENSE_DEFAULT_CUSTOM_DAYS = 30;

export const FixedExpenseSource = {
    Recurring: 'recurring',
    Loan: 'loan',
} as const;

export function isCustomGranularity(value: string): boolean {
    return value === FixedExpenseGranularity.Custom;
}

export function startInputType(granularity: string): 'date' | 'month' {
    return granularity === FixedExpenseGranularity.Month ||
        granularity === FixedExpenseGranularity.Year
        ? 'month'
        : 'date';
}

/** Normalize picker value to Y-m-d for the query string. */
export function normalizeStartParam(granularity: string, raw: string): string {
    if (!raw) {
        return raw;
    }
    if (granularity === FixedExpenseGranularity.Month || granularity === FixedExpenseGranularity.Year) {
        if (/^\d{4}-\d{2}$/.test(raw)) {
            return `${raw}-01`;
        }
    }
    return raw.slice(0, 10);
}

export function startInputValue(granularity: string, start: string): string {
    if (!start) {
        return '';
    }
    if (granularity === FixedExpenseGranularity.Month || granularity === FixedExpenseGranularity.Year) {
        return start.slice(0, 7);
    }
    return start.slice(0, 10);
}
