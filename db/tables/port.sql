CREATE TABLE IF NOT EXISTS ports (
  id SERIAL PRIMARY KEY,
  program_name VARCHAR(255),
  subdomain VARCHAR(255),
  host VARCHAR(255),
  port INTEGER,
  protocol VARCHAR(32),
  service VARCHAR(255),
  source VARCHAR(64),
  metadata JSONB,
  created_date TIMESTAMP DEFAULT now(),
  last_update TIMESTAMP DEFAULT now(),
  UNIQUE (program_name, subdomain, host, port, protocol)
);

