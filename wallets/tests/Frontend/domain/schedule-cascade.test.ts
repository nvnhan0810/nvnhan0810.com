import { describe, expect, it } from 'vitest';
import { applyCascadeInPlace, cascadeScheduleRows, ceilToTens } from '@/domain/lending/schedule-cascade';

describe('ceilToTens', () => {
    it('should_round_up_to_tens', () => {
        expect(ceilToTens(4744768.89)).toBe(4744770);
        expect(ceilToTens(1)).toBe(10);
    });
});

describe('cascadeScheduleRows', () => {
    it('should_preserve_exact_interest_entered_by_user', () => {
        const rows = cascadeScheduleRows(
            10_000_000,
            [
                {
                    month_index: 1,
                    due_date: '2026-02-05',
                    days: 31,
                    payment: 4_000_000,
                    interest: 3_061_007,
                    principal: 0,
                    fee: 11_003,
                    remaining_principal: 0,
                },
                {
                    month_index: 2,
                    due_date: '2026-03-05',
                    days: 28,
                    payment: 4_000_000,
                    interest: 200_000,
                    principal: 0,
                    fee: 11_000,
                    remaining_principal: 0,
                },
                {
                    month_index: 3,
                    due_date: '2026-04-05',
                    days: 31,
                    payment: 4_000_000,
                    interest: 100_000,
                    principal: 0,
                    fee: 11_000,
                    remaining_principal: 0,
                },
            ],
            4_000_000,
        );

        expect(rows[0]?.interest).toBe(3_061_007);
        expect(rows[0]?.fee).toBe(11_003);
        expect(rows[0]?.principal).toBe(4_000_000 - 11_003 - 3_061_007);
        expect(rows[rows.length - 1]?.remaining_principal).toBe(0);
    });

    it('should_not_overwrite_interest_or_fee_when_applying_in_place', () => {
        const rows = [
            {
                month_index: 1,
                due_date: '2026-02-05',
                days: 31,
                payment: 4_000_000,
                interest: 123_456,
                principal: 0,
                fee: 11_111,
                remaining_principal: 0,
            },
            {
                month_index: 2,
                due_date: '2026-03-05',
                days: 28,
                payment: 4_000_000,
                interest: 100_000,
                principal: 0,
                fee: 11_000,
                remaining_principal: 0,
            },
        ];
        applyCascadeInPlace(5_000_000, rows, 4_000_000);
        expect(rows[0]?.interest).toBe(123_456);
        expect(rows[0]?.fee).toBe(11_111);
        expect(rows[0]?.principal).toBe(4_000_000 - 11_111 - 123_456);
    });

    it('should_reduce_principal_when_interest_increases', () => {
        const rows = cascadeScheduleRows(
            10_000_000,
            [
                {
                    month_index: 1,
                    due_date: '2026-02-05',
                    days: 31,
                    payment: 4_000_000,
                    interest: 300_000,
                    principal: 0,
                    fee: 11_000,
                    remaining_principal: 0,
                },
                {
                    month_index: 2,
                    due_date: '2026-03-05',
                    days: 28,
                    payment: 4_000_000,
                    interest: 200_000,
                    principal: 0,
                    fee: 11_000,
                    remaining_principal: 0,
                },
                {
                    month_index: 3,
                    due_date: '2026-04-05',
                    days: 31,
                    payment: 4_000_000,
                    interest: 100_000,
                    principal: 0,
                    fee: 11_000,
                    remaining_principal: 0,
                },
            ],
            4_000_000,
        );

        expect(rows[0]?.principal).toBe(4_000_000 - 11_000 - 300_000);
        expect(rows[rows.length - 1]?.remaining_principal).toBe(0);
        expect(
            Math.round((rows[0]?.principal ?? 0) + (rows[0]?.interest ?? 0) + (rows[0]?.fee ?? 0)),
        ).toBe(rows[0]?.payment);
    });

    it('should_mutate_in_place_without_replacing_row_objects', () => {
        const rows = [
            {
                month_index: 1,
                due_date: '2026-02-05',
                days: 31,
                payment: 4_000_000,
                interest: 500_000,
                principal: 0,
                fee: 11_000,
                remaining_principal: 0,
            },
            {
                month_index: 2,
                due_date: '2026-03-05',
                days: 28,
                payment: 4_000_000,
                interest: 100_000,
                principal: 0,
                fee: 11_000,
                remaining_principal: 0,
            },
        ];
        const first = rows[0];
        applyCascadeInPlace(5_000_000, rows, 4_000_000);
        expect(rows[0]).toBe(first);
        expect(rows[0]?.interest).toBe(500_000);
        expect(rows[0]?.principal).toBe(4_000_000 - 11_000 - 500_000);
    });
});
