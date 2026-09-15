import { describe, expect, it } from 'vitest';
import { formatMoney, parseMoney } from '@/domain/money/money';
import { formatDateVi, greetingLabel, todayVi } from '@/domain/time/date-vi';
import { TxType, TX_TYPE_LABELS } from '@/domain/catalog/transaction-type';
import { WalletType, WALLET_TYPE_LABELS } from '@/domain/catalog/wallet-type';

describe('formatMoney', () => {
    it('should_format_vnd_with_suffix_by_default', () => {
        expect(formatMoney(1_500_000)).toBe('1.500.000 ₫');
    });

    it('should_format_without_suffix_when_requested', () => {
        expect(formatMoney(1000, false)).toBe('1.000');
    });

    it('should_treat_invalid_as_zero', () => {
        expect(formatMoney(undefined)).toBe('0 ₫');
    });
});

describe('parseMoney', () => {
    it('should_strip_non_digits', () => {
        expect(parseMoney('1.234.567')).toBe(1234567);
    });

    it('should_return_zero_for_empty', () => {
        expect(parseMoney('')).toBe(0);
        expect(parseMoney(null)).toBe(0);
    });
});

describe('formatDateVi', () => {
    it('should_format_iso_to_dmy', () => {
        expect(formatDateVi('2026-08-31')).toBe('31/08/2026');
    });

    it('should_pass_through_unparseable_dmy_shaped_strings', () => {
        // Valid calendar dates are normalized; only non-Date strings matching d/m/Y pass through.
        expect(formatDateVi('32/13/2026')).toBe('32/13/2026');
    });

    it('should_format_php_datetime_json_object', () => {
        expect(
            formatDateVi({
                date: '2026-08-05 00:00:00.000000',
                timezone_type: 3,
                timezone: 'UTC',
            }),
        ).toBe('05/08/2026');
    });
});

describe('greetingLabel', () => {
    it('should_return_morning_before_noon', () => {
        expect(greetingLabel(new Date('2026-08-31T09:00:00'))).toBe('Chào buổi sáng');
    });

    it('should_return_evening_after_18', () => {
        expect(greetingLabel(new Date('2026-08-31T19:00:00'))).toBe('Chào buổi tối');
    });
});

describe('catalog labels', () => {
    it('should_expose_wallet_and_tx_labels', () => {
        expect(WALLET_TYPE_LABELS[WalletType.Cash]).toBe('Tiền mặt');
        expect(TX_TYPE_LABELS[TxType.Expense]).toBe('Chi');
    });
});

describe('todayVi', () => {
    it('should_return_non_empty_dmy', () => {
        expect(todayVi()).toMatch(/^\d{2}\/\d{2}\/\d{4}$/);
    });
});
