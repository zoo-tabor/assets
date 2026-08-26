-- 001: Zakladni schema v1 (viz PLAN.md kap. 3)
-- Ucet ma vychozi charset utf8 -> vse zakladame explicitne utf8mb4_czech_ci.

CREATE TABLE organizations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    logo_file VARCHAR(255) NULL,
    accent_color CHAR(7) NOT NULL DEFAULT '#1e7e34',
    tag_prefix VARCHAR(20) NOT NULL,
    tag_next_number INT UNSIGNED NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_org_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'admin',
    theme_pref VARCHAR(10) NOT NULL DEFAULT 'auto',
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_user_email (email),
    UNIQUE KEY uq_user_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE login_attempts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    login VARCHAR(190) NOT NULL,
    attempted_at DATETIME NOT NULL,
    KEY idx_attempts_ip (ip, attempted_at),
    KEY idx_attempts_login (login, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE locations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_loc_org (organization_id),
    CONSTRAINT fk_loc_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_cat_org (organization_id),
    CONSTRAINT fk_cat_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE departments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_dep_org (organization_id),
    CONSTRAINT fk_dep_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE persons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    employee_id VARCHAR(50) NULL,
    title VARCHAR(100) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(50) NULL,
    location_id INT UNSIGNED NULL,
    department_id INT UNSIGNED NULL,
    notes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_person_org (organization_id),
    CONSTRAINT fk_person_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE RESTRICT,
    CONSTRAINT fk_person_loc FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE SET NULL,
    CONSTRAINT fk_person_dep FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE assets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    tag_id VARCHAR(50) NOT NULL,
    description VARCHAR(255) NOT NULL,
    brand VARCHAR(100) NULL,
    model VARCHAR(100) NULL,
    serial_no VARCHAR(100) NULL,
    purchase_date DATE NULL,
    cost DECIMAL(12,2) NULL,
    purchased_from VARCHAR(150) NULL,
    location_id INT UNSIGNED NULL,
    category_id INT UNSIGNED NULL,
    department_id INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'available',
    assigned_person_id INT UNSIGNED NULL,
    os_type VARCHAR(30) NULL,
    os_sn VARCHAR(100) NULL,
    office VARCHAR(30) NULL,
    office_sn VARCHAR(100) NULL,
    note TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_asset_tag (tag_id),
    KEY idx_asset_org (organization_id),
    KEY idx_asset_status (organization_id, status),
    CONSTRAINT fk_asset_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE RESTRICT,
    CONSTRAINT fk_asset_loc FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE RESTRICT,
    CONSTRAINT fk_asset_cat FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_asset_dep FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE RESTRICT,
    CONSTRAINT fk_asset_person FOREIGN KEY (assigned_person_id) REFERENCES persons (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE custom_fields (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('text','number','date','select','bool') NOT NULL DEFAULT 'text',
    options TEXT NULL,
    sort INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_cf_org (organization_id),
    CONSTRAINT fk_cf_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE asset_custom_values (
    asset_id INT UNSIGNED NOT NULL,
    custom_field_id INT UNSIGNED NOT NULL,
    value TEXT NULL,
    PRIMARY KEY (asset_id, custom_field_id),
    CONSTRAINT fk_acv_asset FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE CASCADE,
    CONSTRAINT fk_acv_field FOREIGN KEY (custom_field_id) REFERENCES custom_fields (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE asset_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    asset_id INT UNSIGNED NOT NULL,
    type VARCHAR(20) NOT NULL,
    person_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    event_date DATETIME NOT NULL,
    due_date DATE NULL,
    note TEXT NULL,
    data TEXT NULL,
    KEY idx_event_asset (asset_id, event_date),
    KEY idx_event_due (due_date),
    CONSTRAINT fk_event_asset FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_person FOREIGN KEY (person_id) REFERENCES persons (id) ON DELETE SET NULL,
    CONSTRAINT fk_event_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE asset_photos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    asset_id INT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_photo_asset (asset_id),
    CONSTRAINT fk_photo_asset FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE asset_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    asset_id INT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    uploaded_by INT UNSIGNED NULL,
    uploaded_at DATETIME NOT NULL,
    KEY idx_doc_asset (asset_id),
    CONSTRAINT fk_doc_asset FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE CASCADE,
    CONSTRAINT fk_doc_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE asset_links (
    parent_asset_id INT UNSIGNED NOT NULL,
    child_asset_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (parent_asset_id, child_asset_id),
    CONSTRAINT fk_link_parent FOREIGN KEY (parent_asset_id) REFERENCES assets (id) ON DELETE CASCADE,
    CONSTRAINT fk_link_child FOREIGN KEY (child_asset_id) REFERENCES assets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE warranties (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    asset_id INT UNSIGNED NOT NULL,
    expires_at DATE NOT NULL,
    notes TEXT NULL,
    KEY idx_warranty_asset (asset_id),
    KEY idx_warranty_expires (expires_at),
    CONSTRAINT fk_warranty_asset FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE maintenances (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    asset_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'planned',
    due_date DATE NULL,
    completed_at DATE NULL,
    cost DECIMAL(12,2) NULL,
    notes TEXT NULL,
    KEY idx_maint_asset (asset_id),
    KEY idx_maint_due (due_date),
    CONSTRAINT fk_maint_asset FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE audits (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    location_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    closed_at DATETIME NULL,
    KEY idx_audit_org (organization_id),
    CONSTRAINT fk_audit_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE RESTRICT,
    CONSTRAINT fk_audit_loc FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE audit_items (
    audit_id INT UNSIGNED NOT NULL,
    asset_id INT UNSIGNED NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'pending',
    checked_at DATETIME NULL,
    checked_by INT UNSIGNED NULL,
    PRIMARY KEY (audit_id, asset_id),
    CONSTRAINT fk_ai_audit FOREIGN KEY (audit_id) REFERENCES audits (id) ON DELETE CASCADE,
    CONSTRAINT fk_ai_asset FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE CASCADE,
    CONSTRAINT fk_ai_user FOREIGN KEY (checked_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
