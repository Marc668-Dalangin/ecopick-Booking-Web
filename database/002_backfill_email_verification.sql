-- Backfill email verification for existing seller and junkshop accounts.
START TRANSACTION;

UPDATE accounts
SET is_email_verified = 1
WHERE account_role IN ('seller', 'junkshop')
  AND (is_email_verified = 0 OR is_email_verified IS NULL);

COMMIT;