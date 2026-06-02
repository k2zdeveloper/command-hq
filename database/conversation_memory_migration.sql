-- ============================================================
-- Mosbat AI — Conversation Memory (long-term summary) Migration
-- Run in: Supabase Dashboard → SQL Editor → New Query
--
-- Stores ONE rolling summary per agent + session. Older messages
-- that fall out of the recent window get folded into this note so
-- agents remember the gist without re-sending the full history.
-- ============================================================

CREATE TABLE IF NOT EXISTS conversation_memory (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    agent_id      UUID NOT NULL REFERENCES agents(id) ON DELETE CASCADE,
    session_id    TEXT NOT NULL,
    summary       TEXT,
    turns_covered INT  NOT NULL DEFAULT 0,
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (agent_id, session_id)
);

CREATE INDEX IF NOT EXISTS conv_memory_agent_idx ON conversation_memory(agent_id);

SELECT * FROM conversation_memory LIMIT 1;
