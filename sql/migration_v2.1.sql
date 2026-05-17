-- ============================================================
-- Moroccan AE System — Migration v2.0 → v2.1
-- Run once: mysql -u root -p moroccan_ae < migration_v2.1.sql
-- ============================================================

-- ── 1. Logo support in ae_config ─────────────────────────────
ALTER TABLE ae_config
    ADD COLUMN IF NOT EXISTS logo_path       VARCHAR(500) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS logo_width_mm   INT          DEFAULT 40,
    -- SMTP / Email settings
    ADD COLUMN IF NOT EXISTS smtp_host       VARCHAR(255) DEFAULT '',
    ADD COLUMN IF NOT EXISTS smtp_port       INT          DEFAULT 587,
    ADD COLUMN IF NOT EXISTS smtp_user       VARCHAR(255) DEFAULT '',
    ADD COLUMN IF NOT EXISTS smtp_pass       VARCHAR(255) DEFAULT '',
    ADD COLUMN IF NOT EXISTS smtp_from_name  VARCHAR(255) DEFAULT '',
    ADD COLUMN IF NOT EXISTS smtp_from_email VARCHAR(255) DEFAULT '',
    ADD COLUMN IF NOT EXISTS smtp_encryption ENUM('tls','ssl','none') DEFAULT 'tls',
    -- Invoice/quote email templates
    ADD COLUMN IF NOT EXISTS email_invoice_subject VARCHAR(255) DEFAULT 'Votre facture {{number}}',
    ADD COLUMN IF NOT EXISTS email_invoice_body     TEXT,
    ADD COLUMN IF NOT EXISTS email_quote_subject    VARCHAR(255) DEFAULT 'Votre devis {{number}}',
    ADD COLUMN IF NOT EXISTS email_quote_body       TEXT;

-- ── 2. Bank accounts table ────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_bank_accounts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(255) NOT NULL,                         -- "CIH Principal", "CashPlus", "Caisse"
    type         ENUM('bank','ewallet','cash') DEFAULT 'bank',
    bank_name    VARCHAR(100) DEFAULT '',                       -- OCP, Attijariwafa, BMCE, CIH, CashPlus…
    rib          VARCHAR(100) DEFAULT '',
    currency     VARCHAR(10)  DEFAULT 'MAD',
    opening_balance DECIMAL(14,2) DEFAULT 0.00,
    is_default   TINYINT(1)   DEFAULT 0,
    is_active    TINYINT(1)   DEFAULT 1,
    color        VARCHAR(20)  DEFAULT '#0078d4',               -- UI badge color
    sort_order   INT          DEFAULT 0,
    notes        TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed one default account from existing bank_rib config
INSERT IGNORE INTO ae_bank_accounts (id, name, type, bank_name, rib, is_default, sort_order)
SELECT 1, 'Compte Principal', 'bank', 'Banque', bank_rib, 1, 0
FROM ae_config WHERE id = 1 AND bank_rib != '';

-- Fallback if no RIB was set
INSERT IGNORE INTO ae_bank_accounts (id, name, type, is_default, sort_order)
VALUES (1, 'Compte Principal', 'bank', 1, 0);

-- ── 3. Add account_id to bank transactions ────────────────────
ALTER TABLE ae_bank_transactions
    ADD COLUMN IF NOT EXISTS account_id      INT DEFAULT 1,
    ADD COLUMN IF NOT EXISTS transfer_to_id  INT DEFAULT NULL,  -- for inter-account transfers
    ADD COLUMN IF NOT EXISTS imported        TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS import_hash     VARCHAR(64) DEFAULT NULL;  -- dedup on CSV import

-- ── 4. Bank transfers tracking ───────────────────────────────
CREATE TABLE IF NOT EXISTS ae_bank_transfers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    from_account_id INT NOT NULL,
    to_account_id   INT NOT NULL,
    amount          DECIMAL(14,2) NOT NULL,
    transfer_date   DATE NOT NULL,
    description     VARCHAR(500) DEFAULT '',
    from_tx_id      INT DEFAULT NULL,   -- debit tx in from_account
    to_tx_id        INT DEFAULT NULL,   -- credit tx in to_account
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── 5. Email log ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_email_log (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    sent_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    to_email     VARCHAR(255) NOT NULL,
    to_name      VARCHAR(255) DEFAULT '',
    subject      VARCHAR(500) DEFAULT '',
    document_type ENUM('invoice','quote') NOT NULL,
    document_id  INT NOT NULL,
    status       ENUM('sent','failed') DEFAULT 'sent',
    error_msg    TEXT,
    message_id   VARCHAR(255) DEFAULT ''
);

-- ── 6. CSV import log ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_csv_imports (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    imported_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    account_id   INT NOT NULL,
    bank_format  VARCHAR(50) DEFAULT '',   -- OCP, Attijariwafa, BMCE, CIH, CashPlus
    filename     VARCHAR(255) DEFAULT '',
    rows_total   INT DEFAULT 0,
    rows_imported INT DEFAULT 0,
    rows_skipped INT DEFAULT 0
);

-- ── 7. ae_invoices — add activity_id column for multi-activity
ALTER TABLE ae_invoices
    ADD COLUMN IF NOT EXISTS activity_label VARCHAR(255) DEFAULT '';

ALTER TABLE ae_quotes
    ADD COLUMN IF NOT EXISTS activity_label VARCHAR(255) DEFAULT '';

-- ── 8. Uploads directory placeholder (handled in PHP) ─────────
-- Uploads land in: /uploads/logos/   (logo)
-- The .htaccess in /uploads/ must block direct PHP execution.

-- Done.
SELECT 'Migration v2.1 complete ✓' AS status;
