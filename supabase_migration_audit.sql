-- ============================================================
-- APagri Pre-Submission Audit Migration Script
-- Safe to run on live Supabase DB (non-destructive)
-- Run this ENTIRE script once in Supabase SQL Editor
-- ============================================================

-- ============================================================
-- SECTION 1: Soft-delete columns (IF NOT EXISTS = safe repeat)
-- ============================================================
ALTER TABLE public.farmer  ADD COLUMN IF NOT EXISTS deleted_at  TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE public.farmer  ADD COLUMN IF NOT EXISTS deleted_by  VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE public.farm    ADD COLUMN IF NOT EXISTS deleted_at  TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE public.farm    ADD COLUMN IF NOT EXISTS deleted_by  VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE public.crop    ADD COLUMN IF NOT EXISTS deleted_at  TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE public.crop    ADD COLUMN IF NOT EXISTS deleted_by  VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE public.planting ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE public.planting ADD COLUMN IF NOT EXISTS deleted_by VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE public.harvest ADD COLUMN IF NOT EXISTS deleted_at  TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE public.harvest ADD COLUMN IF NOT EXISTS deleted_by  VARCHAR(100) NULL DEFAULT NULL;

-- ============================================================
-- SECTION 2: Audit timestamps (created_at / updated_at)
-- ============================================================
ALTER TABLE public.farmer  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.farmer  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE public.farm    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.farm    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE public.crop    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.crop    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE public.planting ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.planting ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE public.harvest ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.harvest ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- ============================================================
-- SECTION 3: Auto-update trigger for updated_at
-- ============================================================
CREATE OR REPLACE FUNCTION public.set_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

-- Attach trigger to each core table (drop first to allow re-runs)
DROP TRIGGER IF EXISTS trg_farmer_updated_at   ON public.farmer;
DROP TRIGGER IF EXISTS trg_farm_updated_at     ON public.farm;
DROP TRIGGER IF EXISTS trg_crop_updated_at     ON public.crop;
DROP TRIGGER IF EXISTS trg_planting_updated_at ON public.planting;
DROP TRIGGER IF EXISTS trg_harvest_updated_at  ON public.harvest;

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

-- ============================================================
-- SECTION 4: CHECK constraints (safe; skip if already exists)
-- ============================================================
DO $$ BEGIN
    ALTER TABLE public.farm ADD CONSTRAINT chk_farm_area_positive CHECK (area_hectares > 0);
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    ALTER TABLE public.planting ADD CONSTRAINT chk_planting_area_positive CHECK (area_planted > 0);
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    ALTER TABLE public.planting ADD CONSTRAINT chk_planting_seed_nonneg CHECK (seed_quantity >= 0);
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    ALTER TABLE public.planting ADD CONSTRAINT chk_planting_dates CHECK (expected_harvest_date >= planting_date);
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    ALTER TABLE public.harvest ADD CONSTRAINT chk_harvest_qty_positive CHECK (quantity_kg > 0);
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    ALTER TABLE public.harvest ADD CONSTRAINT chk_harvest_price_nonneg CHECK (selling_price_per_kg >= 0);
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    ALTER TABLE public.crop ADD CONSTRAINT chk_crop_period_positive CHECK (growing_period_days > 0);
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

-- ============================================================
-- SECTION 5: Fix quality_grade data
-- Normalize 'Grade A/B/C' → single letters A/B/C/D
-- ============================================================
UPDATE public.harvest SET quality_grade = 'A' WHERE quality_grade ILIKE 'Grade A' OR quality_grade ILIKE '%grade%a%';
UPDATE public.harvest SET quality_grade = 'B' WHERE quality_grade ILIKE 'Grade B' OR quality_grade ILIKE '%grade%b%';
UPDATE public.harvest SET quality_grade = 'C' WHERE quality_grade ILIKE 'Grade C' OR quality_grade ILIKE '%grade%c%';
UPDATE public.harvest SET quality_grade = 'D' WHERE quality_grade ILIKE 'Grade D' OR quality_grade ILIKE '%grade%d%';

-- ============================================================
-- SECTION 6: Fix crop_category to match dropdown values (plural)
-- ============================================================
UPDATE public.crop SET crop_category = 'Cereals'    WHERE crop_category = 'Cereal';
UPDATE public.crop SET crop_category = 'Vegetables' WHERE crop_category = 'Vegetable';
UPDATE public.crop SET crop_category = 'Fruits'     WHERE crop_category = 'Fruit';
UPDATE public.crop SET crop_category = 'Legumes'    WHERE crop_category = 'Legume';
UPDATE public.crop SET crop_category = 'Root Crops' WHERE crop_category = 'Root Crop';

-- ============================================================
-- SECTION 7: Fix recommended_season to match dropdown values
-- ============================================================
UPDATE public.crop SET recommended_season = 'Year-round' WHERE recommended_season IN ('Year Round', 'Year round', 'year round');

-- ============================================================
-- SECTION 8: Fix farm irrigation_type to match dropdown values
-- ============================================================
UPDATE public.farm SET irrigation_type = 'Rain-fed' WHERE irrigation_type IN ('Rainfed', 'Rain fed');
UPDATE public.farm SET irrigation_type = 'Drip'     WHERE irrigation_type = 'Drip Irrigation';

-- Add Sandy Loam and Clay Loam note:
-- soil_type values 'Sandy Loam' and 'Clay Loam' in DB are KEPT as-is
-- because we will add them to the PHP dropdowns (see farms.php update)

-- ============================================================
-- SECTION 9: Fix planting status to lowercase (matches PHP badge logic)
-- ============================================================
UPDATE public.planting SET status = 'planned'   WHERE status = 'Planned';
UPDATE public.planting SET status = 'planted'   WHERE status = 'Planted';
UPDATE public.planting SET status = 'growing'   WHERE status = 'Growing';
UPDATE public.planting SET status = 'harvested' WHERE status = 'Harvested';
UPDATE public.planting SET status = 'failed'    WHERE status = 'Failed';

-- ============================================================
-- SECTION 10: Ensure harvest_remark has the correct 6 entries
-- ============================================================
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

-- ============================================================
-- SECTION 11: UNIQUE constraint on crop (crop_name + variety)
-- ============================================================
DO $$ BEGIN
    ALTER TABLE public.crop ADD CONSTRAINT uq_crop_name_variety UNIQUE (crop_name, variety);
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

-- ============================================================
-- SECTION 12: Verify — run SELECTs to confirm
-- ============================================================
SELECT 'farmer columns' AS check_name, column_name FROM information_schema.columns
  WHERE table_schema='public' AND table_name='farmer'
  AND column_name IN ('created_at','updated_at','deleted_at')
UNION ALL
SELECT 'harvest quality_grade sample', quality_grade::text FROM public.harvest LIMIT 3;
