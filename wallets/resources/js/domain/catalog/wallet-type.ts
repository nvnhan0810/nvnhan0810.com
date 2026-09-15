export const WalletType = {
    Cash: 'cash',
    Bank: 'bank',
    CreditCard: 'credit_card',
    EWallet: 'e_wallet',
} as const;

export type WalletTypeId = (typeof WalletType)[keyof typeof WalletType];

export const WALLET_TYPE_LABELS: Record<WalletTypeId, string> = {
    [WalletType.Cash]: 'Tiền mặt',
    [WalletType.Bank]: 'Tài khoản ngân hàng',
    [WalletType.CreditCard]: 'Thẻ tín dụng',
    [WalletType.EWallet]: 'Ví điện tử',
};

/** @deprecated Prefer WALLET_TYPE_LABELS; kept for gradual migration */
export const WALLET_TYPES = WALLET_TYPE_LABELS;
