-- ============================================================
-- Mosbat AI — Artifacts (soft-copy file store) Migration
-- Run in: Supabase Dashboard → SQL Editor → New Query
--
-- Stores metadata for every file an agent produces:
-- images, documents, reports, etc. The actual files live in
-- public/generated/ and are referenced here by URL.
-- ============================================================

CREATE TABLE IF NOT EXISTS artifacts (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id   TEXT NOT NULL,
    agent_id     UUID REFERENCES agents(id) ON DELETE SET NULL,
    task_id      UUID,
    type         TEXT NOT NULL DEFAULT 'document'
                 CHECK (type IN ('image','document','text','other')),
    title        TEXT NOT NULL,
    file_url     TEXT NOT NULL,
    mime         TEXT,
    size_bytes   BIGINT,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS artifacts_company_idx ON artifacts(company_id);
CREATE INDEX IF NOT EXISTS artifacts_agent_idx   ON artifacts(agent_id);
CREATE INDEX IF NOT EXISTS artifacts_created_idx  ON artifacts(created_at DESC);

SELECT * FROM artifacts LIMIT 1;
