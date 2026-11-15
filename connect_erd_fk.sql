-- =============================================================================
-- APAgri System — Connect ERD Foreign Keys Migration (connect_erd_fk.sql)
-- Purpose: Connect all 6 floating lookup tables to core operational tables
--          via explicit FOREIGN KEY constraints for Supabase Schema Visualizer
-- Standard: Full 3NF Normalization, Zero Orphaned Tables, Idempotent Execution
-- =============================================================================

-- =============================================================================
-- 1. PRE-REQUISITE: ENSURE LOOKUP TABLES & SEED DATA EXIST
-- =============================================================================

-- 1.1 Soil Types Lookup
CREATE TABLE IF NOT EXISTS public.soil_type_lookup (
    soil_type_id SERIAL PRIMARY KEY,
    soil_name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT
);

INSERT INTO public.soil_type_lookup (soil_name, description) VALUES
    ('Clay', 'Heavy, nutrient-rich soil with high water retention'),
    ('Clay Loam', 'Balanced blend of clay and loam; ideal for diverse crops'),
    ('Sandy', 'Light, warm, and dry soil with rapid drainage'),
    ('Sandy Loam', 'Free-draining loam suitable for vegetables and root crops'),
    ('Loam', 'Even mix of sand, silt, and clay; high agricultural fertility'),
    ('Silt', 'Fertile soil with fine particles and good moisture retention'),
    ('Peat', 'High organic matter content; retains high moisture'),
    ('Chalk', 'Shallow, alkaline soil over limestone bedrock'),
    ('Other', 'Specialized or mixed soil classification')
ON CONFLICT (soil_name) DO NOTHING;

-- 1.2 Irrigation Types Lookup (including 'Irrigated')
CREATE TABLE IF NOT EXISTS public.irrigation_type_lookup (
    irrigation_type_id SERIAL PRIMARY KEY,
    irrigation_name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT
);

INSERT INTO public.irrigation_type_lookup (irrigation_name, description) VALUES
    ('Drip', 'Targeted low-pressure emitter irrigation to root zones'),
    ('Sprinkler', 'High-pressure overhead spray irrigation'),
    ('Surface Flood', 'Gravity-fed furrow or basin flood irrigation'),
    ('Rain-fed', 'Natural precipitation with zero supplemental watering'),
    ('Irrigated', 'Standard pressurized or pumped surface irrigation'),
    ('None', 'Zero irrigation infrastructure installed')
ON CONFLICT (irrigation_name) DO NOTHING;

-- 1.3 Crop Categories Lookup
CREATE TABLE IF NOT EXISTS public.crop_category_lookup (
    category_id SERIAL PRIMARY KEY,
    category_name VARCHAR(50) UNIQUE NOT NULL
);

INSERT INTO public.crop_category_lookup (category_name) VALUES
    ('Cereals'),
    ('Legumes'),
    ('Vegetables'),
    ('Fruits'),
    ('Root Crops'),
    ('Industrial Crops'),
    ('Herbs & Spices'),
    ('Other')
ON CONFLICT (category_name) DO NOTHING;

-- 1.4 Seasons Lookup
CREATE TABLE IF NOT EXISTS public.season_lookup (
    season_id SERIAL PRIMARY KEY,
    season_name VARCHAR(50) UNIQUE NOT NULL
);

INSERT INTO public.season_lookup (season_name) VALUES
    ('Dry Season'),
    ('Wet Season'),
    ('Year-round'),
    ('Spring'),
    ('Summer'),
    ('Fall'),
    ('Winter')
ON CONFLICT (season_name) DO NOTHING;

-- 1.5 Planting Status Lookup
CREATE TABLE IF NOT EXISTS public.planting_status_lookup (
    status_id SERIAL PRIMARY KEY,
    status_code VARCHAR(30) UNIQUE NOT NULL,
    status_label VARCHAR(50) NOT NULL
);

