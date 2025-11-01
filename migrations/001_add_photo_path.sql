-- Ajouter la colonne photo_path
ALTER TABLE report_photos ADD COLUMN IF NOT EXISTS photo_path VARCHAR(500);

-- Optionnel : rendre photo_data nullable si vous migrez les données
ALTER TABLE report_photos ALTER COLUMN photo_data DROP NOT NULL;
