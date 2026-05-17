-- ============================================================
-- Moroccan AE System v2.1 — Complete Database Schema
-- ============================================================
-- Fresh install : mysql -u root -p your_db < sql/schema.sql
-- Existing v2.0 : mysql -u root -p your_db < sql/migrate_v2.1_safe.sql
-- ============================================================

-- ── Users ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_users (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    username         VARCHAR(100) UNIQUE NOT NULL,
    password_hash    VARCHAR(255) NOT NULL,
    email            VARCHAR(255) DEFAULT '',
    login_attempts   INT DEFAULT 0,
    locked_until     DATETIME DEFAULT NULL,
    remember_token   VARCHAR(64) DEFAULT NULL,
    remember_expires DATETIME DEFAULT NULL,
    last_login       DATETIME DEFAULT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Configuration (v2.1 — all columns) ───────────────────────
CREATE TABLE IF NOT EXISTS ae_config (
    id                  INT PRIMARY KEY DEFAULT 1,

    -- Identity
    owner_name          VARCHAR(255) DEFAULT '',
    email               VARCHAR(255) DEFAULT '',
    ice                 VARCHAR(50)  DEFAULT '',
    if_fiscal           VARCHAR(50)  DEFAULT '',
    tp                  VARCHAR(50)  DEFAULT '',
    cnss_phone          VARCHAR(50)  DEFAULT '',
    address             TEXT,
    bank_rib            VARCHAR(100) DEFAULT '',

    -- Activities
    activity_1          VARCHAR(255) DEFAULT '',
    activity_2          VARCHAR(255) DEFAULT '',
    activity_3          VARCHAR(255) DEFAULT '',

    -- Tax
    ir_rate_services    DECIMAL(6,4)  DEFAULT 0.0100,
    ir_rate_commerce    DECIMAL(6,4)  DEFAULT 0.0050,
    ceiling_services    DECIMAL(12,2) DEFAULT 200000.00,
    ceiling_commerce    DECIMAL(12,2) DEFAULT 500000.00,
    alert_yellow        DECIMAL(4,2)  DEFAULT 0.75,
    alert_orange        DECIMAL(4,2)  DEFAULT 0.85,
    alert_red           DECIMAL(4,2)  DEFAULT 0.95,
    cnss_monthly        DECIMAL(10,2) DEFAULT 100.00,
    fiscal_year         INT           DEFAULT 2026,
    currency            VARCHAR(10)   DEFAULT 'MAD',

    -- Documents
    quote_validity_days INT  DEFAULT 30,
    quote_footer_text   TEXT,
    invoice_footer_text TEXT,

    -- v2.1 Logo
    logo_path           VARCHAR(500)  DEFAULT NULL,
    logo_width_mm       INT           DEFAULT 40,

    -- v2.1 SMTP
    smtp_host           VARCHAR(255)  DEFAULT '',
    smtp_port           INT           DEFAULT 587,
    smtp_user           VARCHAR(255)  DEFAULT '',
    smtp_pass           VARCHAR(255)  DEFAULT '',
    smtp_from_name      VARCHAR(255)  DEFAULT '',
    smtp_from_email     VARCHAR(255)  DEFAULT '',
    smtp_encryption     VARCHAR(10)   DEFAULT 'tls',

    -- v2.1 Email templates
    email_invoice_subject VARCHAR(255) DEFAULT 'Votre facture {{number}}',
    email_invoice_body    TEXT,
    email_quote_subject   VARCHAR(255) DEFAULT 'Votre devis {{number}}',
    email_quote_body      TEXT,

    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO ae_config (id) VALUES (1);

-- ── Clients ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_clients (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    email      VARCHAR(255) DEFAULT '',
    phone      VARCHAR(50)  DEFAULT '',
    address    TEXT,
    ice        VARCHAR(50)  DEFAULT '',
    city       VARCHAR(100) DEFAULT '',
    category   VARCHAR(50)  DEFAULT 'Service',
    notes      TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Invoices ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_invoices (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number   VARCHAR(50)   UNIQUE NOT NULL,
    client_id        INT           DEFAULT NULL,
    client_name      VARCHAR(255)  NOT NULL,
    invoice_date     DATE          NOT NULL,
    due_date         DATE          DEFAULT NULL,
    category         VARCHAR(50)   DEFAULT 'Service',
    activity         VARCHAR(255)  DEFAULT '',
    activity_label   VARCHAR(255)  DEFAULT '',
    amount_ht        DECIMAL(12,2) DEFAULT 0.00,
    has_tva          TINYINT(1)    DEFAULT 0,
    amount_ttc       DECIMAL(12,2) DEFAULT 0.00,
    status           VARCHAR(20)   DEFAULT 'En attente',
    payment_date     DATE          DEFAULT NULL,
    payment_method   VARCHAR(50)   DEFAULT NULL,
    quarter          VARCHAR(10)   DEFAULT NULL,
    fiscal_year      INT           DEFAULT NULL,
    from_quote_id    INT           DEFAULT NULL,
    notes            TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES ae_clients(id) ON DELETE SET NULL
);

-- ── Invoice Lines ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_invoice_lines (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id  INT NOT NULL,
    description VARCHAR(500) DEFAULT '',
    quantity    DECIMAL(10,2) DEFAULT 1.00,
    unit_price  DECIMAL(12,2) DEFAULT 0.00,
    amount      DECIMAL(12,2) DEFAULT 0.00,
    sort_order  INT           DEFAULT 0,
    FOREIGN KEY (invoice_id) REFERENCES ae_invoices(id) ON DELETE CASCADE
);

-- ── Quotes ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_quotes (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    quote_number         VARCHAR(50)   UNIQUE NOT NULL,
    client_id            INT           DEFAULT NULL,
    client_name          VARCHAR(255)  NOT NULL,
    quote_date           DATE          NOT NULL,
    valid_until          DATE          DEFAULT NULL,
    category             VARCHAR(50)   DEFAULT 'Service',
    activity             VARCHAR(255)  DEFAULT '',
    activity_label       VARCHAR(255)  DEFAULT '',
    amount_ht            DECIMAL(12,2) DEFAULT 0.00,
    amount_ttc           DECIMAL(12,2) DEFAULT 0.00,
    status               VARCHAR(20)   DEFAULT 'Brouillon',
    converted_invoice_id INT           DEFAULT NULL,
    notes                TEXT,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES ae_clients(id) ON DELETE SET NULL
);

-- ── Quote Lines ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_quote_lines (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    quote_id    INT NOT NULL,
    description VARCHAR(500) DEFAULT '',
    quantity    DECIMAL(10,2) DEFAULT 1.00,
    unit_price  DECIMAL(12,2) DEFAULT 0.00,
    amount      DECIMAL(12,2) DEFAULT 0.00,
    sort_order  INT           DEFAULT 0,
    FOREIGN KEY (quote_id) REFERENCES ae_quotes(id) ON DELETE CASCADE
);

-- ── Expenses ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_expenses (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    expense_date   DATE NOT NULL,
    supplier       VARCHAR(255) DEFAULT '',
    expense_number VARCHAR(50)  DEFAULT '',
    description    TEXT,
    category       VARCHAR(100) DEFAULT 'Autres',
    amount         DECIMAL(12,2) DEFAULT 0.00,
    payment_method VARCHAR(50)  DEFAULT NULL,
    has_receipt    TINYINT(1)   DEFAULT 0,
    fiscal_year    INT          DEFAULT NULL,
    notes          TEXT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Tax Payments ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_tax_payments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    payment_type VARCHAR(10)   NOT NULL,
    quarter      VARCHAR(10)   DEFAULT NULL,
    fiscal_year  INT           DEFAULT NULL,
    amount       DECIMAL(12,2) DEFAULT 0.00,
    payment_date DATE          DEFAULT NULL,
    reference    VARCHAR(100)  DEFAULT '',
    notes        TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Bank Accounts (v2.1) ──────────────────────────────────────
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

INSERT IGNORE INTO ae_bank_accounts (id, name, type, is_default, sort_order)
VALUES (1, 'Compte Principal', 'bank', 1, 0);

-- ── Bank Transactions ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_bank_transactions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    account_id       INT           DEFAULT 1,
    transaction_date DATE          NOT NULL,
    description      VARCHAR(500)  DEFAULT '',
    credit           DECIMAL(12,2) DEFAULT 0.00,
    debit            DECIMAL(12,2) DEFAULT 0.00,
    invoice_id       INT           DEFAULT NULL,
    reconciled       TINYINT(1)    DEFAULT 0,
    fiscal_year      INT           DEFAULT NULL,
    imported         TINYINT(1)    DEFAULT 0,
    import_hash      VARCHAR(64)   DEFAULT NULL,
    transaction_type VARCHAR(20)   DEFAULT 'other',
    notes            TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id)  REFERENCES ae_invoices(id)      ON DELETE SET NULL,
    FOREIGN KEY (account_id)  REFERENCES ae_bank_accounts(id) ON DELETE SET DEFAULT
);

-- ── Bank Transfers (v2.1) ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_bank_transfers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    from_account_id INT           NOT NULL,
    to_account_id   INT           NOT NULL,
    amount          DECIMAL(14,2) NOT NULL,
    transfer_date   DATE          NOT NULL,
    description     VARCHAR(500)  DEFAULT '',
    from_tx_id      INT           DEFAULT NULL,
    to_tx_id        INT           DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_account_id) REFERENCES ae_bank_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (to_account_id)   REFERENCES ae_bank_accounts(id) ON DELETE CASCADE
);