INSERT INTO public.planting_status_lookup (status_code, status_label) VALUES
    ('planned', 'Planned'),
    ('planted', 'Planted'),
    ('growing', 'Growing'),
    ('harvested', 'Harvested'),
    ('failed', 'Failed')
ON CONFLICT (status_code) DO NOTHING;

-- 1.6 Quality Grades Lookup
CREATE TABLE IF NOT EXISTS public.quality_grade_lookup (
    grade_code VARCHAR(10) PRIMARY KEY,
    grade_label VARCHAR(50) NOT NULL,
    description TEXT
);

INSERT INTO public.quality_grade_lookup (grade_code, grade_label, description) VALUES
    ('A', 'Grade A — Excellent', 'Premium export quality; defect-free'),
    ('B', 'Grade B — Good', 'Standard commercial grade; minor cosmetic blemishes'),
    ('C', 'Grade C — Fair', 'Marketable utility grade for processing'),
    ('D', 'Grade D — Poor', 'Substandard yield suitable only for animal feed or composting')
ON CONFLICT (grade_code) DO NOTHING;


-- =============================================================================
-- 2. MIGRATE FARM TABLE (soil_type_id & irrigation_type_id FKs)
-- =============================================================================

-- 2.1 Add Foreign Key Columns
ALTER TABLE public.farm ADD COLUMN IF NOT EXISTS soil_type_id INT;
ALTER TABLE public.farm ADD COLUMN IF NOT EXISTS irrigation_type_id INT;

-- 2.2 Backfill soil_type_id from existing soil_type column (if present)
DO $$ BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_schema = 'public' AND table_name = 'farm' AND column_name = 'soil_type'
    ) THEN
        UPDATE public.farm f
        SET soil_type_id = s.soil_type_id
        FROM public.soil_type_lookup s
        WHERE LOWER(TRIM(f.soil_type)) = LOWER(TRIM(s.soil_name))
          AND (f.soil_type_id IS NULL OR f.soil_type_id <> s.soil_type_id);

        -- Fallback default for any unmatched custom text
        UPDATE public.farm
        SET soil_type_id = (SELECT soil_type_id FROM public.soil_type_lookup WHERE soil_name = 'Other')
        WHERE soil_type_id IS NULL AND soil_type IS NOT NULL AND soil_type <> '';
    END IF;
END $$;

-- 2.3 Backfill irrigation_type_id from existing irrigation_type column (if present)
DO $$ BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_schema = 'public' AND table_name = 'farm' AND column_name = 'irrigation_type'
    ) THEN
        UPDATE public.farm f
        SET irrigation_type_id = i.irrigation_type_id
        FROM public.irrigation_type_lookup i
        WHERE LOWER(TRIM(f.irrigation_type)) = LOWER(TRIM(i.irrigation_name))
          AND (f.irrigation_type_id IS NULL OR f.irrigation_type_id <> i.irrigation_type_id);

        -- Fallback default for any unmatched custom text
        UPDATE public.farm
        SET irrigation_type_id = (SELECT irrigation_type_id FROM public.irrigation_type_lookup WHERE irrigation_name = 'None')
        WHERE irrigation_type_id IS NULL AND irrigation_type IS NOT NULL AND irrigation_type <> '';
    END IF;
END $$;

-- 2.4 Drop old varchar columns to enforce strict 3NF
ALTER TABLE public.farm DROP COLUMN IF EXISTS soil_type;
ALTER TABLE public.farm DROP COLUMN IF EXISTS irrigation_type;

-- 2.5 Add Foreign Key Constraints to farm
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_farm_soil_type') THEN
        ALTER TABLE public.farm ADD CONSTRAINT fk_farm_soil_type
            FOREIGN KEY (soil_type_id) REFERENCES public.soil_type_lookup(soil_type_id)
            ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_farm_irrigation_type') THEN
        ALTER TABLE public.farm ADD CONSTRAINT fk_farm_irrigation_type
            FOREIGN KEY (irrigation_type_id) REFERENCES public.irrigation_type_lookup(irrigation_type_id)
            ON DELETE SET NULL;
    END IF;
