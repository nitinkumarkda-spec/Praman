-- =====================================================================
-- PRAMAAN — Property Records & Auction-readiness Multi-section Assessment Network
-- Kota Development Authority
--
-- Database schema (MySQL 8 / MariaDB 10.4+, InnoDB, utf8mb4).
-- Fresh install: run  php database/install.php  (drops + recreates `pramaan_db`
-- and loads the sample data), or import this file and then run the seeder.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+05:30';
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS pramaan_db;
CREATE DATABASE pramaan_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pramaan_db;

-- ---------------------------------------------------------------------
-- 1. Access: roles (verification sections) and users
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    role_id       INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    role_name     VARCHAR(100)      NOT NULL,
    verification_form ENUM('GENERAL','SALE','AUCTION','SITE') NOT NULL DEFAULT 'GENERAL',  -- the check this section fills
    role_name_hi  VARCHAR(100)      NULL,
    description   VARCHAR(255)      NULL,
    sla_days      SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    is_mandatory  TINYINT(1)        NOT NULL DEFAULT 1,
    sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active     TINYINT(1)        NOT NULL DEFAULT 1,
    created_by    INT UNSIGNED      NULL,
    created_at    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by    INT UNSIGNED      NULL,
    updated_at    DATETIME          NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id),
    UNIQUE KEY uq_roles_name (role_name),
    KEY ix_roles_active (is_active, sort_order),
    CONSTRAINT ck_roles_sla CHECK (sla_days BETWEEN 1 AND 365)
) ENGINE=InnoDB;

CREATE TABLE users (
    user_id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name                 VARCHAR(120)  NOT NULL,
    mobile               CHAR(10)      NOT NULL,
    email                VARCHAR(150)  NULL DEFAULT NULL,
    password_hash        VARCHAR(255)  NOT NULL,
    role_id              INT UNSIGNED  NULL,
    is_active            TINYINT(1)    NOT NULL DEFAULT 1,
    must_change_password TINYINT(1)    NOT NULL DEFAULT 0,
    failed_attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until         DATETIME      NULL,
    last_login_at        DATETIME      NULL,
    last_login_ip        VARCHAR(45)   NULL,
    password_changed_at  DATETIME      NULL,
    created_by           INT UNSIGNED  NULL,
    created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by           INT UNSIGNED  NULL,
    updated_at           DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_mobile (mobile),
    UNIQUE KEY uq_users_email (email),
    KEY ix_users_role (role_id, is_active),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (role_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
    attempt_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)     NOT NULL,
    ip_address    VARCHAR(45)     NOT NULL,
    success       TINYINT(1)      NOT NULL DEFAULT 0,
    attempted_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attempt_id),
    KEY ix_login_user (username, success, attempted_at),   -- unlock: failures of one user
    KEY ix_login_ip (ip_address, success, attempted_at),   -- brute force: failures per IP in the window
    KEY ix_login_time (attempted_at)                       -- 90-day prune + Super Admin insights
) ENGINE=InnoDB;

