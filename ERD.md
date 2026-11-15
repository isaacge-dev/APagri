# APAgri Entity-Relationship Diagram (ERD) & Database Architecture
**System:** APAgri Web-Based Agriculture Information System  
**Course & Project:** CTU AP3 Midterm Project  
**Database Engine:** PostgreSQL 15+ (Supabase)  
**Standard:** Third Normal Form (3NF), Referential Integrity, Auditability & Soft-Delete Preservation  

---

## 1. Mermaid.js Entity-Relationship Diagram

```mermaid
erDiagram
    %% Core Operational Relationships
    FARMER ||--o{ FARM : "owns (1:N)"
    FARM ||--o{ PLANTING : "hosts (1:N)"
    CROP ||--o{ PLANTING : "planted_in (1:N)"
    PLANTING ||--o{ HARVEST : "yields (1:N)"
    HARVEST_REMARK ||--o{ HARVEST : "categorizes (1:N)"

    %% Security & Activity Log Relationships
    USERS ||--o{ ACTIVITY_LOGS : "performs (1:N)"

    %% Reference Lookups (3NF Controlled Domains)
    SOIL_TYPE_LOOKUP ||--o{ FARM : "specifies_soil"
    IRRIGATION_TYPE_LOOKUP ||--o{ FARM : "specifies_irrigation"
    CROP_CATEGORY_LOOKUP ||--o{ CROP : "classifies_crop"
    SEASON_LOOKUP ||--o{ CROP : "recommends_season"
    PLANTING_STATUS_LOOKUP ||--o{ PLANTING : "tracks_status"
    QUALITY_GRADE_LOOKUP ||--o{ HARVEST : "evaluates_quality"

    %% Entity Attributes & Types
    FARMER {
        int farmer_id PK "Identity"
        varchar first_name "NOT NULL"
        varchar last_name "NOT NULL"
        varchar contact_number
        varchar address
        date date_registered "DEFAULT CURRENT_DATE"
        timestamp created_at "Audit"
        timestamp updated_at "Audit"
        timestamp deleted_at "Soft-delete"
        varchar deleted_by "Soft-delete"
    }

    FARM {
        int farm_id PK "Identity"
        int farmer_id FK "REFERENCES farmer(farmer_id) ON DELETE CASCADE"
        varchar farm_name "NOT NULL"
        varchar location
        numeric area_hectares "CHECK (> 0)"
        int soil_type_id FK "REFERENCES soil_type_lookup(soil_type_id)"
        int irrigation_type_id FK "REFERENCES irrigation_type_lookup(irrigation_type_id)"
        timestamp created_at "Audit"
        timestamp updated_at "Audit"
        timestamp deleted_at "Soft-delete"
        varchar deleted_by "Soft-delete"
    }

    CROP {
        int crop_id PK "Identity"
        varchar crop_name "NOT NULL"
        varchar variety "UNIQUE with crop_name"
        int category_id FK "REFERENCES crop_category_lookup(category_id)"
        int growing_period_days "CHECK (> 0)"
        int season_id FK "REFERENCES season_lookup(season_id)"
        timestamp created_at "Audit"
        timestamp updated_at "Audit"
        timestamp deleted_at "Soft-delete"
        varchar deleted_by "Soft-delete"
    }

    PLANTING {
        int planting_id PK "Identity"
        int farm_id FK "REFERENCES farm(farm_id) ON DELETE CASCADE"
        int crop_id FK "REFERENCES crop(crop_id) ON DELETE CASCADE"
        date planting_date "NOT NULL"
        numeric area_planted "CHECK (> 0)"
        numeric seed_quantity "CHECK (>= 0)"
        date expected_harvest_date "CHECK (>= planting_date)"
        int status_id FK "REFERENCES planting_status_lookup(status_id)"
        timestamp created_at "Audit"
        timestamp updated_at "Audit"
        timestamp deleted_at "Soft-delete"
        varchar deleted_by "Soft-delete"
    }

    HARVEST {
        int harvest_id PK "Identity"
        int planting_id FK "REFERENCES planting(planting_id) ON DELETE CASCADE"
        date harvest_date "NOT NULL"
        numeric quantity_kg "CHECK (> 0)"
        numeric selling_price_per_kg "CHECK (>= 0)"
        varchar quality_grade FK "REFERENCES quality_grade_lookup(grade_code)"
        int remark_id FK "REFERENCES harvest_remark(remark_id) ON DELETE SET NULL"
        timestamp created_at "Audit"
        timestamp updated_at "Audit"
        timestamp deleted_at "Soft-delete"
        varchar deleted_by "Soft-delete"
    }

    HARVEST_REMARK {
        int remark_id PK
        varchar remark_description "NOT NULL"
    }

    USERS {
        int user_id PK "Identity"
        varchar full_name "NOT NULL"
        varchar username UK "UNIQUE NOT NULL"
        varchar email UK "UNIQUE NOT NULL"
        varchar password_hash "Bcrypt Hash"
        varchar role "CHECK ('Administrator','Staff')"
        jsonb permissions "DEFAULT '{}'"
        boolean is_active "DEFAULT TRUE"
        boolean must_change_password "DEFAULT FALSE"
        timestamp created_at "Audit"
        timestamp updated_at "Audit"
    }

    ACTIVITY_LOGS {
        int log_id PK "Identity"
        int user_id FK "REFERENCES users(user_id) ON DELETE SET NULL"
        varchar user_name "Denormalized for audit persistence"
        varchar action_type "CREATE / UPDATE / DELETE / LOGIN"
        module_name varchar "Target Module Name"
        varchar record_id "Referenced Record ID"
        text description "Audit Narrative"
        timestamp created_at "Audit"
    }

    SOIL_TYPE_LOOKUP {
        int soil_type_id PK
        varchar soil_name UK "UNIQUE NOT NULL"
        text description
    }

    IRRIGATION_TYPE_LOOKUP {
        int irrigation_type_id PK
        varchar irrigation_name UK "UNIQUE NOT NULL"
        text description
    }

    CROP_CATEGORY_LOOKUP {
        int category_id PK
        varchar category_name UK "UNIQUE NOT NULL"
    }

    SEASON_LOOKUP {
        int season_id PK
        varchar season_name UK "UNIQUE NOT NULL"
    }

    PLANTING_STATUS_LOOKUP {
        int status_id PK
        varchar status_code UK "UNIQUE NOT NULL"
        varchar status_label "Display Label"
    }

    QUALITY_GRADE_LOOKUP {
        varchar grade_code PK "A, B, C, D"
        varchar grade_label "NOT NULL"
        text description
    }
```

