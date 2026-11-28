-- Bank account details for `bank` wallets: VietQR bank BIN/code, account number and holder.
ALTER TABLE wallets
 ADD COLUMN "bank_bin" text CHECK ("bank_bin" ~ '^[0-9]{6}$'),
 ADD COLUMN "bank_code" text CHECK (char_length("bank_code") <= 20),
 ADD COLUMN "account_number" text CHECK ("account_number" ~ '^[0-9A-Za-z]{1,19}$'),
 ADD COLUMN "account_holder" text CHECK (char_length("account_holder") <= 100),
 ADD COLUMN "bank_branch" text CHECK (char_length("bank_branch") <= 200);
