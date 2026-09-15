export const TxType = {
    Income: 'income',
    Expense: 'expense',
    Adjustment: 'adjustment',
    Transfer: 'transfer',
} as const;

export type TxTypeId = (typeof TxType)[keyof typeof TxType];

export const TX_TYPE_LABELS: Record<TxTypeId, string> = {
    [TxType.Income]: 'Thu',
    [TxType.Expense]: 'Chi',
    [TxType.Adjustment]: 'Cân đối',
    [TxType.Transfer]: 'Chuyển ví',
};

/** @deprecated Prefer TX_TYPE_LABELS; kept for gradual migration */
export const TX_TYPES = TX_TYPE_LABELS;
