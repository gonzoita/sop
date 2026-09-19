-- =====================================================================
-- SOPForge — esquema de referencia v1 (MySQL 8)
-- =====================================================================
-- Este archivo es DOCUMENTACIÓN DE REFERENCIA, no el mecanismo de
-- despliegue. El esquema real se crea con migraciones de Laravel.
-- Sirve para: (a) que el agente consulte nombres de tablas y columnas
-- sin leer migraciones, (b) revisar el diseño completo de un vistazo.
--
-- Depende de tablas creadas por Laravel y sus paquetes:
--   users, teams, team_user   (starter kit)
--   roles, permissions, ...   (spatie/laravel-permission)
--   activity_log              (spatie/laravel-activitylog)
--   jobs, failed_jobs, cache, sessions
--
-- Convenciones: InnoDB, utf8mb4, ids BIGINT UNSIGNED, fechas en UTC.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- CLIENTES DE LA AGENCIA
-- ---------------------------------------------------------------------

CREATE TABLE clients (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id         BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) NOT NULL,
    contact_email   VARCHAR(255) NULL,
    status          ENUM('active','paused','archived') NOT NULL DEFAULT 'active',
    meta            JSON NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    deleted_at      TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_clients_team_slug (team_id, slug),
    KEY ix_clients_team (team_id),
    KEY ix_clients_status (status),
    CONSTRAINT fk_clients_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuarios externos con acceso al portal de un cliente.
