ALTER TABLE wp_pollsq ADD COLUMN IF NOT EXISTS pollq_type varchar(50) NOT NULL DEFAULT 'classic';