EXCEPTION WHEN duplicate_object OR duplicate_table THEN NULL; END $$;

CREATE INDEX IF NOT EXISTS idx_farm_soil_type_id       ON public.farm (soil_type_id);
CREATE INDEX IF NOT EXISTS idx_farm_irrigation_type_id ON public.farm (irrigation_type_id);


-- =============================================================================
-- 3. MIGRATE CROP TABLE (category_id & season_id FKs)
-- =============================================================================

-- 3.1 Add Foreign Key Columns
ALTER TABLE public.crop ADD COLUMN IF NOT EXISTS category_id INT;
ALTER TABLE public.crop ADD COLUMN IF NOT EXISTS season_id INT;

-- 3.2 Backfill category_id from existing crop_category column (if present)
DO $$ BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_schema = 'public' AND table_name = 'crop' AND column_name = 'crop_category'
    ) THEN
        UPDATE public.crop c
        SET category_id = cl.category_id
        FROM public.crop_category_lookup cl
        WHERE (LOWER(TRIM(c.crop_category)) = LOWER(TRIM(cl.category_name))
               OR LOWER(TRIM(c.crop_category)) || 's' = LOWER(TRIM(cl.category_name))
               OR RTRIM(LOWER(TRIM(c.crop_category)), 's') = RTRIM(LOWER(TRIM(cl.category_name)), 's'))
          AND (c.category_id IS NULL OR c.category_id <> cl.category_id);

        -- Fallback default for any unmatched category
        UPDATE public.crop
        SET category_id = (SELECT category_id FROM public.crop_category_lookup WHERE category_name = 'Other')
        WHERE category_id IS NULL AND crop_category IS NOT NULL AND crop_category <> '';
    END IF;
END $$;

-- 3.3 Backfill season_id from existing recommended_season column (if present)
DO $$ BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_schema = 'public' AND table_name = 'crop' AND column_name = 'recommended_season'
    ) THEN
        UPDATE public.crop c
        SET season_id = sl.season_id
        FROM public.season_lookup sl
        WHERE REPLACE(LOWER(TRIM(c.recommended_season)), ' ', '-') = REPLACE(LOWER(TRIM(sl.season_name)), ' ', '-')
          AND (c.season_id IS NULL OR c.season_id <> sl.season_id);

        -- Fallback default for any unmatched season
        UPDATE public.crop
        SET season_id = (SELECT season_id FROM public.season_lookup WHERE season_name = 'Year-round')
        WHERE season_id IS NULL AND recommended_season IS NOT NULL AND recommended_season <> '';
    END IF;
END $$;

-- 3.4 Drop old varchar columns to enforce strict 3NF
ALTER TABLE public.crop DROP COLUMN IF EXISTS crop_category;
ALTER TABLE public.crop DROP COLUMN IF EXISTS recommended_season;

-- 3.5 Add Foreign Key Constraints to crop
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_crop_category') THEN
        ALTER TABLE public.crop ADD CONSTRAINT fk_crop_category
            FOREIGN KEY (category_id) REFERENCES public.crop_category_lookup(category_id)
            ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_crop_season') THEN
        ALTER TABLE public.crop ADD CONSTRAINT fk_crop_season
            FOREIGN KEY (season_id) REFERENCES public.season_lookup(season_id)
            ON DELETE SET NULL;
    END IF;
EXCEPTION WHEN duplicate_object OR duplicate_table THEN NULL; END $$;

CREATE INDEX IF NOT EXISTS idx_crop_category_id ON public.crop (category_id);
CREATE INDEX IF NOT EXISTS idx_crop_season_id   ON public.crop (season_id);


-- =============================================================================
-- 4. MIGRATE PLANTING TABLE (status_id FK)
-- =============================================================================

-- 4.1 Add Foreign Key Column
ALTER TABLE public.planting ADD COLUMN IF NOT EXISTS status_id INT;