CREATE TABLE client_user (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id    BIGINT UNSIGNED NOT NULL,
    user_id      BIGINT UNSIGNED NOT NULL,
    role         ENUM('owner','collaborator') NOT NULL DEFAULT 'collaborator',
    invited_at   TIMESTAMP NULL,
    accepted_at  TIMESTAMP NULL,
    created_at   TIMESTAMP NULL,
    updated_at   TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_client_user (client_id, user_id),
    CONSTRAINT fk_client_user_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_client_user_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- SOP: PLANTILLA Y VERSIONES
-- ---------------------------------------------------------------------

CREATE TABLE sops (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id             BIGINT UNSIGNED NOT NULL,
    title               VARCHAR(255) NOT NULL,
    slug                VARCHAR(255) NOT NULL,
    description         TEXT NULL,
    category            VARCHAR(100) NULL,
    status              ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    is_template         TINYINT(1) NOT NULL DEFAULT 0,
    current_version_id  BIGINT UNSIGNED NULL,
    created_by          BIGINT UNSIGNED NULL,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    deleted_at          TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sops_team_slug (team_id, slug),
    KEY ix_sops_team (team_id),
    KEY ix_sops_status (status),
    KEY ix_sops_category (category),
    CONSTRAINT fk_sops_team    FOREIGN KEY (team_id)    REFERENCES teams (id) ON DELETE CASCADE,
    CONSTRAINT fk_sops_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- blocks: { "schema_version": 1, "blocks": [ {id, type, props}, ... ] }
-- published_at NULL = borrador.
CREATE TABLE sop_versions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sop_id          BIGINT UNSIGNED NOT NULL,
    version_number  INT UNSIGNED NOT NULL,
    blocks          JSON NOT NULL,
    changelog       TEXT NULL,
    published_at    TIMESTAMP NULL,
    created_by      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sop_versions (sop_id, version_number),
    KEY ix_sop_versions_published (published_at),
    CONSTRAINT fk_sop_versions_sop     FOREIGN KEY (sop_id)     REFERENCES sops (id)  ON DELETE CASCADE,
    CONSTRAINT fk_sop_versions_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sops
    ADD CONSTRAINT fk_sops_current_version
    FOREIGN KEY (current_version_id) REFERENCES sop_versions (id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- EJECUCIONES
-- ---------------------------------------------------------------------

-- inputs:  { "nombre_marca": "Acme", ... }   valores capturados
-- outputs: { "brief": "texto...", ... }      resultados aprobados por output_key
CREATE TABLE sop_runs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id         BIGINT UNSIGNED NOT NULL,
    sop_id          BIGINT UNSIGNED NOT NULL,
    sop_version_id  BIGINT UNSIGNED NOT NULL,
    client_id       BIGINT UNSIGNED NULL,
    title           VARCHAR(255) NOT NULL,
    status          ENUM('pending','in_progress','awaiting_input','awaiting_approval','completed','cancelled')
                    NOT NULL DEFAULT 'pending',
    inputs          JSON NULL,
    outputs         JSON NULL,
    started_by      BIGINT UNSIGNED NULL,
    assigned_to     BIGINT UNSIGNED NULL,
    started_at      TIMESTAMP NULL,
    completed_at    TIMESTAMP NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY ix_sop_runs_team (team_id),
    KEY ix_sop_runs_status (status),
    KEY ix_sop_runs_client (client_id),
    KEY ix_sop_runs_sop (sop_id),
    CONSTRAINT fk_sop_runs_team    FOREIGN KEY (team_id)        REFERENCES teams (id)        ON DELETE CASCADE,
    CONSTRAINT fk_sop_runs_sop     FOREIGN KEY (sop_id)         REFERENCES sops (id)         ON DELETE CASCADE,
    CONSTRAINT fk_sop_runs_version FOREIGN KEY (sop_version_id) REFERENCES sop_versions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_sop_runs_client  FOREIGN KEY (client_id)      REFERENCES clients (id)      ON DELETE SET NULL,
    CONSTRAINT fk_sop_runs_starter FOREIGN KEY (started_by)     REFERENCES users (id)        ON DELETE SET NULL,
    CONSTRAINT fk_sop_runs_asignee FOREIGN KEY (assigned_to)    REFERENCES users (id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- block_id referencia el "id" del bloque dentro del JSON de la versión.
CREATE TABLE sop_run_steps (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sop_run_id        BIGINT UNSIGNED NOT NULL,
    block_id          VARCHAR(64) NOT NULL,
    block_type        VARCHAR(32) NOT NULL,
    status            ENUM('pending','running','awaiting_approval','approved','rejected','skipped','failed')
                      NOT NULL DEFAULT 'pending',
    assigned_to       BIGINT UNSIGNED NULL,
    output            JSON NULL,
    ai_generation_id  BIGINT UNSIGNED NULL,
    notes             TEXT NULL,
    due_at            TIMESTAMP NULL,
    completed_at      TIMESTAMP NULL,
    created_at        TIMESTAMP NULL,
    updated_at        TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_run_block (sop_run_id, block_id),
    KEY ix_run_steps_status (status),
    CONSTRAINT fk_run_steps_run     FOREIGN KEY (sop_run_id)  REFERENCES sop_runs (id) ON DELETE CASCADE,
    CONSTRAINT fk_run_steps_asignee FOREIGN KEY (assigned_to) REFERENCES users (id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- LIBRERÍA DE SKILLS
-- ---------------------------------------------------------------------

CREATE TABLE skills (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id             BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(255) NOT NULL,
    slug                VARCHAR(255) NOT NULL,
    description         TEXT NULL,
    tags                JSON NULL,
    current_version_id  BIGINT UNSIGNED NULL,
    created_by          BIGINT UNSIGNED NULL,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    deleted_at          TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_skills_team_slug (team_id, slug),
    KEY ix_skills_team (team_id),
    CONSTRAINT fk_skills_team    FOREIGN KEY (team_id)    REFERENCES teams (id) ON DELETE CASCADE,
    CONSTRAINT fk_skills_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- variables: ["nombre_marca","objetivo_campana"]
CREATE TABLE skill_versions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    skill_id        BIGINT UNSIGNED NOT NULL,
    version_number  INT UNSIGNED NOT NULL,
    instructions    LONGTEXT NOT NULL,
    variables       JSON NULL,
    source          ENUM('manual','openrouter','import_markdown') NOT NULL DEFAULT 'manual',
    changelog       TEXT NULL,
    created_by      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_skill_versions (skill_id, version_number),
    CONSTRAINT fk_skill_versions_skill   FOREIGN KEY (skill_id)   REFERENCES skills (id) ON DELETE CASCADE,
    CONSTRAINT fk_skill_versions_creator FOREIGN KEY (created_by) REFERENCES users (id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE skills
    ADD CONSTRAINT fk_skills_current_version
    FOREIGN KEY (current_version_id) REFERENCES skill_versions (id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- IA: CREDENCIALES Y REGISTRO DE LLAMADAS
-- ---------------------------------------------------------------------

-- api_key se guarda CIFRADA por la aplicación (cast 'encrypted' de Eloquent).
-- Nunca en texto plano, nunca en logs.
CREATE TABLE ai_credentials (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id       BIGINT UNSIGNED NOT NULL,
    provider      VARCHAR(50) NOT NULL DEFAULT 'openrouter',
    label         VARCHAR(255) NULL,
    api_key       TEXT NOT NULL,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    last_used_at  TIMESTAMP NULL,
    created_by    BIGINT UNSIGNED NULL,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY ix_ai_credentials_team (team_id),
    CONSTRAINT fk_ai_cred_team    FOREIGN KEY (team_id)    REFERENCES teams (id) ON DELETE CASCADE,
    CONSTRAINT fk_ai_cred_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de mayor crecimiento del sistema: programar archivado/purga.
CREATE TABLE ai_generations (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id           BIGINT UNSIGNED NOT NULL,
    sop_run_id        BIGINT UNSIGNED NULL,
    sop_run_step_id   BIGINT UNSIGNED NULL,
    skill_version_id  BIGINT UNSIGNED NULL,
    provider          VARCHAR(50) NOT NULL DEFAULT 'openrouter',
    model             VARCHAR(190) NOT NULL,
    prompt            LONGTEXT NOT NULL,
    response          LONGTEXT NULL,
    input_tokens      INT UNSIGNED NULL,
    output_tokens     INT UNSIGNED NULL,
    cost_usd          DECIMAL(10,6) NULL,
    latency_ms        INT UNSIGNED NULL,
    status            ENUM('queued','running','succeeded','failed') NOT NULL DEFAULT 'queued',
    error             TEXT NULL,
    created_at        TIMESTAMP NULL,
    updated_at        TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY ix_ai_gen_team_created (team_id, created_at),
    KEY ix_ai_gen_status (status),
    KEY ix_ai_gen_run (sop_run_id),
    CONSTRAINT fk_ai_gen_team    FOREIGN KEY (team_id)          REFERENCES teams (id)          ON DELETE CASCADE,
    CONSTRAINT fk_ai_gen_run     FOREIGN KEY (sop_run_id)       REFERENCES sop_runs (id)       ON DELETE SET NULL,
    CONSTRAINT fk_ai_gen_step    FOREIGN KEY (sop_run_step_id)  REFERENCES sop_run_steps (id)  ON DELETE SET NULL,
    CONSTRAINT fk_ai_gen_skillv  FOREIGN KEY (skill_version_id) REFERENCES skill_versions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sop_run_steps
    ADD CONSTRAINT fk_run_steps_generation
    FOREIGN KEY (ai_generation_id) REFERENCES ai_generations (id) ON DELETE SET NULL;

-- Límite de gasto mensual por equipo (control de costos).
CREATE TABLE ai_budgets (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id            BIGINT UNSIGNED NOT NULL,
    monthly_limit_usd  DECIMAL(10,2) NOT NULL DEFAULT 50.00,
    alert_at_percent   TINYINT UNSIGNED NOT NULL DEFAULT 80,
    created_at         TIMESTAMP NULL,
    updated_at         TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ai_budgets_team (team_id),
    CONSTRAINT fk_ai_budgets_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- AUTOMATIZACIÓN
-- ---------------------------------------------------------------------

CREATE TABLE automation_triggers (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id     BIGINT UNSIGNED NOT NULL,
    event       VARCHAR(100) NOT NULL,      -- client.created, run.completed, run.step.approved
    sop_id      BIGINT UNSIGNED NOT NULL,
    conditions  JSON NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_by  BIGINT UNSIGNED NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY ix_triggers_team_event (team_id, event),
    CONSTRAINT fk_triggers_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE,
    CONSTRAINT fk_triggers_sop  FOREIGN KEY (sop_id)  REFERENCES sops (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- EXPORTACIÓN
-- ---------------------------------------------------------------------

CREATE TABLE skill_exports (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id           BIGINT UNSIGNED NOT NULL,
    skill_version_id  BIGINT UNSIGNED NOT NULL,
    target            ENUM('markdown','gpt','gem','claude_project') NOT NULL,
    payload           LONGTEXT NOT NULL,
    generated_by      BIGINT UNSIGNED NULL,
    created_at        TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY ix_exports_team (team_id),
    CONSTRAINT fk_exports_team   FOREIGN KEY (team_id)          REFERENCES teams (id)          ON DELETE CASCADE,
    CONSTRAINT fk_exports_skillv FOREIGN KEY (skill_version_id) REFERENCES skill_versions (id) ON DELETE CASCADE,
    CONSTRAINT fk_exports_user   FOREIGN KEY (generated_by)     REFERENCES users (id)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CUMPLIMIENTO: SOLICITUDES DE DATOS
-- ---------------------------------------------------------------------

CREATE TABLE data_requests (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id       BIGINT UNSIGNED NOT NULL,
    client_id     BIGINT UNSIGNED NULL,
    type          ENUM('export','delete') NOT NULL,
    status        ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    requested_by  BIGINT UNSIGNED NULL,
    file_path     VARCHAR(500) NULL,
    completed_at  TIMESTAMP NULL,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY ix_data_requests_team (team_id),
    CONSTRAINT fk_data_req_team   FOREIGN KEY (team_id)      REFERENCES teams (id)   ON DELETE CASCADE,
    CONSTRAINT fk_data_req_client FOREIGN KEY (client_id)    REFERENCES clients (id) ON DELETE SET NULL,
    CONSTRAINT fk_data_req_user   FOREIGN KEY (requested_by) REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
