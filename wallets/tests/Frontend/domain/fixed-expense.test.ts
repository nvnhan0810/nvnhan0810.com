import { describe, expect, it } from 'vitest';
import {
    FixedExpenseGranularity,
    isCustomGranularity,
    normalizeStartParam,
    startInputType,
    startInputValue,
} from '@/domain/reporting/fixed-expense';

describe('fixed-expense domain', () => {
    it('detects custom granularity', () => {
        expect(isCustomGranularity(FixedExpenseGranularity.Custom)).toBe(true);
        expect(isCustomGranularity(FixedExpenseGranularity.Week)).toBe(false);
    });

    it('uses month input for month and year', () => {
        expect(startInputType(FixedExpenseGranularity.Month)).toBe('month');
        expect(startInputType(FixedExpenseGranularity.Year)).toBe('month');
        expect(startInputType(FixedExpenseGranularity.Week)).toBe('date');
        expect(startInputType(FixedExpenseGranularity.Custom)).toBe('date');
    });

    it('normalizes month picker to first day', () => {
        expect(normalizeStartParam(FixedExpenseGranularity.Month, '2026-08')).toBe('2026-08-01');
        expect(normalizeStartParam(FixedExpenseGranularity.Week, '2026-08-31')).toBe('2026-08-31');
    });

    it('formats start for input controls', () => {
        expect(startInputValue(FixedExpenseGranularity.Month, '2026-08-01')).toBe('2026-08');
        expect(startInputValue(FixedExpenseGranularity.Week, '2026-08-31')).toBe('2026-08-31');
    });
});
