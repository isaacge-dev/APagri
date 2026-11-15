-- =============================================================================
-- APAgri System — Master Idempotent Migration Script (migration_update.sql)
-- Database Engine: PostgreSQL 15+ (Supabase)
-- Standard: Full 3NF Normalization, Referential Integrity & Audit Trail
-- Safe for execution on live database (non-destructive, preserves all data)
-- =============================================================================

-- =============================================================================
-- 1. REFERENCE / LOOKUP TABLES (3NF Controlled Vocabularies)
-- =============================================================================

-- 1.1 Harvest Remarks (Primary lookup mandated by rubric)
CREATE TABLE IF NOT EXISTS public.harvest_remark (
    remark_id INT PRIMARY KEY,
    remark_description VARCHAR(80) NOT NULL
);

INSERT INTO public.harvest_remark (remark_id, remark_description)
VALUES
    (1, 'High quality harvest'),
    (2, 'Good harvest'),
    (3, 'Acceptable quality'),
    (4, 'Low Yield'),
    (5, 'Poor Quality'),
    (6, 'Damaged Harvest')
ON CONFLICT (remark_id) DO UPDATE 
SET remark_description = EXCLUDED.remark_description;

-- 1.2 Soil Types Lookup Table
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

-- 1.3 Irrigation Types Lookup Table
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
    ('None', 'Zero irrigation infrastructure installed')
ON CONFLICT (irrigation_name) DO NOTHING;

-- 1.4 Crop Categories Lookup Table
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

-- 1.5 Recommended Seasons Lookup Table
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

-- 1.6 Planting Statuses Lookup Table
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

-- 1.7 Quality Grades Lookup Table
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
-- 2. CORE OPERATIONAL TABLES (1NF -> 2NF -> 3NF)
-- =============================================================================

-- 2.1 Farmer Table
CREATE TABLE IF NOT EXISTS public.farmer (
    farmer_id INT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    contact_number VARCHAR(20),
    address VARCHAR(150),
    date_registered DATE DEFAULT CURRENT_DATE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by VARCHAR(100) NULL DEFAULT NULL
);

ALTER TABLE public.farmer ADD COLUMN IF NOT EXISTS first_name VARCHAR(50);
ALTER TABLE public.farmer ADD COLUMN IF NOT EXISTS last_name VARCHAR(50);
ALTER TABLE public.farmer ADD COLUMN IF NOT EXISTS contact_number VARCHAR(20);
ALTER TABLE public.farmer ADD COLUMN IF NOT EXISTS address VARCHAR(150);
ALTER TABLE public.farmer ADD COLUMN IF NOT EXISTS date_registered DATE DEFAULT CURRENT_DATE;
ALTER TABLE public.farmer ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.farmer ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.farmer ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE public.farmer ADD COLUMN IF NOT EXISTS deleted_by VARCHAR(100) NULL DEFAULT NULL;

-- 2.2 Farm Table
CREATE TABLE IF NOT EXISTS public.farm (
    farm_id INT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
    farmer_id INT NOT NULL,
    farm_name VARCHAR(100) NOT NULL,
    location VARCHAR(150),
    area_hectares NUMERIC(8,2),
    soil_type VARCHAR(50),
    irrigation_type VARCHAR(50),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by VARCHAR(100) NULL DEFAULT NULL
);

ALTER TABLE public.farm ADD COLUMN IF NOT EXISTS farmer_id INT;
ALTER TABLE public.farm ADD COLUMN IF NOT EXISTS farm_name VARCHAR(100);
ALTER TABLE public.farm ADD COLUMN IF NOT EXISTS location VARCHAR(150);
ALTER TABLE public.farm ADD COLUMN IF NOT EXISTS area_hectares NUMERIC(8,2);
ALTER TABLE public.farm ADD COLUMN IF NOT EXISTS soil_type VARCHAR(50);
ALTER TABLE public.farm ADD COLUMN IF NOT EXISTS irrigation_type VARCHAR(50);
ALTER TABLE public.farm ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.farm ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.farm ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE public.farm ADD COLUMN IF NOT EXISTS deleted_by VARCHAR(100) NULL DEFAULT NULL;