-- ── Reminders ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_reminders (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id      INT NOT NULL,
    reminder_1_date DATE DEFAULT NULL,
    reminder_2_date DATE DEFAULT NULL,
    status          VARCHAR(30)  DEFAULT 'En attente',
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES ae_invoices(id) ON DELETE CASCADE
);

-- ── Email Log (v2.1) ──────────────────────────────────────────
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

-- ── CSV Import Log (v2.1) ─────────────────────────────────────
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

-- ── Indexes ───────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_invoices_status   ON ae_invoices(status);
CREATE INDEX IF NOT EXISTS idx_invoices_year     ON ae_invoices(fiscal_year);
CREATE INDEX IF NOT EXISTS idx_invoices_client   ON ae_invoices(client_name);
CREATE INDEX IF NOT EXISTS idx_invoices_quarter  ON ae_invoices(quarter);
CREATE INDEX IF NOT EXISTS idx_quotes_status     ON ae_quotes(status);
CREATE INDEX IF NOT EXISTS idx_quotes_client     ON ae_quotes(client_name);
CREATE INDEX IF NOT EXISTS idx_expenses_year     ON ae_expenses(fiscal_year);
CREATE INDEX IF NOT EXISTS idx_bank_year         ON ae_bank_transactions(fiscal_year);
CREATE INDEX IF NOT EXISTS idx_bank_account      ON ae_bank_transactions(account_id);
CREATE INDEX IF NOT EXISTS idx_bank_hash         ON ae_bank_transactions(import_hash);
CREATE INDEX IF NOT EXISTS idx_email_log_doc     ON ae_email_log(document_type, document_id);
