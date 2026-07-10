-- Migration 01: Initial Schema setup
-- Matches the database schema structure

-- Roles, permissions, users
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `code` VARCHAR(30) NOT NULL UNIQUE,
  `description` VARCHAR(255) NULL
);
