-- ============================================================
-- Moroccan AE System v2 — Database Schema
-- Open Source | MIT License
-- ============================================================

CREATE DATABASE IF NOT EXISTS moroccan_ae CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE moroccan_ae;

-- ── Users (authentication) ────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(100) UNIQUE NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    email           VARCHAR(255) DEFAULT '',
    login_attempts  INT DEFAULT 0,
    locked_until    DATETIME DEFAULT NULL,
    remember_token  VARCHAR(64) DEFAULT NULL,
    remember_expires DATETIME DEFAULT NULL,
    last_login      DATETIME DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Configuration ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_config (
    id                  INT PRIMARY KEY DEFAULT 1,
    owner_name          VARCHAR(255) DEFAULT '',
    email               VARCHAR(255) DEFAULT '',
    ice                 VARCHAR(50)  DEFAULT '',
    if_fiscal           VARCHAR(50)  DEFAULT '',
    tp                  VARCHAR(50)  DEFAULT '',
    cnss_phone          VARCHAR(50)  DEFAULT '',
    address             TEXT,
    bank_rib            VARCHAR(100) DEFAULT '',
    -- Professional activities (from rn.ae.gov.ma)
    activity_1          VARCHAR(255) DEFAULT '',
    activity_2          VARCHAR(255) DEFAULT '',
    activity_3          VARCHAR(255) DEFAULT '',
    -- Tax parameters
    ir_rate_services    DECIMAL(6,4) DEFAULT 0.0100,
    ir_rate_commerce    DECIMAL(6,4) DEFAULT 0.0050,
    ceiling_services    DECIMAL(12,2) DEFAULT 200000.00,
    ceiling_commerce    DECIMAL(12,2) DEFAULT 500000.00,
    alert_yellow        DECIMAL(4,2) DEFAULT 0.75,
    alert_orange        DECIMAL(4,2) DEFAULT 0.85,
    alert_red           DECIMAL(4,2) DEFAULT 0.95,
    cnss_monthly        DECIMAL(10,2) DEFAULT 100.00,
    fiscal_year         INT DEFAULT 2026,
    currency            VARCHAR(10) DEFAULT 'MAD',
    -- Quote settings
    quote_validity_days INT DEFAULT 30,
    quote_footer_text   TEXT,
    invoice_footer_text TEXT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO ae_config (id) VALUES (1);

-- ── Clients ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_clients (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255) DEFAULT '',
    phone       VARCHAR(50)  DEFAULT '',
    address     TEXT,
    ice         VARCHAR(50)  DEFAULT '',
    city        VARCHAR(100) DEFAULT '',
    category    ENUM('Service','Commerce','Industrie') DEFAULT 'Service',
    notes       TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Invoices ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_invoices (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number   VARCHAR(50) UNIQUE NOT NULL,
    client_id        INT DEFAULT NULL,
    client_name      VARCHAR(255) NOT NULL,
    invoice_date     DATE NOT NULL,
    due_date         DATE DEFAULT NULL,
    category         ENUM('Service','Commerce','Industrie') DEFAULT 'Service',
    activity         VARCHAR(255) DEFAULT '',
    amount_ht        DECIMAL(12,2) DEFAULT 0.00,
    has_tva          TINYINT(1) DEFAULT 0,
    amount_ttc       DECIMAL(12,2) DEFAULT 0.00,
    status           ENUM('Payé','En attente','Annulé') DEFAULT 'En attente',
    payment_date     DATE DEFAULT NULL,
    payment_method   ENUM('Virement','Chèque','Espèces','CB','PayPal','Mobile Money') DEFAULT NULL,
    quarter          VARCHAR(10) DEFAULT NULL,
    fiscal_year      INT DEFAULT NULL,
    from_quote_id    INT DEFAULT NULL,
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
    sort_order  INT DEFAULT 0,
    FOREIGN KEY (invoice_id) REFERENCES ae_invoices(id) ON DELETE CASCADE
);

-- ── Quotes (Devis) ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_quotes (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    quote_number        VARCHAR(50) UNIQUE NOT NULL,
    client_id           INT DEFAULT NULL,
    client_name         VARCHAR(255) NOT NULL,
    quote_date          DATE NOT NULL,
    valid_until         DATE DEFAULT NULL,
    category            ENUM('Service','Commerce','Industrie') DEFAULT 'Service',
    activity            VARCHAR(255) DEFAULT '',
    amount_ht           DECIMAL(12,2) DEFAULT 0.00,
    amount_ttc          DECIMAL(12,2) DEFAULT 0.00,
    status              ENUM('Brouillon','Envoyé','Accepté','Refusé','Expiré') DEFAULT 'Brouillon',
    converted_invoice_id INT DEFAULT NULL,
    notes               TEXT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
    sort_order  INT DEFAULT 0,
    FOREIGN KEY (quote_id) REFERENCES ae_quotes(id) ON DELETE CASCADE
);

-- ── Expenses ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_expenses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    expense_date    DATE NOT NULL,
    supplier        VARCHAR(255) DEFAULT '',
    expense_number  VARCHAR(50)  DEFAULT '',
    description     TEXT,
    category        ENUM('Fournitures','Logiciel/Abonnement','Transport','Formation','Marketing','Matériel','Téléphonie','Autres') DEFAULT 'Autres',
    amount          DECIMAL(12,2) DEFAULT 0.00,
    payment_method  ENUM('Virement','Chèque','Espèces','CB','PayPal') DEFAULT NULL,
    has_receipt     TINYINT(1) DEFAULT 0,
    fiscal_year     INT DEFAULT NULL,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Tax Payments ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_tax_payments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    payment_type ENUM('IR','CNSS') NOT NULL,
    quarter      VARCHAR(10) DEFAULT NULL,
    fiscal_year  INT DEFAULT NULL,
    amount       DECIMAL(12,2) DEFAULT 0.00,
    payment_date DATE DEFAULT NULL,
    reference    VARCHAR(100) DEFAULT '',
    notes        TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Bank Transactions ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_bank_transactions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    transaction_date DATE NOT NULL,
    description      VARCHAR(500) DEFAULT '',
    credit           DECIMAL(12,2) DEFAULT 0.00,
    debit            DECIMAL(12,2) DEFAULT 0.00,
    invoice_id       INT DEFAULT NULL,
    reconciled       TINYINT(1) DEFAULT 0,
    fiscal_year      INT DEFAULT NULL,
    notes            TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES ae_invoices(id) ON DELETE SET NULL
);

-- ── Reminders ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ae_reminders (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id       INT NOT NULL,
    reminder_1_date  DATE DEFAULT NULL,
    reminder_2_date  DATE DEFAULT NULL,
    status           ENUM('En attente','Relancé 1x','Relancé 2x','Litige','Payé','Abandonné') DEFAULT 'En attente',
    notes            TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES ae_invoices(id) ON DELETE CASCADE
);

-- ── Indexes for performance ───────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_invoices_status      ON ae_invoices(status);
CREATE INDEX IF NOT EXISTS idx_invoices_year        ON ae_invoices(fiscal_year);
CREATE INDEX IF NOT EXISTS idx_invoices_client      ON ae_invoices(client_name);
CREATE INDEX IF NOT EXISTS idx_invoices_quarter     ON ae_invoices(quarter);
CREATE INDEX IF NOT EXISTS idx_quotes_status        ON ae_quotes(status);
CREATE INDEX IF NOT EXISTS idx_quotes_client        ON ae_quotes(client_name);
CREATE INDEX IF NOT EXISTS idx_expenses_year        ON ae_expenses(fiscal_year);
CREATE INDEX IF NOT EXISTS idx_bank_year            ON ae_bank_transactions(fiscal_year);
