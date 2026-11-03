-- Restore the previous document schema for rollback only.
CREATE TABLE wallets_accounts (
 user_id UUID PRIMARY KEY REFERENCES identity_users(id) ON DELETE CASCADE,
 payload JSONB NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
INSERT INTO wallets_accounts(user_id,payload)
SELECT p.user_id,jsonb_build_object(
 'next_id',p.next_id,
 'wallets',COALESCE((SELECT jsonb_agg(to_jsonb(t)-'user_id' ORDER BY id) FROM wallets t WHERE t.user_id=p.user_id),'[]'::jsonb),
 'templates',COALESCE((SELECT jsonb_agg(to_jsonb(t)-'user_id' ORDER BY id) FROM transaction_templates t WHERE t.user_id=p.user_id),'[]'::jsonb),
 'transactions',COALESCE((SELECT jsonb_agg(to_jsonb(t)-'user_id' ORDER BY id) FROM transactions t WHERE t.user_id=p.user_id),'[]'::jsonb),
 'recurring',COALESCE((SELECT jsonb_agg(to_jsonb(t)-'user_id' ORDER BY id) FROM recurring_items t WHERE t.user_id=p.user_id),'[]'::jsonb),
 'reminders',COALESCE((SELECT jsonb_agg(to_jsonb(t)-'user_id' ORDER BY id) FROM recurring_occurrences t WHERE t.user_id=p.user_id),'[]'::jsonb),
 'holidays',COALESCE((SELECT jsonb_agg(to_jsonb(h) ORDER BY id) FROM holidays h),'[]'::jsonb),
 'settings',COALESCE((SELECT jsonb_object_agg(key,CASE WHEN key='telegram_enabled' THEN to_jsonb(value IN ('true','1')) WHEN key='recurring_alert_days' THEN to_jsonb(value::int) ELSE to_jsonb(value) END) FROM user_settings WHERE user_id=p.user_id),'{}'::jsonb),
 'goal',(SELECT (to_jsonb(g)-'user_id') || jsonb_build_object('settlement_fees',COALESCE((SELECT jsonb_agg(to_jsonb(f)-'user_id' ORDER BY year) FROM settlement_fees f WHERE f.user_id=g.user_id),'[]'::jsonb)) FROM debt_goals g WHERE g.user_id=p.user_id),
 'loans',COALESCE((SELECT jsonb_agg((to_jsonb(l)-'user_id') || jsonb_build_object(
    'schedule',COALESCE((SELECT jsonb_agg(to_jsonb(r)-'user_id' ORDER BY month_index) FROM loan_periods r WHERE r.user_id=l.user_id AND r.loan_id=l.id),'[]'::jsonb),
    'custom_schedule',CASE WHEN l.interest_calculation_method='custom' THEN (SELECT jsonb_agg(to_jsonb(r)-'user_id' ORDER BY month_index) FROM loan_periods r WHERE r.user_id=l.user_id AND r.loan_id=l.id) ELSE NULL END,
    'payments',COALESCE((SELECT jsonb_agg((to_jsonb(r)-'user_id')||jsonb_build_object('paid_at',to_char(r.paid_at,'DD/MM/YYYY')) ORDER BY id) FROM loan_payments r WHERE r.user_id=l.user_id AND r.loan_id=l.id),'[]'::jsonb)
 ) ORDER BY id) FROM loans l WHERE l.user_id=p.user_id),'[]'::jsonb)
) FROM finance_profiles p;
DROP TABLE settlement_fees, debt_goals, transactions, wallet_transfers, recurring_occurrences, recurring_items, loan_payments, loan_periods, loans, transaction_templates, wallets, user_settings, holidays, finance_profiles;