-- 4.2 Backfill status_id from existing status column (if present)
DO $$ BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_schema = 'public' AND table_name = 'planting' AND column_name = 'status'
    ) THEN
        UPDATE public.planting p
        SET status_id = s.status_id
        FROM public.planting_status_lookup s
        WHERE (LOWER(TRIM(p.status)) = LOWER(TRIM(s.status_code))
               OR LOWER(TRIM(p.status)) = LOWER(TRIM(s.status_label)))
          AND (p.status_id IS NULL OR p.status_id <> s.status_id);

        -- Fallback default for any unmatched status
        UPDATE public.planting
        SET status_id = (SELECT status_id FROM public.planting_status_lookup WHERE status_code = 'planned')
        WHERE status_id IS NULL;
    END IF;
END $$;

-- 4.3 Drop old varchar column to enforce strict 3NF
-- Drop any old check constraint on the status column first
DO $$ BEGIN
    ALTER TABLE public.planting DROP CONSTRAINT IF EXISTS chk_planting_status_valid;
EXCEPTION WHEN undefined_object THEN NULL; END $$;

ALTER TABLE public.planting DROP COLUMN IF EXISTS status;

-- 4.4 Set Default and Add Foreign Key Constraint to planting
ALTER TABLE public.planting ALTER COLUMN status_id SET DEFAULT 1;

DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_planting_status') THEN
        ALTER TABLE public.planting ADD CONSTRAINT fk_planting_status
            FOREIGN KEY (status_id) REFERENCES public.planting_status_lookup(status_id)
            ON DELETE RESTRICT;
    END IF;
EXCEPTION WHEN duplicate_object OR duplicate_table THEN NULL; END $$;

CREATE INDEX IF NOT EXISTS idx_planting_status_id ON public.planting (status_id);


-- =============================================================================
-- 5. MIGRATE HARVEST TABLE (quality_grade FK to quality_grade_lookup)
-- =============================================================================

-- 5.1 Normalize any historical string values to single-letter codes
UPDATE public.harvest SET quality_grade = 'A' WHERE quality_grade ILIKE '%A%';
UPDATE public.harvest SET quality_grade = 'B' WHERE quality_grade ILIKE '%B%';
UPDATE public.harvest SET quality_grade = 'C' WHERE quality_grade ILIKE '%C%';
UPDATE public.harvest SET quality_grade = 'D' WHERE quality_grade ILIKE '%D%';

-- 5.2 Set any unrecognised values to NULL before FK assignment
UPDATE public.harvest 
SET quality_grade = NULL 
WHERE quality_grade IS NOT NULL 
  AND quality_grade NOT IN (SELECT grade_code FROM public.quality_grade_lookup);

-- 5.3 Add Foreign Key Constraint to harvest
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_harvest_quality_grade') THEN
        ALTER TABLE public.harvest ADD CONSTRAINT fk_harvest_quality_grade
            FOREIGN KEY (quality_grade) REFERENCES public.quality_grade_lookup(grade_code)
            ON DELETE SET NULL;
    END IF;
EXCEPTION WHEN duplicate_object OR duplicate_table THEN NULL; END $$;

CREATE INDEX IF NOT EXISTS idx_harvest_quality_grade ON public.harvest (quality_grade);


-- =============================================================================
-- 6. VERIFICATION QUERY (Confirms all 14 tables and relationships)
-- =============================================================================

SELECT 
    tc.table_name, 
    kcu.column_name, 
    ccu.table_name AS foreign_table_name,
    ccu.column_name AS foreign_column_name,
    tc.constraint_name
FROM information_schema.table_constraints AS tc 
JOIN information_schema.key_column_usage AS kcu
  ON tc.constraint_name = kcu.constraint_name
  AND tc.table_schema = kcu.table_schema
JOIN information_schema.constraint_column_usage AS ccu
  ON ccu.constraint_name = tc.constraint_name
  AND ccu.table_schema = tc.table_schema
WHERE tc.constraint_type = 'FOREIGN KEY' 
  AND tc.table_schema = 'public'
ORDER BY tc.table_name, kcu.column_name;
