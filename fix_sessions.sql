-- Fix sessions_remaining to default to 30 instead of 4

-- First, update all existing students with year level 1, 2, or 3 to have 30 sessions (NOT year level 4)
UPDATE students SET sessions_remaining = 30 WHERE role != 'admin' AND year_level < 4;

-- Then change the default value for future inserts
ALTER TABLE students ALTER COLUMN sessions_remaining SET DEFAULT 30;
