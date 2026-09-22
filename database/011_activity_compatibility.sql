-- SKILLBRIDGE Migration 011: Activity compatibility and delivery hardening
-- The PHP migration runner performs the conditional column/index repair safely
-- for both legacy and normalized activity tables.
USE industry_hub;

-- This file is intentionally informational for existing installations.
-- Run database/run_migration.php so the PHP repair can inspect the actual
-- schema before changing it.