-- 2.3 Crop Table
CREATE TABLE IF NOT EXISTS public.crop (
    crop_id INT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
    crop_name VARCHAR(80) NOT NULL,
    variety VARCHAR(80),
    crop_category VARCHAR(50),
    growing_period_days INT,
    recommended_season VARCHAR(30),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by VARCHAR(100) NULL DEFAULT NULL
);

ALTER TABLE public.crop ADD COLUMN IF NOT EXISTS crop_name VARCHAR(80);
ALTER TABLE public.crop ADD COLUMN IF NOT EXISTS variety VARCHAR(80);
ALTER TABLE public.crop ADD COLUMN IF NOT EXISTS crop_category VARCHAR(50);
ALTER TABLE public.crop ADD COLUMN IF NOT EXISTS growing_period_days INT;
ALTER TABLE public.crop ADD COLUMN IF NOT EXISTS recommended_season VARCHAR(30);
ALTER TABLE public.crop ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.crop ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.crop ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE public.crop ADD COLUMN IF NOT EXISTS deleted_by VARCHAR(100) NULL DEFAULT NULL;

-- 2.4 Planting Table
CREATE TABLE IF NOT EXISTS public.planting (
    planting_id INT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
    farm_id INT NOT NULL,
    crop_id INT NOT NULL,
    planting_date DATE NOT NULL,
    area_planted NUMERIC(8,2),
    seed_quantity NUMERIC(8,2),
    expected_harvest_date DATE,
    status VARCHAR(30) NOT NULL DEFAULT 'planned',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by VARCHAR(100) NULL DEFAULT NULL
);

ALTER TABLE public.planting ADD COLUMN IF NOT EXISTS farm_id INT;
ALTER TABLE public.planting ADD COLUMN IF NOT EXISTS crop_id INT;
ALTER TABLE public.planting ADD COLUMN IF NOT EXISTS planting_date DATE;
ALTER TABLE public.planting ADD COLUMN IF NOT EXISTS area_planted NUMERIC(8,2);
ALTER TABLE public.planting ADD COLUMN IF NOT EXISTS seed_quantity NUMERIC(8,2);
ALTER TABLE public.planting ADD COLUMN IF NOT EXISTS expected_harvest_date DATE;
ALTER TABLE public.planting ADD COLUMN IF NOT EXISTS status VARCHAR(30) DEFAULT 'planned';
ALTER TABLE public.planting ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.planting ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.planting ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE public.planting ADD COLUMN IF NOT EXISTS deleted_by VARCHAR(100) NULL DEFAULT NULL;

-- 2.5 Harvest Table
CREATE TABLE IF NOT EXISTS public.harvest (
    harvest_id INT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
    planting_id INT NOT NULL,
    harvest_date DATE NOT NULL,
    quantity_kg NUMERIC(10,2) NOT NULL,
    selling_price_per_kg NUMERIC(10,2),
    quality_grade VARCHAR(30),
    remark_id INT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by VARCHAR(100) NULL DEFAULT NULL
);

ALTER TABLE public.harvest ADD COLUMN IF NOT EXISTS planting_id INT;
ALTER TABLE public.harvest ADD COLUMN IF NOT EXISTS harvest_date DATE;
ALTER TABLE public.harvest ADD COLUMN IF NOT EXISTS quantity_kg NUMERIC(10,2);
ALTER TABLE public.harvest ADD COLUMN IF NOT EXISTS selling_price_per_kg NUMERIC(10,2);
ALTER TABLE public.harvest ADD COLUMN IF NOT EXISTS quality_grade VARCHAR(30);
ALTER TABLE public.harvest ADD COLUMN IF NOT EXISTS remark_id INT;
ALTER TABLE public.harvest ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.harvest ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.harvest ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE public.harvest ADD COLUMN IF NOT EXISTS deleted_by VARCHAR(100) NULL DEFAULT NULL;


-- =============================================================================
-- 3. SYSTEM, AUTHENTICATION & AUDITING TABLES
-- =============================================================================

