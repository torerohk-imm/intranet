-- Migration: Update role name from 'Administrador Principal' to 'Administrador'
-- Date: 2025-11-24

UPDATE roles 
SET name = 'Administrador' 
WHERE slug = 'admin' AND name = 'Administrador Principal';