-- what each role may do (Admin → Permissions); codes are listed in app/Core/Permissions.php
CREATE TABLE role_permissions (
    grant_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id     INT UNSIGNED NOT NULL,
    permission  VARCHAR(60)  NOT NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (grant_id),
    UNIQUE KEY uq_role_permissions (role_id, permission),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (role_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- one user's own exceptions to the permissions of the panel (Super Admin → User Master → Permissions)
CREATE TABLE user_permissions (
    override_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    permission  VARCHAR(60)  NOT NULL,
    effect      ENUM('ALLOW','DENY') NOT NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (override_id),
    UNIQUE KEY uq_user_permissions (user_id, permission),
    CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. Masters (Super Admin)
-- ---------------------------------------------------------------------
CREATE TABLE areas (
    area_id      INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    area_name    VARCHAR(60)       NOT NULL,
    name_hi      VARCHAR(60)       NULL,
    description  VARCHAR(150)      NULL,
    sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active    TINYINT(1)        NOT NULL DEFAULT 1,
    is_system    TINYINT(1)        NOT NULL DEFAULT 0,
    created_by   INT UNSIGNED      NULL,
    created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by   INT UNSIGNED      NULL,
    updated_at   DATETIME          NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (area_id),
    UNIQUE KEY uq_areas_name (area_name),
    KEY ix_areas_active (is_active, sort_order)
) ENGINE=InnoDB;

CREATE TABLE plot_types (
    plot_type_id INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    name         VARCHAR(60)       NOT NULL,
    name_hi      VARCHAR(60)       NULL,
    description  VARCHAR(150)      NULL,
    sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active    TINYINT(1)        NOT NULL DEFAULT 1,
    is_system    TINYINT(1)        NOT NULL DEFAULT 0,
    created_by   INT UNSIGNED      NULL,
    created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by   INT UNSIGNED      NULL,
    updated_at   DATETIME          NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (plot_type_id),
    UNIQUE KEY uq_plot_types_name (name)
) ENGINE=InnoDB;

-- system statuses (is_system = 1) have fixed ids the workflow uses: App\Codes\PropertyStatus
CREATE TABLE statuses (
    status_id    INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    name         VARCHAR(40)       NOT NULL,
    name_hi      VARCHAR(40)       NULL,
    color        VARCHAR(20)       NOT NULL DEFAULT 'info',
    icon         VARCHAR(40)       NOT NULL DEFAULT 'fa-flag',
    description  VARCHAR(150)      NULL,
    sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active    TINYINT(1)        NOT NULL DEFAULT 1,
    is_system    TINYINT(1)        NOT NULL DEFAULT 0,
    created_by   INT UNSIGNED      NULL,
    created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by   INT UNSIGNED      NULL,
    updated_at   DATETIME          NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (status_id),
    UNIQUE KEY uq_statuses_name (name)
) ENGINE=InnoDB;

-- area units (sq m, sq ft, sq yard …): every area is also stored in sq m using to_sqm
CREATE TABLE units (
    unit_id      INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    name         VARCHAR(40)       NOT NULL,
    name_hi      VARCHAR(40)       NULL,
    symbol       VARCHAR(15)       NOT NULL,              -- short label, e.g. "sq ft"
    to_sqm       DECIMAL(16,8)     NOT NULL,              -- 1 unit = to_sqm square metres
    description  VARCHAR(150)      NULL,
    sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active    TINYINT(1)        NOT NULL DEFAULT 1,
    is_system    TINYINT(1)        NOT NULL DEFAULT 0,    -- sq m: the base unit
    created_by   INT UNSIGNED      NULL,
    created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by   INT UNSIGNED      NULL,
    updated_at   DATETIME          NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (unit_id),
    UNIQUE KEY uq_units_name (name),
    CONSTRAINT ck_units_factor CHECK (to_sqm > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. Schemes, sectors and the role-user mapping of a scheme
-- ---------------------------------------------------------------------
CREATE TABLE schemes (
    scheme_id       INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    scheme_name     VARCHAR(150)      NOT NULL,
    scheme_type     ENUM('RESIDENTIAL','COMMERCIAL','INDUSTRIAL','MIXED','FARMHOUSE') NOT NULL,
    area_id         INT UNSIGNED      NULL,
    is_active       TINYINT(1)        NOT NULL DEFAULT 1,
    created_by      INT UNSIGNED      NULL,
    created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by      INT UNSIGNED      NULL,
    updated_at      DATETIME          NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (scheme_id),
    UNIQUE KEY uq_schemes_name (scheme_name),
    KEY ix_schemes_active (is_active, scheme_name),
    KEY ix_schemes_area (area_id),
    CONSTRAINT fk_schemes_area FOREIGN KEY (area_id) REFERENCES areas(area_id)
) ENGINE=InnoDB;

CREATE TABLE sectors (
    sector_id    INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    scheme_id    INT UNSIGNED      NOT NULL,
    sector_name  VARCHAR(80)       NOT NULL,
    sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active    TINYINT(1)        NOT NULL DEFAULT 1,
    created_by   INT UNSIGNED      NULL,
    created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by   INT UNSIGNED      NULL,
    updated_at   DATETIME          NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (sector_id),
    UNIQUE KEY uq_sectors_name (scheme_id, sector_name),
    KEY ix_sectors_active (scheme_id, is_active, sort_order),
    CONSTRAINT fk_sectors_scheme FOREIGN KEY (scheme_id) REFERENCES schemes (scheme_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- users of each role who verify every property of the scheme
CREATE TABLE scheme_users (
    map_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    scheme_id   INT UNSIGNED NOT NULL,
    role_id     INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (map_id),
    KEY ix_scheme_users (scheme_id, role_id, is_active),
    KEY ix_scheme_users_user (user_id, is_active),
    CONSTRAINT fk_scheme_users_scheme FOREIGN KEY (scheme_id) REFERENCES schemes (scheme_id) ON DELETE RESTRICT,
    CONSTRAINT fk_scheme_users_role   FOREIGN KEY (role_id)   REFERENCES roles (role_id)     ON DELETE RESTRICT,
    CONSTRAINT fk_scheme_users_user   FOREIGN KEY (user_id)   REFERENCES users (user_id)     ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. Property register
-- ---------------------------------------------------------------------
CREATE TABLE properties (
    property_id     INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    scheme_id       INT UNSIGNED   NOT NULL,
    sector_id       INT UNSIGNED   NULL,                  -- optional
    sector_key      INT UNSIGNED   AS (IFNULL(sector_id, 0)) STORED,  -- 0 = no sector; keeps plot no. unique also without a sector
    plot_no         VARCHAR(30)    NOT NULL,
    plot_type_id    INT UNSIGNED   NOT NULL,
    area_value      DECIMAL(12,2)  NULL,                  -- optional (all three together)
    unit_id         INT UNSIGNED   NULL,
    area_sqm        DECIMAL(14,4)  NULL,
    is_corner       TINYINT(1)     NOT NULL DEFAULT 0,    -- marked Corner in the Add Property grid
    remarks         VARCHAR(500)   NULL,
    reserve_price   DECIMAL(14,2)  NULL,                  -- in rupees, optional
    status_id       INT UNSIGNED   NOT NULL DEFAULT 1,    -- 1 = Listed
    current_round_id INT UNSIGNED  NULL,
    ready_round_id  INT UNSIGNED   NULL,
    valid_till      DATE           NULL,
    is_active       TINYINT(1)     NOT NULL DEFAULT 1,
    created_by      INT UNSIGNED   NULL,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by      INT UNSIGNED   NULL,
    updated_at      DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (property_id),
    KEY ix_properties_sector (sector_id),
    UNIQUE KEY uq_properties_plot (scheme_id, sector_key, plot_no),
    KEY ix_properties_scheme (scheme_id, is_active),
    KEY ix_properties_status (status_id, is_active, valid_till),   -- expiry job, re-verification due, status filters
    KEY ix_properties_created (created_by, is_active),                 -- "entered by me"
    CONSTRAINT fk_properties_scheme   FOREIGN KEY (scheme_id)      REFERENCES schemes (scheme_id)       ON DELETE RESTRICT,
    CONSTRAINT fk_properties_sector   FOREIGN KEY (sector_id)      REFERENCES sectors (sector_id)       ON DELETE RESTRICT,
    CONSTRAINT fk_properties_type     FOREIGN KEY (plot_type_id)   REFERENCES plot_types (plot_type_id) ON DELETE RESTRICT,
    CONSTRAINT fk_properties_unit     FOREIGN KEY (unit_id)        REFERENCES units (unit_id)           ON DELETE RESTRICT,
    CONSTRAINT fk_properties_status   FOREIGN KEY (status_id)      REFERENCES statuses (status_id)      ON DELETE RESTRICT,
    CONSTRAINT ck_properties_area CHECK (area_value IS NULL OR area_value > 0),
    CONSTRAINT ck_properties_area_unit CHECK ((area_value IS NULL) = (unit_id IS NULL) AND (area_value IS NULL) = (area_sqm IS NULL)),
    CONSTRAINT ck_properties_price CHECK (reserve_price IS NULL OR reserve_price > 0)
) ENGINE=InnoDB;


-- ---------------------------------------------------------------------
-- 5. Verification: rounds -> one task per role -> assignees
-- ---------------------------------------------------------------------
CREATE TABLE rounds (
    round_id       INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    property_id    INT UNSIGNED      NOT NULL,
    round_no       SMALLINT UNSIGNED NOT NULL,
    round_status   ENUM('OPEN','CLOSED','CANCELLED') NOT NULL DEFAULT 'OPEN',
    result         ENUM('READY','NOT_READY') NULL,
    result_note    TEXT              NULL,
    initiated_by   INT UNSIGNED      NOT NULL,
    initiated_at   DATETIME          NOT NULL,
    due_date       DATE              NOT NULL,
    resend_reason  VARCHAR(255)      NULL,
    closed_by      INT UNSIGNED      NULL,
    closed_at      DATETIME          NULL,
    valid_from     DATE              NULL,
    valid_till     DATE              NULL,
    created_at     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME          NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (round_id),
    UNIQUE KEY uq_rounds_no (property_id, round_no),
    KEY ix_rounds_open (property_id, round_status),   -- "a round is already open"
    KEY ix_rounds_status (round_status),
    CONSTRAINT fk_rounds_property  FOREIGN KEY (property_id)  REFERENCES properties (property_id) ON DELETE RESTRICT,
    CONSTRAINT fk_rounds_initiator FOREIGN KEY (initiated_by) REFERENCES users (user_id)           ON DELETE RESTRICT
) ENGINE=InnoDB;

ALTER TABLE properties
    ADD CONSTRAINT fk_properties_round FOREIGN KEY (current_round_id) REFERENCES rounds (round_id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_properties_ready FOREIGN KEY (ready_round_id)   REFERENCES rounds (round_id) ON DELETE SET NULL;

CREATE TABLE tasks (
    task_id       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    round_id      INT UNSIGNED  NOT NULL,
    role_id       INT UNSIGNED  NOT NULL,
    task_status   ENUM('PENDING','APPROVED','REJECTED','NO_RESPONSE') NOT NULL DEFAULT 'PENDING',
    sla_due_date  DATE          NOT NULL,
    remarks       VARCHAR(1000) NULL,
    responded_by  INT UNSIGNED  NULL,
    responded_at  DATETIME      NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id),
    UNIQUE KEY uq_tasks_role (round_id, role_id),
    KEY ix_tasks_status (task_status, sla_due_date),
    CONSTRAINT fk_tasks_round FOREIGN KEY (round_id)     REFERENCES rounds (round_id) ON DELETE RESTRICT,
    CONSTRAINT fk_tasks_role  FOREIGN KEY (role_id)      REFERENCES roles (role_id)   ON DELETE RESTRICT,
    CONSTRAINT fk_tasks_user  FOREIGN KEY (responded_by) REFERENCES users (user_id)   ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE task_assignees (
    assignee_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id      INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (assignee_id),
    UNIQUE KEY uq_task_assignees (task_id, user_id),
    KEY ix_task_assignees_user (user_id, task_id),   -- my tasks / my properties
    CONSTRAINT fk_task_assignees_task FOREIGN KEY (task_id) REFERENCES tasks (task_id) ON DELETE CASCADE,
    CONSTRAINT fk_task_assignees_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Engineering Cell: physical site check (length × width, encroachment, road, corner, geo location), filled with the decision.
-- Site photos are attachments of the task (context SITE_PHOTO).
CREATE TABLE site_inspections (
    inspection_id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    task_id               INT UNSIGNED  NOT NULL,
    length_value          DECIMAL(10,2) NULL,                 -- as measured at the site, in the length of the unit
    width_value           DECIMAL(10,2) NULL,
    area_value            DECIMAL(12,2) NOT NULL,             -- length × width
    unit_id               INT UNSIGNED  NOT NULL,
    area_sqm              DECIMAL(14,4) NOT NULL,
    recorded_value        DECIMAL(12,2) NULL,                 -- property area at the time; NULL when the register has none
    recorded_unit_id      INT UNSIGNED  NULL,
    difference            DECIMAL(12,2) NOT NULL DEFAULT 0,
    difference_pct        DECIMAL(7,2)  NOT NULL DEFAULT 0,
    variance              ENUM('SAME','LESS','MORE') NULL,      -- NULL: nothing recorded to compare with
    encroachment          ENUM('YES','NO') NOT NULL,
    encroachment_type     ENUM('PARTIAL','COMPLETE') NULL,    -- when encroachment = YES
    encroachment_details  VARCHAR(300)  NULL,
    road_width            SMALLINT UNSIGNED NULL,             -- whole number
    road_width_unit       ENUM('FT','M') NULL,
    is_corner             TINYINT(1)    NULL,
    latitude              DECIMAL(10,8) NULL,
    longitude             DECIMAL(11,8) NULL,
    measured_by           INT UNSIGNED  NOT NULL,
    measured_at           DATETIME      NOT NULL,
    PRIMARY KEY (inspection_id),
    UNIQUE KEY uq_site_inspections_task (task_id),
    CONSTRAINT fk_site_inspections_task FOREIGN KEY (task_id)     REFERENCES tasks (task_id) ON DELETE CASCADE,
    CONSTRAINT fk_site_inspections_user FOREIGN KEY (measured_by) REFERENCES users (user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_inspections_unit FOREIGN KEY (unit_id) REFERENCES units (unit_id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_inspections_recorded_unit FOREIGN KEY (recorded_unit_id) REFERENCES units (unit_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Department-wise answers filled with the decision: Sale (Sale Cell), Auction (Auction Cell), General (other cells, not Engineering).
-- Any "Yes" means the property is not available, so the decision must be Objection.
-- The allotment / mutation letter is an attachment of the task (context ALLOTMENT_LETTER).
CREATE TABLE section_checks (
    check_id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id             INT UNSIGNED NOT NULL,
    is_sale_allotted    TINYINT(1)   NULL,                 -- Sale: allotted to a person (NULL = section not asked)
    allottee_name       VARCHAR(100) NULL,
    allotment_date      DATE         NULL,
    is_auctioned        TINYINT(1)   NULL,                 -- Auction (NULL = section not asked)
    auction_date        DATE         NULL,
    is_inst_allotted    TINYINT(1)   NULL,                 -- General (other cells): allotted to an organisation / institution, or in progress (NULL = not asked)
    is_inst_in_process  TINYINT(1)   NULL,                 -- allotment in progress: name only, no allotment date yet
    inst_name           VARCHAR(150) NULL,
    inst_allotment_date DATE         NULL,
    checked_by          INT UNSIGNED NOT NULL,
    checked_at          DATETIME     NOT NULL,
    PRIMARY KEY (check_id),
    UNIQUE KEY uq_section_checks_task (task_id),
    CONSTRAINT fk_section_checks_task FOREIGN KEY (task_id)    REFERENCES tasks (task_id) ON DELETE CASCADE,
    CONSTRAINT fk_section_checks_user FOREIGN KEY (checked_by) REFERENCES users (user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- files uploaded with a section response (stored outside the web root, storage/uploads)
CREATE TABLE attachments (
    attachment_id  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    task_id        INT UNSIGNED  NULL,
    round_id       INT UNSIGNED  NULL,
    property_id    INT UNSIGNED  NOT NULL,
    context        VARCHAR(30)   NOT NULL DEFAULT 'TASK_RESPONSE',
    file_name      VARCHAR(150)  NOT NULL,
    stored_name    VARCHAR(80)   NOT NULL,
    mime           VARCHAR(50)   NOT NULL,
    size           INT UNSIGNED  NOT NULL,
    sha256         CHAR(64)      NOT NULL,
    uploaded_by    INT UNSIGNED  NOT NULL,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attachment_id),
    UNIQUE KEY uq_attachments_stored (stored_name),
    KEY ix_attachments_task (task_id),
    KEY ix_attachments_property (property_id),
    CONSTRAINT fk_attachments_task     FOREIGN KEY (task_id)     REFERENCES tasks (task_id)          ON DELETE CASCADE,
    CONSTRAINT fk_attachments_round    FOREIGN KEY (round_id)    REFERENCES rounds (round_id)        ON DELETE CASCADE,
    CONSTRAINT fk_attachments_property FOREIGN KEY (property_id) REFERENCES properties (property_id) ON DELETE RESTRICT,
    CONSTRAINT fk_attachments_user     FOREIGN KEY (uploaded_by) REFERENCES users (user_id)          ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. Notifications and audit trail
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    notification_id  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED    NOT NULL,
    type             ENUM('TASK','VERIFICATION','PROPERTY','ACCOUNT','SYSTEM') NOT NULL DEFAULT 'SYSTEM',
    title            VARCHAR(200)    NOT NULL,
    message          VARCHAR(600)    NOT NULL DEFAULT '',
    link             VARCHAR(255)    NULL,
    icon             VARCHAR(40)     NOT NULL DEFAULT 'fa-bell',
    tone             ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    ref              VARCHAR(80)     NULL,
    is_read          TINYINT(1)      NOT NULL DEFAULT 0,
    read_at          DATETIME        NULL,
    deleted_at       DATETIME        NULL,   -- hidden by the user, kept so a reminder (ref) is not sent again
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    UNIQUE KEY uq_notifications_ref (user_id, ref),
    KEY ix_notifications_user (user_id, deleted_at, notification_id),   -- newest visible rows of a user
    KEY ix_notifications_unread (user_id, is_read, deleted_at),         -- unread count / mark all read
    KEY ix_notifications_deleted (deleted_at),                         -- prune
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- insert-only: the production DB user gets INSERT + SELECT on this table, never UPDATE / DELETE
CREATE TABLE audit_logs (
    log_id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type               VARCHAR(30)     NOT NULL,
    entity_id                 INT UNSIGNED    NULL,
    property_id               INT UNSIGNED    NULL,
    action                    VARCHAR(50)     NOT NULL,
    old_value                 JSON            NULL,
    new_value                 JSON            NULL,
    performed_by              INT UNSIGNED    NULL,
    performed_by_name         VARCHAR(120)    NOT NULL DEFAULT 'System',
    performed_by_designation  VARCHAR(100)    NULL,
    ip_address                VARCHAR(45)     NOT NULL DEFAULT '',
    user_agent                VARCHAR(255)    NULL,
    performed_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    KEY ix_audit_entity (entity_type, entity_id),
    KEY ix_audit_property (property_id, log_id),   -- property timeline (newest first)
    KEY ix_audit_user (performed_by, log_id),      -- a user's own actions (newest first)
    KEY ix_audit_time (performed_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. App settings (Super Admin → App Settings) and landing page slides
-- ---------------------------------------------------------------------
CREATE TABLE settings (
    setting_key    VARCHAR(60)  NOT NULL,
    setting_value  TEXT         NOT NULL,
    updated_by     INT UNSIGNED NULL,
    updated_at     DATETIME     NULL,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB;

CREATE TABLE slides (
    slide_id    INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    image       VARCHAR(80)       NOT NULL,
    title       VARCHAR(120)      NOT NULL,
    credit      VARCHAR(200)      NULL,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active   TINYINT(1)        NOT NULL DEFAULT 1,
    created_by  INT UNSIGNED      NULL,
    created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by  INT UNSIGNED      NULL,
    updated_at  DATETIME          NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (slide_id),
    UNIQUE KEY uq_slides_image (image),
    KEY ix_slides_active (is_active, sort_order)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Production: run the app with a least-privilege account, e.g.
--   CREATE USER 'pramaan_app'@'localhost' IDENTIFIED BY '<strong password>';
--   GRANT SELECT, INSERT, UPDATE, DELETE ON pramaan_db.* TO 'pramaan_app'@'localhost';
--   REVOKE UPDATE, DELETE ON pramaan_db.audit_logs FROM 'pramaan_app'@'localhost';
-- (MySQL needs table-level grants for the last line: grant the other tables one by one.)
-- ---------------------------------------------------------------------
