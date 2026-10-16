-- =====================================================================
--  PRAMAN — Property Records & Auction-readiness Multi-section
--           Assessment Network  |  Kota Development Authority
--  Database : praman_db
--  Engine   : InnoDB, utf8mb4  (MySQL 8.0+ / MariaDB 10.4+ compatible)
--  Import   : phpMyAdmin -> Import -> select this file -> Go
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
SET time_zone = '+05:30';

CREATE DATABASE IF NOT EXISTS praman_db
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE praman_db;

DROP TABLE IF EXISTS verification_task_assignees;
DROP TABLE IF EXISTS verification_tasks;
DROP TABLE IF EXISTS verification_rounds;
DROP TABLE IF EXISTS property_role_users;
DROP TABLE IF EXISTS properties;
DROP TABLE IF EXISTS scheme_role_users;
DROP TABLE IF EXISTS scheme_sectors;
DROP TABLE IF EXISTS schemes;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

-- ---------------------------------------------------------------------
-- 1. roles  (each role = one verification section)
-- ---------------------------------------------------------------------
CREATE TABLE roles (
  role_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_code      VARCHAR(40)  NOT NULL,
  role_name      VARCHAR(100) NOT NULL,
  role_name_hi   VARCHAR(100) NULL,
  description    VARCHAR(255) NULL,
  sla_days       SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  is_mandatory   TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by     INT UNSIGNED NULL,
  updated_at     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id),
  UNIQUE KEY uq_roles_code (role_code),
  UNIQUE KEY uq_roles_name (role_name),
  KEY idx_roles_active (is_active, sort_order),
  CONSTRAINT chk_roles_sla CHECK (sla_days BETWEEN 1 AND 365),
  CONSTRAINT chk_roles_code CHECK (role_code REGEXP '^[A-Z][A-Z0-9_]{1,39}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. users
-- ---------------------------------------------------------------------
CREATE TABLE users (
  user_id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username             VARCHAR(50)  NOT NULL,
  emp_code             VARCHAR(30)  NOT NULL,
  name                 VARCHAR(120) NOT NULL,
  designation          VARCHAR(100) NOT NULL,
  mobile               CHAR(10)     NOT NULL,
  email                VARCHAR(150) NOT NULL,
  password_hash        VARCHAR(255) NOT NULL,
  panel                ENUM('SUPER_ADMIN','DATA_ENTRY','OIC','ROLE_USER') NOT NULL,
  role_id              INT UNSIGNED NULL,
  must_change_password TINYINT(1)   NOT NULL DEFAULT 1,
  password_changed_at  DATETIME     NULL,
  failed_attempts      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until         DATETIME     NULL,
  last_login_at        DATETIME     NULL,
  last_login_ip        VARCHAR(45)  NULL,
  is_active            TINYINT(1)   NOT NULL DEFAULT 1,
  created_by           INT UNSIGNED NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by           INT UNSIGNED NULL,
  updated_at           DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_emp_code (emp_code),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_panel (panel, is_active),
  KEY idx_users_role (role_id, is_active),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (role_id),
  CONSTRAINT chk_users_mobile CHECK (mobile REGEXP '^[6-9][0-9]{9}$'),
  CONSTRAINT chk_users_role_panel CHECK (
     (panel = 'ROLE_USER' AND role_id IS NOT NULL) OR (panel <> 'ROLE_USER' AND role_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE roles
  ADD CONSTRAINT fk_roles_created_by FOREIGN KEY (created_by) REFERENCES users (user_id),
  ADD CONSTRAINT fk_roles_updated_by FOREIGN KEY (updated_by) REFERENCES users (user_id);

-- ---------------------------------------------------------------------
-- 3. schemes + sectors + scheme-level role/user mapping
-- ---------------------------------------------------------------------
CREATE TABLE schemes (
  scheme_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  scheme_code       VARCHAR(30)  NOT NULL,
  scheme_name       VARCHAR(150) NOT NULL,
  scheme_type       ENUM('RESIDENTIAL','COMMERCIAL','INDUSTRIAL','MIXED','FARMHOUSE') NOT NULL,
  zone              VARCHAR(80)  NOT NULL,
  year_of_scheme    SMALLINT UNSIGNED NOT NULL,
  sectors_complete  TINYINT(1)   NOT NULL DEFAULT 0,
  is_active         TINYINT(1)   NOT NULL DEFAULT 1,
  created_by        INT UNSIGNED NOT NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by        INT UNSIGNED NULL,
  updated_at        DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (scheme_id),
  UNIQUE KEY uq_schemes_code (scheme_code),
  UNIQUE KEY uq_schemes_name (scheme_name),
  KEY idx_schemes_active (is_active),
  CONSTRAINT fk_schemes_created_by FOREIGN KEY (created_by) REFERENCES users (user_id),
  CONSTRAINT fk_schemes_updated_by FOREIGN KEY (updated_by) REFERENCES users (user_id),
  CONSTRAINT chk_schemes_year CHECK (year_of_scheme BETWEEN 1950 AND 2100),
  CONSTRAINT chk_schemes_sectors CHECK (sectors_complete IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scheme_sectors (
  sector_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  scheme_id    INT UNSIGNED NOT NULL,
  sector_name  VARCHAR(80)  NOT NULL,
  sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  created_by   INT UNSIGNED NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by   INT UNSIGNED NULL,
  updated_at   DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (sector_id),
  UNIQUE KEY uq_sector_name (scheme_id, sector_name),
  KEY idx_sector_active (scheme_id, is_active),
  CONSTRAINT fk_sector_scheme FOREIGN KEY (scheme_id) REFERENCES schemes (scheme_id),
  CONSTRAINT fk_sector_created_by FOREIGN KEY (created_by) REFERENCES users (user_id),
  CONSTRAINT fk_sector_updated_by FOREIGN KEY (updated_by) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scheme_role_users (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  scheme_id   INT UNSIGNED NOT NULL,
  role_id     INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_by  INT UNSIGNED NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by  INT UNSIGNED NULL,
  updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sru (scheme_id, role_id, user_id),
  KEY idx_sru_role (role_id),
  KEY idx_sru_user (user_id),
  CONSTRAINT fk_sru_scheme FOREIGN KEY (scheme_id) REFERENCES schemes (scheme_id),
  CONSTRAINT fk_sru_role   FOREIGN KEY (role_id)   REFERENCES roles (role_id),
  CONSTRAINT fk_sru_user   FOREIGN KEY (user_id)   REFERENCES users (user_id),
  CONSTRAINT fk_sru_created_by FOREIGN KEY (created_by) REFERENCES users (user_id),
  CONSTRAINT fk_sru_updated_by FOREIGN KEY (updated_by) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. properties + per-property role/user snapshot
-- ---------------------------------------------------------------------
CREATE TABLE properties (
  property_id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  property_code           VARCHAR(40)  NULL,
  scheme_id               INT UNSIGNED NOT NULL,
  sector_id               INT UNSIGNED NOT NULL,
  plot_no                 VARCHAR(30)  NOT NULL,
  property_type           ENUM('PLOT','SHOP','KIOSK','FARMHOUSE','FLAT','OTHER') NOT NULL,
  area_value              DECIMAL(12,2) NOT NULL,
  area_unit               ENUM('SQM','SQFT') NOT NULL,
  area_sqm                DECIMAL(14,4) NOT NULL,
  current_status          ENUM('LISTED','UNDER_VERIFICATION','AUCTION_READY','NOT_READY',
                               'SENT_FOR_AUCTION','SOLD','WITHHELD','ARCHIVED') NOT NULL DEFAULT 'LISTED',
  current_round_id        INT UNSIGNED NULL,
  last_verified_round_id  INT UNSIGNED NULL,
  verification_valid_till DATE NULL,
  parent_property_id      INT UNSIGNED NULL,
  remarks                 VARCHAR(500) NULL,
  is_active               TINYINT(1)   NOT NULL DEFAULT 1,
  created_by              INT UNSIGNED NOT NULL,
  created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by              INT UNSIGNED NULL,
  updated_at              DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (property_id),
  UNIQUE KEY uq_property_code (property_code),
  UNIQUE KEY uq_property_identity (scheme_id, sector_id, plot_no, property_type),
  KEY idx_property_status (current_status, is_active),
  KEY idx_property_sector (sector_id),
  KEY idx_property_created_by (created_by),
  CONSTRAINT fk_property_scheme FOREIGN KEY (scheme_id) REFERENCES schemes (scheme_id),
  CONSTRAINT fk_property_sector FOREIGN KEY (sector_id) REFERENCES scheme_sectors (sector_id),
  CONSTRAINT fk_property_parent FOREIGN KEY (parent_property_id) REFERENCES properties (property_id),
  CONSTRAINT fk_property_created_by FOREIGN KEY (created_by) REFERENCES users (user_id),
  CONSTRAINT fk_property_updated_by FOREIGN KEY (updated_by) REFERENCES users (user_id),
  CONSTRAINT chk_property_area CHECK (area_value > 0 AND area_sqm > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_role_users (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  property_id  INT UNSIGNED NOT NULL,
  role_id      INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  snapshot_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by   INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pru (property_id, role_id, user_id),
  KEY idx_pru_role (role_id),
  KEY idx_pru_user (user_id),
  CONSTRAINT fk_pru_property FOREIGN KEY (property_id) REFERENCES properties (property_id),
  CONSTRAINT fk_pru_role     FOREIGN KEY (role_id)     REFERENCES roles (role_id),
  CONSTRAINT fk_pru_user     FOREIGN KEY (user_id)     REFERENCES users (user_id),
  CONSTRAINT fk_pru_created_by FOREIGN KEY (created_by) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. Verification engine
-- ---------------------------------------------------------------------
CREATE TABLE verification_rounds (
  round_id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  property_id          INT UNSIGNED NOT NULL,
  round_no             SMALLINT UNSIGNED NOT NULL,
  round_status         ENUM('OPEN','CLOSED','SUPERSEDED','CANCELLED') NOT NULL DEFAULT 'OPEN',
  consolidated_result  ENUM('READY','NOT_READY') NULL,
  consolidation_remarks TEXT NULL,
  -- 1 while OPEN, NULL otherwise => UNIQUE(property_id, open_lock) allows only ONE open round per property
  open_lock            TINYINT AS (CASE WHEN round_status = 'OPEN' THEN 1 ELSE NULL END) STORED,
  initiated_by         INT UNSIGNED NOT NULL,
  initiated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  due_date             DATE         NULL,
  closed_by            INT UNSIGNED NULL,
  closed_at            DATETIME     NULL,
  valid_from           DATE         NULL,
  valid_till           DATE         NULL,
  reinitiate_reason    VARCHAR(255) NULL,
  PRIMARY KEY (round_id),
  UNIQUE KEY uq_round_no (property_id, round_no),
  UNIQUE KEY uq_one_open_round (property_id, open_lock),
  KEY idx_round_status (round_status),
  CONSTRAINT fk_round_property FOREIGN KEY (property_id) REFERENCES properties (property_id),
  CONSTRAINT fk_round_initiated_by FOREIGN KEY (initiated_by) REFERENCES users (user_id),
  CONSTRAINT fk_round_closed_by FOREIGN KEY (closed_by) REFERENCES users (user_id),
  CONSTRAINT chk_round_closed CHECK (round_status = 'OPEN' OR closed_at IS NOT NULL),
  CONSTRAINT chk_round_result CHECK (round_status <> 'CLOSED' OR consolidated_result IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE properties
  ADD CONSTRAINT fk_property_current_round FOREIGN KEY (current_round_id) REFERENCES verification_rounds (round_id),
  ADD CONSTRAINT fk_property_last_round FOREIGN KEY (last_verified_round_id) REFERENCES verification_rounds (round_id);

CREATE TABLE verification_tasks (
  task_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  round_id      INT UNSIGNED NOT NULL,
  role_id       INT UNSIGNED NOT NULL,
  task_status   ENUM('PENDING','APPROVED','REJECTED','NO_RESPONSE') NOT NULL DEFAULT 'PENDING',
  remarks       TEXT         NULL,
  responded_by  INT UNSIGNED NULL,
  responded_at  DATETIME     NULL,
  is_simulated  TINYINT(1)   NOT NULL DEFAULT 0,
  sla_due_date  DATE         NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (task_id),
  UNIQUE KEY uq_task_round_role (round_id, role_id),
  KEY idx_task_status (task_status),
  KEY idx_task_role (role_id),
  CONSTRAINT fk_task_round FOREIGN KEY (round_id) REFERENCES verification_rounds (round_id),
  CONSTRAINT fk_task_role  FOREIGN KEY (role_id)  REFERENCES roles (role_id),
  CONSTRAINT fk_task_responded_by FOREIGN KEY (responded_by) REFERENCES users (user_id),
  CONSTRAINT chk_task_reject_reason CHECK (task_status <> 'REJECTED' OR (remarks IS NOT NULL AND CHAR_LENGTH(TRIM(remarks)) > 0)),
  CONSTRAINT chk_task_responded CHECK (task_status = 'PENDING' OR responded_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE verification_task_assignees (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_id     INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  assigned_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_task_user (task_id, user_id),
  KEY idx_vta_user (user_id),
  CONSTRAINT fk_vta_task FOREIGN KEY (task_id) REFERENCES verification_tasks (task_id),
  CONSTRAINT fk_vta_user FOREIGN KEY (user_id) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. Audit (insert-only) + login attempts
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
  log_id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type              VARCHAR(40)  NOT NULL,
  entity_id                INT UNSIGNED NULL,
  property_id              INT UNSIGNED NULL,
  action                   VARCHAR(50)  NOT NULL,
  old_value                LONGTEXT     NULL,
  new_value                LONGTEXT     NULL,
  performed_by             INT UNSIGNED NULL,
  performed_by_name        VARCHAR(120) NULL,
  performed_by_designation VARCHAR(100) NULL,
  performed_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address               VARCHAR(45)  NULL,
  user_agent               VARCHAR(255) NULL,
  PRIMARY KEY (log_id),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_property (property_id),
  KEY idx_audit_user (performed_by),
  KEY idx_audit_time (performed_at),
  CONSTRAINT chk_audit_old_json CHECK (old_value IS NULL OR JSON_VALID(old_value)),
  CONSTRAINT chk_audit_new_json CHECK (new_value IS NULL OR JSON_VALID(new_value))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username     VARCHAR(50)  NOT NULL,
  ip_address   VARCHAR(45)  NOT NULL,
  success      TINYINT(1)   NOT NULL DEFAULT 0,
  user_agent   VARCHAR(255) NULL,
  attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_la_user_time (username, attempted_at),
  KEY idx_la_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED DATA
-- Default password for EVERY seeded user:  Praman@123
-- Users go straight to their dashboard (security.force_password_change = false in config).
-- Change these default passwords from "Change Password" before going live.
-- =====================================================================

INSERT INTO roles (role_id, role_code, role_name, role_name_hi, description, sla_days, is_mandatory, sort_order) VALUES
 (1, 'AUCTION_CELL',       'Auction Cell',       'नीलामी शाखा',        'Allotment by auction — allottee / cancellation record', 5, 1, 1),
 (2, 'LOTTERY_CELL',       'Lottery Cell',       'लॉटरी शाखा',         'Allotment by lottery — allottee / cancellation record', 5, 1, 2),
 (3, 'SALE_CELL',          'Sale Cell',          'विक्रय शाखा',         'Sale register — sold / reserved / vacant on record',    5, 1, 3),
 (4, 'LAND_FOR_LAND_CELL', 'Land-for-Land Cell', 'भूमि के बदले भूमि शाखा','Land given against acquired land',                       5, 1, 4),
 (5, 'PUNARVAS_CELL',      'Punarvas Cell',      'पुनर्वास शाखा',        'Rehabilitation allotments',                             5, 1, 5),
 (6, 'ENGINEERING_CELL',   'Engineering Cell',   'अभियांत्रिकी शाखा',   'Site inspection — vacancy, encroachment, measurements', 10, 1, 6),
 (7, 'TOWN_PLANNING_CELL', 'Town Planning Cell', 'नगर नियोजन शाखा',    'Master plan / layout / road widening check',            7, 1, 7),
 (8, 'LEGAL_CELL',         'Legal Cell',         'विधि शाखा',           'Litigation / stay order check',                         7, 1, 8);

-- $2y$12$... = password_hash('Praman@123', PASSWORD_BCRYPT). Upgraded to Argon2id automatically on first login.
SET @pw = '$2y$12$OMHu.1N/OheGYqr.uzIFtOHjgOk5D06uXYkwMZFTUkt657s7R4R/m';

INSERT INTO users (user_id, username, emp_code, name, designation, mobile, email, password_hash, panel, role_id) VALUES
 (1,  'superadmin', 'KDA-IT-001',  'System Administrator', 'Programmer (IT Cell)',        '9800000001', 'superadmin@kda.local', @pw, 'SUPER_ADMIN', NULL),
 (2,  'dataentry',  'KDA-DE-001',  'Ramesh Kumar Meena',   'Data Entry Operator',         '9800000002', 'dataentry@kda.local',  @pw, 'DATA_ENTRY',  NULL),
 (3,  'oic',        'KDA-OIC-001', 'Suresh Chand Sharma',  'OIC — Sale / Auction',        '9800000003', 'oic@kda.local',        @pw, 'OIC',         NULL),
 (4,  'auction1',   'KDA-AUC-001', 'Anil Kumar Jain',      'Assistant, Auction Cell',     '9800000011', 'auction1@kda.local',   @pw, 'ROLE_USER', 1),
 (5,  'auction2',   'KDA-AUC-002', 'Pooja Saini',          'Section Incharge, Auction',   '9800000012', 'auction2@kda.local',   @pw, 'ROLE_USER', 1),
 (6,  'lottery1',   'KDA-LOT-001', 'Mahesh Gurjar',        'Assistant, Lottery Cell',     '9800000021', 'lottery1@kda.local',   @pw, 'ROLE_USER', 2),
 (7,  'lottery2',   'KDA-LOT-002', 'Kavita Rathore',       'Section Incharge, Lottery',   '9800000022', 'lottery2@kda.local',   @pw, 'ROLE_USER', 2),
 (8,  'sale1',      'KDA-SAL-001', 'Deepak Verma',         'Clerk, Sale Cell',            '9800000031', 'sale1@kda.local',      @pw, 'ROLE_USER', 3),
 (9,  'sale2',      'KDA-SAL-002', 'Neha Agarwal',         'Section Incharge, Sale',      '9800000032', 'sale2@kda.local',      @pw, 'ROLE_USER', 3),
 (10, 'lfl1',       'KDA-LFL-001', 'Bhanwar Lal Bairwa',   'Assistant, Land-for-Land',    '9800000041', 'lfl1@kda.local',       @pw, 'ROLE_USER', 4),
 (11, 'lfl2',       'KDA-LFL-002', 'Sunita Mehra',         'Section Incharge, LFL',       '9800000042', 'lfl2@kda.local',       @pw, 'ROLE_USER', 4),
 (12, 'punarvas1',  'KDA-PUN-001', 'Rajendra Prasad',      'Assistant, Punarvas Cell',    '9800000051', 'punarvas1@kda.local',  @pw, 'ROLE_USER', 5),
 (13, 'punarvas2',  'KDA-PUN-002', 'Asha Kanwar',          'Section Incharge, Punarvas',  '9800000052', 'punarvas2@kda.local',  @pw, 'ROLE_USER', 5),
 (14, 'eng1',       'KDA-ENG-001', 'Vikas Choudhary',      'Junior Engineer',             '9800000061', 'eng1@kda.local',       @pw, 'ROLE_USER', 6),
 (15, 'eng2',       'KDA-ENG-002', 'Manoj Kumar Yadav',    'Assistant Engineer',          '9800000062', 'eng2@kda.local',       @pw, 'ROLE_USER', 6),
 (16, 'tp1',        'KDA-TP-001',  'Shweta Joshi',         'Assistant Town Planner',      '9800000071', 'tp1@kda.local',        @pw, 'ROLE_USER', 7),
 (17, 'tp2',        'KDA-TP-002',  'Gaurav Singhal',       'Deputy Town Planner',         '9800000072', 'tp2@kda.local',        @pw, 'ROLE_USER', 7),
 (18, 'legal1',     'KDA-LEG-001', 'Advocate Ritu Sharma', 'Legal Assistant',             '9800000081', 'legal1@kda.local',     @pw, 'ROLE_USER', 8),
 (19, 'legal2',     'KDA-LEG-002', 'Hemant Gupta',         'Law Officer',                 '9800000082', 'legal2@kda.local',     @pw, 'ROLE_USER', 8);

UPDATE roles SET created_by = 1;
UPDATE users SET must_change_password = 0;

INSERT INTO audit_logs (entity_type, entity_id, action, new_value, performed_by, performed_by_name, performed_by_designation, ip_address)
VALUES ('SYSTEM', NULL, 'DATABASE_SEEDED', JSON_OBJECT('roles', 8, 'users', 19), 1, 'System Administrator', 'Programmer (IT Cell)', '127.0.0.1');

-- =====================================================================
-- PRODUCTION HARDENING (run separately as root, NOT needed on local XAMPP)
-- Create a least-privilege application user. audit_logs is INSERT/SELECT only.
-- =====================================================================
-- CREATE USER 'praman_app'@'localhost' IDENTIFIED BY 'Change_This_Strong_Password!';
-- GRANT SELECT, INSERT, UPDATE ON praman_db.roles TO 'praman_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON praman_db.users TO 'praman_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON praman_db.schemes TO 'praman_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON praman_db.scheme_sectors TO 'praman_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON praman_db.scheme_role_users TO 'praman_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON praman_db.properties TO 'praman_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON praman_db.property_role_users TO 'praman_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON praman_db.verification_rounds TO 'praman_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON praman_db.verification_tasks TO 'praman_app'@'localhost';
-- GRANT SELECT, INSERT ON praman_db.verification_task_assignees TO 'praman_app'@'localhost';
-- GRANT SELECT, INSERT, DELETE ON praman_db.login_attempts TO 'praman_app'@'localhost';
-- GRANT SELECT, INSERT ON praman_db.audit_logs TO 'praman_app'@'localhost';   -- insert-only audit
-- FLUSH PRIVILEGES;
