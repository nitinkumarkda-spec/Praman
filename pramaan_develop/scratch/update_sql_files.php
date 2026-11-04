<?php
// Update database/schema.sql
$schemaPath = __DIR__ . '/../database/schema.sql';
$schema = file_get_contents($schemaPath);

$oldCreateUsers = <<<SQL
CREATE TABLE users (
    user_id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    username             VARCHAR(50)   NOT NULL,
    emp_code             VARCHAR(30)   NOT NULL,
    name                 VARCHAR(120)  NOT NULL,
    designation          VARCHAR(100)  NOT NULL,
    mobile               CHAR(10)      NOT NULL,
    email                VARCHAR(150)  NOT NULL,
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
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_emp_code (emp_code),
    UNIQUE KEY uq_users_mobile (mobile),
    UNIQUE KEY uq_users_email (email),
    KEY ix_users_role (role_id, is_active),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (role_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;
SQL;

$newCreateUsers = <<<SQL
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
SQL;

$schemaNorm = str_replace("\r\n", "\n", $schema);
$oldNorm = str_replace("\r\n", "\n", $oldCreateUsers);
$newNorm = str_replace("\r\n", "\n", $newCreateUsers);

if (strpos($schemaNorm, $oldNorm) !== false) {
    $schemaNorm = str_replace($oldNorm, $newNorm, $schemaNorm);
    file_put_contents($schemaPath, str_replace("\n", "\r\n", $schemaNorm));
    echo "database/schema.sql updated successfully.\n";
} else {
    echo "database/schema.sql old CREATE TABLE not found.\n";
}

// Update database/pramaan_db.sql
$dbSqlPath = __DIR__ . '/../database/pramaan_db.sql';
$dbSql = file_get_contents($dbSqlPath);

$oldDbSqlUsers = <<<SQL
CREATE TABLE `users` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `emp_code` varchar(30) NOT NULL,
  `name` varchar(120) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `mobile` char(10) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `failed_attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_emp_code` (`emp_code`),
  UNIQUE KEY `uq_users_mobile` (`mobile`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `ix_users_role` (`role_id`,`is_active`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

$newDbSqlUsers = <<<SQL
CREATE TABLE `users` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `mobile` char(10) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `failed_attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_mobile` (`mobile`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `ix_users_role` (`role_id`,`is_active`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

$oldInsert = "INSERT INTO `users` VALUES (1,'admin','KDA-IT-001','Admin','Administrator','9800000001','admin@kda.local',";
$newInsert = "INSERT INTO `users` VALUES (1,'Admin','9800000001','admin@kda.local',";

$dbSqlNorm = str_replace("\r\n", "\n", $dbSql);
$oldDbNorm = str_replace("\r\n", "\n", $oldDbSqlUsers);
$newDbNorm = str_replace("\r\n", "\n", $newDbSqlUsers);

if (strpos($dbSqlNorm, $oldDbNorm) !== false) {
    $dbSqlNorm = str_replace($oldDbNorm, $newDbNorm, $dbSqlNorm);
    $dbSqlNorm = str_replace($oldInsert, $newInsert, $dbSqlNorm);
    file_put_contents($dbSqlPath, str_replace("\n", "\r\n", $dbSqlNorm));
    echo "database/pramaan_db.sql updated successfully.\n";
} else {
    echo "database/pramaan_db.sql old CREATE TABLE not found.\n";
}

