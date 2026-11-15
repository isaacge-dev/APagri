-- APagri expansion tables. Safe to run more than once; leaves farm data intact.
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

CREATE INDEX IF NOT EXISTS activity_logs_created_at_idx ON public.activity_logs (created_at DESC);
CREATE INDEX IF NOT EXISTS activity_logs_module_action_idx ON public.activity_logs (module_name, action_type);

-- Initial Administrator (username: admin | password: admin123)
INSERT INTO public.users (full_name, username, email, password_hash, role, is_active, must_change_password)
VALUES ('APagri Administrator', 'admin', 'admin@apagri.local', '$2y$10$aUSLk2tQd67zdYmgApjT.uibSOYH/RcjxyKHkaT8yl0JRmxUF0GHe', 'Administrator', TRUE, FALSE)
ON CONFLICT (username) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    role = 'Administrator',
    is_active = TRUE,
    must_change_password = FALSE,
    updated_at = CURRENT_TIMESTAMP;

-- Initial Staff (username: staff | password: staff123)
INSERT INTO public.users (full_name, username, email, password_hash, role, is_active, must_change_password)
VALUES ('APagri Staff', 'staff', 'staff@apagri.local', '$2y$10$m3PHgh7jhTlGdtN2WiRqu.imFEIDtXlw/Z3CXXOVgp3vJV0/jD1cK', 'Staff', TRUE, FALSE)
ON CONFLICT (username) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    role = 'Staff',
    is_active = TRUE,
    must_change_password = FALSE,
    updated_at = CURRENT_TIMESTAMP;
