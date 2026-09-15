export type ScheduleRow = {
    month_index: number;
    due_date: string;
    due_date_label?: string;
    days: number | null;
    payment: number;
    interest: number;
    principal: number;
    fee: number;
    remaining_principal: number;
};

/** Used when auto-generating schedules (Home Credit formula), not when user edits. */
export function ceilToTens(amount: number): number {
    if (amount <= 0) {
        return 0;
    }
    return Math.ceil(amount / 10) * 10;
}

function asEnteredAmount(value: unknown): number {
    const n = Number(value);
    if (!Number.isFinite(n) || n < 0) {
        return 0;
    }
    return n;
}

/**
 * After editing interest/fee, recompute principal + remaining.
 * Interest/fee are kept exactly as entered (no rounding).
 */
export function cascadeScheduleRows(
    openingPrincipal: number,
    rows: ScheduleRow[],
    defaultEmi: number,
): ScheduleRow[] {
    let balance = asEnteredAmount(openingPrincipal);
    const count = rows.length;
    const rebuilt: ScheduleRow[] = [];

    for (let idx = 0; idx < count; idx++) {
        const row = rows[idx];
        if (!row) {
            continue;
        }

        const interest = asEnteredAmount(row.interest);
        const fee = asEnteredAmount(row.fee);
        const isLast = idx === count - 1;
        let principalPayment: number;
        let payment: number;

        if (isLast) {
            principalPayment = balance;
            payment = principalPayment + interest + fee;
        } else {
            const emi = asEnteredAmount(row.payment) || asEnteredAmount(defaultEmi);
            const targetPayment = emi > 0 ? emi : interest + fee;
            const allocatable = Math.max(0, targetPayment - fee);
            principalPayment = allocatable - interest;
            if (principalPayment < 0) {
                principalPayment = 0;
            }
            if (principalPayment > balance) {
                principalPayment = balance;
            }
            payment = principalPayment + interest + fee;
        }

        balance = balance - principalPayment;
        if (balance < 0) {
            balance = 0;
        }

        rebuilt.push({
            ...row,
            interest,
            fee,
            principal: principalPayment,
            payment,
            remaining_principal: balance,
        });
    }

    const lastIdx = rebuilt.length - 1;
    const last = rebuilt[lastIdx];
    if (last && last.remaining_principal !== 0) {
        const leftover = last.remaining_principal;
        const principal = last.principal + leftover;
        rebuilt[lastIdx] = {
            ...last,
            principal,
            payment: principal + last.interest + last.fee,
            remaining_principal: 0,
        };
    }

    return rebuilt;
}

/**
 * Mutate derived fields only — never overwrite interest/fee the user is typing.
 */
export function applyCascadeInPlace(
    openingPrincipal: number,
    rows: ScheduleRow[],
    defaultEmi: number,
): void {
    const next = cascadeScheduleRows(openingPrincipal, rows, defaultEmi);
    for (let i = 0; i < rows.length; i++) {
        const src = next[i];
        const dest = rows[i];
        if (!src || !dest) {
            continue;
        }
        dest.principal = src.principal;
        dest.payment = src.payment;
        dest.remaining_principal = src.remaining_principal;
    }
}
