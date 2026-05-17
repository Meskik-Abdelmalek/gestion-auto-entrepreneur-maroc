-- ============================================================
-- Moroccan AE System — Safe Migration v2.0 → v2.1
-- ============================================================
-- Run this on an EXISTING v2.0 install to add v2.1 features.
-- Safe to run multiple times — uses IF NOT EXISTS everywhere.
-- ============================================================

-- ── 1. ae_config — add v2.1 columns ──────────────────────────
ALTER TABLE ae_config
    ADD COLUMN IF NOT EXISTS logo_path            VARCHAR(500)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS logo_width_mm        INT           DEFAULT 40,
    ADD COLUMN IF NOT EXISTS smtp_host            VARCHAR(255)  DEFAULT '',
    ADD COLUMN IF NOT EXISTS smtp_port            INT           DEFAULT 587,
    ADD COLUMN IF NOT EXISTS smtp_user            VARCHAR(255)  DEFAULT '',
    ADD COLUMN IF NOT EXISTS smtp_pass            VARCHAR(255)  DEFAULT '',
    ADD COLUMN IF NOT EXISTS smtp_from_name       VARCHAR(255)  DEFAULT '',
    ADD COLUMN IF NOT EXISTS smtp_from_email      VARCHAR(255)  DEFAULT '',
    ADD COLUMN IF NOT EXISTS smtp_encryption      VARCHAR(10)   DEFAULT 'tls',
    ADD COLUMN IF NOT EXISTS email_invoice_subject VARCHAR(255) DEFAULT 'Votre facture {{number}}',
    ADD COLUMN IF NOT EXISTS email_invoice_body   TEXT,
    ADD COLUMN IF NOT EXISTS email_quote_subject  VARCHAR(255)  DEFAULT 'Votre devis {{number}}',
    ADD COLUMN IF NOT EXISTS email_quote_body     TEXT;

-- ── 2. ae_invoices — add activity_label ──────────────────────
ALTER TABLE ae_invoices
    ADD COLUMN IF NOT EXISTS activity_label VARCHAR(255) DEFAULT '';

-- ── 3. ae_quotes — add activity_label ────────────────────────
ALTER TABLE ae_quotes
    ADD COLUMN IF NOT EXISTS activity_label VARCHAR(255) DEFAULT '';

-- ── 4. ae_bank_accounts (new table) ──────────────────────────
CREATE TABLE IF NOT EXISTS ae_bank_accounts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255)  NOT NULL,
    type            VARCHAR(20)   DEFAULT 'bank',
    bank_name       VARCHAR(100)  DEFAULT '',
    rib             VARCHAR(100)  DEFAULT '',
    currency        VARCHAR(10)   DEFAULT 'MAD',
    opening_balance DECIMAL(14,2) DEFAULT 0.00,
    is_default      TINYINT(1)    DEFAULT 0,
    is_active       TINYINT(1)    DEFAULT 1,
    color           VARCHAR(20)   DEFAULT '#0078d4',
    sort_order      INT           DEFAULT 0,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Seed default account from existing bank_rib if none exist
INSERT IGNORE INTO ae_bank_accounts (id, name, type, is_default, sort_order)
SELECT 1, 'Compte Principal', 'bank', 1, 0
FROM ae_config WHERE id = 1
  AND NOT EXISTS (SELECT 1 FROM ae_bank_accounts WHERE id = 1);

-- ── 5. ae_bank_transactions — add v2.1 columns ───────────────
ALTER TABLE ae_bank_transactions
    ADD COLUMN IF NOT EXISTS account_id  INT         DEFAULT 1,
    ADD COLUMN IF NOT EXISTS imported    TINYINT(1)  DEFAULT 0,
    ADD COLUMN IF NOT EXISTS import_hash VARCHAR(64) DEFAULT NULL;

-- ── 6. ae_bank_transfers (new table) ─────────────────────────
CREATE TABLE IF NOT EXISTS ae_bank_transfers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    from_account_id INT           NOT NULL,
    to_account_id   INT           NOT NULL,
    amount          DECIMAL(14,2) NOT NULL,
    transfer_date   DATE          NOT NULL,
    description     VARCHAR(500)  DEFAULT '',
    from_tx_id      INT           DEFAULT NULL,
    to_tx_id        INT           DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── 7. ae_email_log (new table) ──────────────────────────────
CREATE TABLE IF NOT EXISTS ae_email_log (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    sent_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    to_email      VARCHAR(255) NOT NULL,
    to_name       VARCHAR(255) DEFAULT '',
    subject       VARCHAR(500) DEFAULT '',
    document_type VARCHAR(20)  NOT NULL,
    document_id   INT          NOT NULL,
    status        VARCHAR(10)  DEFAULT 'sent',
    error_msg     TEXT,
    message_id    VARCHAR(255) DEFAULT ''
);

-- ── 8. ae_csv_imports (new table) ────────────────────────────
CREATE TABLE IF NOT EXISTS ae_csv_imports (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    imported_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    account_id    INT          NOT NULL DEFAULT 1,
    bank_format   VARCHAR(50)  DEFAULT '',
    filename      VARCHAR(255) DEFAULT '',
    rows_total    INT          DEFAULT 0,
    rows_imported INT          DEFAULT 0,
    rows_skipped  INT          DEFAULT 0
);

-- ── 9. New indexes ────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_bank_account  ON ae_bank_transactions(account_id);
CREATE INDEX IF NOT EXISTS idx_bank_hash     ON ae_bank_transactions(import_hash);
CREATE INDEX IF NOT EXISTS idx_email_log_doc ON ae_email_log(document_type, document_id);

-- ── 10. transaction_type column (v2.1 informal/declared logic) ─
ALTER TABLE ae_bank_transactions
    ADD COLUMN IF NOT EXISTS transaction_type VARCHAR(20) DEFAULT 'other';

-- Backfill: already-reconciled transactions → declared
UPDATE ae_bank_transactions SET transaction_type='declared'
WHERE invoice_id IS NOT NULL AND (transaction_type IS NULL OR transaction_type='other');

-- Backfill: imported transactions → keep as 'other' for manual review
-- Backfill: debit-only with no invoice → expense
UPDATE ae_bank_transactions SET transaction_type='expense'
WHERE debit > 0 AND credit = 0 AND invoice_id IS NULL AND (transaction_type IS NULL OR transaction_type='other');

-- ── 11. Rename 'informal' → 'hors_facture' (neutral language) ─
UPDATE ae_bank_transactions
SET transaction_type = 'hors_facture'
WHERE transaction_type = 'informal';
