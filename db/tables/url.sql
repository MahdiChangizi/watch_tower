CREATE TABLE IF NOT EXISTS urls (
  id SERIAL PRIMARY KEY,
  program_name VARCHAR(255) NOT NULL,
  scope VARCHAR(255),
  url TEXT NOT NULL,
  parameters JSONB DEFAULT '[]'::jsonb,
  source VARCHAR(64) NOT NULL DEFAULT 'waybackurls',
  occurrences INTEGER NOT NULL DEFAULT 1,
  first_seen TIMESTAMP NOT NULL DEFAULT now(),
  last_seen TIMESTAMP NOT NULL DEFAULT now(),
  UNIQUE (program_name, url)
);

CREATE INDEX IF NOT EXISTS idx_urls_program_name ON urls (program_name);
CREATE INDEX IF NOT EXISTS idx_urls_scope ON urls (scope);
CREATE INDEX IF NOT EXISTS idx_urls_source ON urls (source);