-- 3.1 Users Table (Admin & Staff)
CREATE TABLE IF NOT EXISTS public.users (
    user_id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(80) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('Administrator', 'Staff')),
    permissions JSONB NOT NULL DEFAULT '{}'::jsonb,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    must_change_password BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE public.users ADD COLUMN IF NOT EXISTS full_name VARCHAR(120);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS username VARCHAR(80);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS email VARCHAR(255);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS role VARCHAR(20);
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS permissions JSONB DEFAULT '{}'::jsonb;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS must_change_password BOOLEAN DEFAULT FALSE;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- 3.2 Activity Logs Table
CREATE TABLE IF NOT EXISTS public.activity_logs (
    log_id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id INT REFERENCES public.users(user_id) ON DELETE SET NULL,
    user_name VARCHAR(120) NOT NULL,
    action_type VARCHAR(40) NOT NULL,
    module_name VARCHAR(80) NOT NULL,
    record_id VARCHAR(80),
    description TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE public.activity_logs ADD COLUMN IF NOT EXISTS user_id INT;
ALTER TABLE public.activity_logs ADD COLUMN IF NOT EXISTS user_name VARCHAR(120);
ALTER TABLE public.activity_logs ADD COLUMN IF NOT EXISTS action_type VARCHAR(40);
ALTER TABLE public.activity_logs ADD COLUMN IF NOT EXISTS module_name VARCHAR(80);
ALTER TABLE public.activity_logs ADD COLUMN IF NOT EXISTS record_id VARCHAR(80);
ALTER TABLE public.activity_logs ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE public.activity_logs ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;


-- =============================================================================
-- 4. CONDITIONAL CONSTRAINTS & REFERENTIAL INTEGRITY (Idempotent)
-- =============================================================================

-- 4.1 Farm Constraints
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_farm_farmer') THEN
        ALTER TABLE public.farm ADD CONSTRAINT fk_farm_farmer 
            FOREIGN KEY (farmer_id) REFERENCES public.farmer(farmer_id) ON DELETE CASCADE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_farm_area_positive') THEN
        ALTER TABLE public.farm ADD CONSTRAINT chk_farm_area_positive 
            CHECK (area_hectares > 0);
    END IF;
EXCEPTION WHEN duplicate_object OR duplicate_table THEN NULL; END $$;

-- 4.2 Crop Constraints
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_crop_name_variety') THEN
        ALTER TABLE public.crop ADD CONSTRAINT uq_crop_name_variety 
            UNIQUE (crop_name, variety);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_crop_period_positive') THEN
        ALTER TABLE public.crop ADD CONSTRAINT chk_crop_period_positive 
            CHECK (growing_period_days > 0);
    END IF;
EXCEPTION WHEN duplicate_object OR duplicate_table THEN NULL; END $$;

-- 4.3 Planting Constraints
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_planting_farm') THEN
        ALTER TABLE public.planting ADD CONSTRAINT fk_planting_farm 
            FOREIGN KEY (farm_id) REFERENCES public.farm(farm_id) ON DELETE CASCADE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_planting_crop') THEN
        ALTER TABLE public.planting ADD CONSTRAINT fk_planting_crop 
            FOREIGN KEY (crop_id) REFERENCES public.crop(crop_id) ON DELETE CASCADE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_planting_area_positive') THEN
        ALTER TABLE public.planting ADD CONSTRAINT chk_planting_area_positive 
            CHECK (area_planted > 0);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_planting_seed_nonneg') THEN
        ALTER TABLE public.planting ADD CONSTRAINT chk_planting_seed_nonneg 
            CHECK (seed_quantity >= 0);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_planting_dates') THEN
        ALTER TABLE public.planting ADD CONSTRAINT chk_planting_dates 
            CHECK (expected_harvest_date IS NULL OR expected_harvest_date >= planting_date);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_planting_status_valid') THEN
        ALTER TABLE public.planting ADD CONSTRAINT chk_planting_status_valid 
            CHECK (status IN ('planned', 'planted', 'growing', 'harvested', 'failed'));
    END IF;
EXCEPTION WHEN duplicate_object OR duplicate_table THEN NULL; END $$;

-- 4.4 Harvest Constraints
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_harvest_planting') THEN
        ALTER TABLE public.harvest ADD CONSTRAINT fk_harvest_planting 
            FOREIGN KEY (planting_id) REFERENCES public.planting(planting_id) ON DELETE CASCADE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_harvest_remark') THEN
        ALTER TABLE public.harvest ADD CONSTRAINT fk_harvest_remark 
            FOREIGN KEY (remark_id) REFERENCES public.harvest_remark(remark_id) ON DELETE SET NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_harvest_qty_positive') THEN
        ALTER TABLE public.harvest ADD CONSTRAINT chk_harvest_qty_positive 
            CHECK (quantity_kg > 0);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_harvest_price_nonneg') THEN
        ALTER TABLE public.harvest ADD CONSTRAINT chk_harvest_price_nonneg 
            CHECK (selling_price_per_kg IS NULL OR selling_price_per_kg >= 0);
    END IF;
EXCEPTION WHEN duplicate_object OR duplicate_table THEN NULL; END $$;

-- 4.5 Activity Logs Constraint
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_activity_logs_user') THEN
        ALTER TABLE public.activity_logs ADD CONSTRAINT fk_activity_logs_user 
            FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON DELETE SET NULL;
    END IF;
EXCEPTION WHEN duplicate_object OR duplicate_table THEN NULL; END $$;


-- =============================================================================
-- 5. AUTOMATIC TIMESTAMP TRIGGERS (updated_at)
-- =============================================================================

CREATE OR REPLACE FUNCTION public.set_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_farmer_updated_at   ON public.farmer;
DROP TRIGGER IF EXISTS trg_farm_updated_at     ON public.farm;
DROP TRIGGER IF EXISTS trg_crop_updated_at     ON public.crop;
DROP TRIGGER IF EXISTS trg_planting_updated_at ON public.planting;
DROP TRIGGER IF EXISTS trg_harvest_updated_at  ON public.harvest;
DROP TRIGGER IF EXISTS trg_users_updated_at    ON public.users;

CREATE TRIGGER trg_farmer_updated_at
    BEFORE UPDATE ON public.farmer
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE TRIGGER trg_farm_updated_at
    BEFORE UPDATE ON public.farm
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE TRIGGER trg_crop_updated_at
    BEFORE UPDATE ON public.crop
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE TRIGGER trg_planting_updated_at
    BEFORE UPDATE ON public.planting
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE TRIGGER trg_harvest_updated_at
    BEFORE UPDATE ON public.harvest
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE TRIGGER trg_users_updated_at
    BEFORE UPDATE ON public.users
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();


-- =============================================================================
-- 6. INDEXES FOR QUERY OPTIMIZATION
-- =============================================================================

CREATE INDEX IF NOT EXISTS idx_farm_farmer_id       ON public.farm (farmer_id);
CREATE INDEX IF NOT EXISTS idx_farm_deleted_at      ON public.farm (deleted_at);
CREATE INDEX IF NOT EXISTS idx_farmer_deleted_at    ON public.farmer (deleted_at);
CREATE INDEX IF NOT EXISTS idx_crop_deleted_at      ON public.crop (deleted_at);
CREATE INDEX IF NOT EXISTS idx_planting_farm_id     ON public.planting (farm_id);
CREATE INDEX IF NOT EXISTS idx_planting_crop_id     ON public.planting (crop_id);
CREATE INDEX IF NOT EXISTS idx_planting_deleted_at  ON public.planting (deleted_at);
CREATE INDEX IF NOT EXISTS idx_planting_status      ON public.planting (status);
CREATE INDEX IF NOT EXISTS idx_harvest_planting_id  ON public.harvest (planting_id);
CREATE INDEX IF NOT EXISTS idx_harvest_remark_id    ON public.harvest (remark_id);
CREATE INDEX IF NOT EXISTS idx_harvest_deleted_at   ON public.harvest (deleted_at);
CREATE INDEX IF NOT EXISTS idx_harvest_date         ON public.harvest (harvest_date);
CREATE INDEX IF NOT EXISTS idx_activity_logs_created ON public.activity_logs (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_activity_logs_mod_act ON public.activity_logs (module_name, action_type);
CREATE INDEX IF NOT EXISTS idx_users_username_lower ON public.users (LOWER(username));


-- =============================================================================
-- 7. DATA CLEANING & STANDARDIZATION (Synchronizing with PHP UI)
-- =============================================================================

-- 7.1 Normalize Quality Grades to Single Letter Codes
UPDATE public.harvest SET quality_grade = 'A' WHERE quality_grade ILIKE 'Grade A' OR quality_grade ILIKE '%A%';
UPDATE public.harvest SET quality_grade = 'B' WHERE quality_grade ILIKE 'Grade B' OR quality_grade ILIKE '%B%';
UPDATE public.harvest SET quality_grade = 'C' WHERE quality_grade ILIKE 'Grade C' OR quality_grade ILIKE '%C%';
UPDATE public.harvest SET quality_grade = 'D' WHERE quality_grade ILIKE 'Grade D' OR quality_grade ILIKE '%D%';

-- 7.2 Normalize Crop Categories (Plural forms matching dropdown options)
UPDATE public.crop SET crop_category = 'Cereals'    WHERE crop_category IN ('Cereal', 'cereals');
UPDATE public.crop SET crop_category = 'Vegetables' WHERE crop_category IN ('Vegetable', 'vegetables');
UPDATE public.crop SET crop_category = 'Fruits'     WHERE crop_category IN ('Fruit', 'fruits');
UPDATE public.crop SET crop_category = 'Legumes'    WHERE crop_category IN ('Legume', 'legumes');
UPDATE public.crop SET crop_category = 'Root Crops' WHERE crop_category IN ('Root Crop', 'root crops');

-- 7.3 Normalize Recommended Seasons
UPDATE public.crop SET recommended_season = 'Year-round' 
WHERE recommended_season IN ('Year Round', 'Year round', 'year round');

-- 7.4 Normalize Farm Irrigation Types
UPDATE public.farm SET irrigation_type = 'Rain-fed' WHERE irrigation_type IN ('Rainfed', 'Rain fed');
UPDATE public.farm SET irrigation_type = 'Drip'     WHERE irrigation_type IN ('Drip Irrigation');

-- 7.5 Normalize Planting Statuses to Lowercase
UPDATE public.planting SET status = 'planned'   WHERE LOWER(status) = 'planned';
UPDATE public.planting SET status = 'planted'   WHERE LOWER(status) = 'planted';
UPDATE public.planting SET status = 'growing'   WHERE LOWER(status) = 'growing';
UPDATE public.planting SET status = 'harvested' WHERE LOWER(status) = 'harvested';
UPDATE public.planting SET status = 'failed'    WHERE LOWER(status) = 'failed';


-- =============================================================================
-- 8. DEFAULT USERS SEEDING (Admin & Staff)
-- =============================================================================

-- Administrator (admin / admin123)
INSERT INTO public.users (full_name, username, email, password_hash, role, is_active, must_change_password)
VALUES (
    'APagri Administrator', 
    'admin', 
    'admin@apagri.local', 
    '$2y$10$aUSLk2tQd67zdYmgApjT.uibSOYH/RcjxyKHkaT8yl0JRmxUF0GHe', 
    'Administrator', 
    TRUE, 
    FALSE
)
ON CONFLICT (username) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    role = 'Administrator',
    is_active = TRUE,
    must_change_password = FALSE,
    updated_at = CURRENT_TIMESTAMP;

-- Staff (staff / staff123)
INSERT INTO public.users (full_name, username, email, password_hash, role, is_active, must_change_password)
VALUES (
    'APagri Staff', 
    'staff', 
    'staff@apagri.local', 
    '$2y$10$m3PHgh7jhTlGdtN2WiRqu.imFEIDtXlw/Z3CXXOVgp3vJV0/jD1cK', 
    'Staff', 
    TRUE, 
    FALSE
)
ON CONFLICT (username) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    role = 'Staff',
    is_active = TRUE,
    must_change_password = FALSE,
    updated_at = CURRENT_TIMESTAMP;


-- =============================================================================
-- 9. POST-MIGRATION VERIFICATION
-- =============================================================================

SELECT 'Migration completed successfully. Tables and audit columns verified.' AS status;