---

## 2. DBML Code (Database Markup Language for dbdiagram.io)

Copy and paste the code block below into [dbdiagram.io](https://dbdiagram.io/) to generate high-resolution PNG, PDF, or SVG diagrams:

```dbml
// =============================================================================
// APAgri — Agriculture Information System DBML
// Compatible with dbdiagram.io
// =============================================================================

Table farmer {
  farmer_id int [pk, increment]
  first_name varchar(50) [not null]
  last_name varchar(50) [not null]
  contact_number varchar(20)
  address varchar(150)
  date_registered date [default: `CURRENT_DATE`]
  created_at timestamp [not null, default: `CURRENT_TIMESTAMP`]
  updated_at timestamp [not null, default: `CURRENT_TIMESTAMP`]
  deleted_at timestamp
  deleted_by varchar(100)
}

Table farm {
  farm_id int [pk, increment]
  farmer_id int [not null, ref: > farmer.farmer_id]
  farm_name varchar(100) [not null]
  location varchar(150)
  area_hectares numeric(8,2) [not null, note: 'CHECK area_hectares > 0']
  soil_type_id int [not null, ref: > soil_type_lookup.soil_type_id]
  irrigation_type_id int [ref: > irrigation_type_lookup.irrigation_type_id]
  created_at timestamp [not null, default: `CURRENT_TIMESTAMP`]
  updated_at timestamp [not null, default: `CURRENT_TIMESTAMP`]
  deleted_at timestamp
  deleted_by varchar(100)
}

Table crop {
  crop_id int [pk, increment]
  crop_name varchar(80) [not null]
  variety varchar(80)
  category_id int [not null, ref: > crop_category_lookup.category_id]
  growing_period_days int [not null, note: 'CHECK growing_period_days > 0']
  season_id int [ref: > season_lookup.season_id]
  created_at timestamp [not null, default: `CURRENT_TIMESTAMP`]
  updated_at timestamp [not null, default: `CURRENT_TIMESTAMP`]
  deleted_at timestamp
  deleted_by varchar(100)

  indexes {
    (crop_name, variety) [unique]
  }
}

Table planting {
  planting_id int [pk, increment]
  farm_id int [not null, ref: > farm.farm_id]
  crop_id int [not null, ref: > crop.crop_id]
  planting_date date [not null]
  area_planted numeric(8,2) [not null, note: 'CHECK area_planted > 0']
  seed_quantity numeric(8,2) [not null, default: 0, note: 'CHECK seed_quantity >= 0']
  expected_harvest_date date [note: 'CHECK expected_harvest_date >= planting_date']
  status_id int [not null, default: 1, ref: > planting_status_lookup.status_id]
  created_at timestamp [not null, default: `CURRENT_TIMESTAMP`]
  updated_at timestamp [not null, default: `CURRENT_TIMESTAMP`]
  deleted_at timestamp
  deleted_by varchar(100)
}

Table harvest {
  harvest_id int [pk, increment]
  planting_id int [not null, ref: > planting.planting_id]
  harvest_date date [not null]
  quantity_kg numeric(10,2) [not null, note: 'CHECK quantity_kg > 0']
  selling_price_per_kg numeric(10,2) [note: 'CHECK selling_price_per_kg >= 0']
  quality_grade varchar(10) [ref: > quality_grade_lookup.grade_code]
  remark_id int [ref: > harvest_remark.remark_id]
  created_at timestamp [not null, default: `CURRENT_TIMESTAMP`]
  updated_at timestamp [not null, default: `CURRENT_TIMESTAMP`]
  deleted_at timestamp
  deleted_by varchar(100)
}

Table harvest_remark {
  remark_id int [pk]
  remark_description varchar(80) [not null]
}

Table users {
  user_id int [pk, increment]
  full_name varchar(120) [not null]
  username varchar(80) [unique, not null]
  email varchar(255) [unique, not null]
  password_hash varchar(255) [not null]
  role varchar(20) [not null, note: 'Administrator | Staff']
  permissions jsonb [not null, default: '{}']
  is_active boolean [not null, default: true]
  must_change_password boolean [not null, default: false]
  created_at timestamp [not null, default: `CURRENT_TIMESTAMP`]
  updated_at timestamp [not null, default: `CURRENT_TIMESTAMP`]
}

Table activity_logs {
  log_id int [pk, increment]
  user_id int [ref: > users.user_id]
  user_name varchar(120) [not null]
  action_type varchar(40) [not null]
  module_name varchar(80) [not null]
  record_id varchar(80)
  description text [not null]
  created_at timestamp [not null, default: `CURRENT_TIMESTAMP`]
}

Table soil_type_lookup {
  soil_type_id int [pk, increment]
  soil_name varchar(50) [unique, not null]
  description text
}

Table irrigation_type_lookup {
  irrigation_type_id int [pk, increment]
  irrigation_name varchar(50) [unique, not null]
  description text
}

Table crop_category_lookup {
  category_id int [pk, increment]
  category_name varchar(50) [unique, not null]
}

Table season_lookup {
  season_id int [pk, increment]
  season_name varchar(50) [unique, not null]
}

Table planting_status_lookup {
  status_id int [pk, increment]
  status_code varchar(30) [unique, not null]
  status_label varchar(50) [not null]
}

Table quality_grade_lookup {
  grade_code varchar(10) [pk]
  grade_label varchar(50) [not null]
  description text
}
```

---

## 3. Relational Cardinalities & Business Rules Summary

| Relationship | Cardinality | Parent Entity | Child Entity | Foreign Key | Cascade Rule |
| :--- | :---: | :--- | :--- | :--- | :--- |
| **Farmer to Farm** | `1 : N` | `farmer` | `farm` | `farm.farmer_id` | `ON DELETE CASCADE` |
| **Farm to Planting** | `1 : N` | `farm` | `planting` | `planting.farm_id` | `ON DELETE CASCADE` |
| **Crop to Planting** | `1 : N` | `crop` | `planting` | `planting.crop_id` | `ON DELETE CASCADE` |
| **Planting to Harvest** | `1 : N` | `planting` | `harvest` | `harvest.planting_id` | `ON DELETE CASCADE` |
| **Remark to Harvest** | `1 : N` | `harvest_remark` | `harvest` | `harvest.remark_id` | `ON DELETE SET NULL` |
| **User to Activity Log**| `1 : N` | `users` | `activity_logs`| `activity_logs.user_id`| `ON DELETE SET NULL`|
| **Soil to Farm** | `1 : N` | `soil_type_lookup`| `farm` | `farm.soil_type_id` | `ON DELETE RESTRICT` |
| **Irrigation to Farm** | `1 : N` | `irrigation_type_lookup` | `farm` | `farm.irrigation_type_id` | `ON DELETE SET NULL` |
| **Category to Crop** | `1 : N` | `crop_category_lookup` | `crop` | `crop.category_id` | `ON DELETE RESTRICT` |
| **Season to Crop** | `1 : N` | `season_lookup` | `crop` | `crop.season_id` | `ON DELETE SET NULL` |
| **Status to Planting** | `1 : N` | `planting_status_lookup`| `planting` | `planting.status_id` | `ON DELETE RESTRICT` |
| **Grade to Harvest** | `1 : N` | `quality_grade_lookup` | `harvest` | `harvest.quality_grade` | `ON DELETE SET NULL` |

### Soft-Delete & Child Preservation Rule
In the application layer (`archive.php`), soft-deletion is enforced via `deleted_at IS NULL`. A parent record cannot be archived if it has active child records (e.g., a `farmer` with active `farm` records, or a `farm` with active `planting` records). Restoring a child requires that the parent record has already been restored.
