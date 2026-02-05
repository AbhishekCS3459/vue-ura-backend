-- SQL script to seed treatments table
-- Run this if you can't use PHP artisan command

INSERT INTO treatments (name, created_at, updated_at)
SELECT 'Cryotherapy', NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM treatments WHERE name = 'Cryotherapy');
INSERT INTO treatments (name, created_at, updated_at)
SELECT 'Body Massage', NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM treatments WHERE name = 'Body Massage');
INSERT INTO treatments (name, created_at, updated_at)
SELECT 'Diet Consultation', NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM treatments WHERE name = 'Diet Consultation');
INSERT INTO treatments (name, created_at, updated_at)
SELECT 'Laser', NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM treatments WHERE name = 'Laser');
INSERT INTO treatments (name, created_at, updated_at)
SELECT 'Cupping Therapy', NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM treatments WHERE name = 'Cupping Therapy');
INSERT INTO treatments (name, created_at, updated_at)
SELECT 'Wellness Assessment', NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM treatments WHERE name = 'Wellness Assessment');
