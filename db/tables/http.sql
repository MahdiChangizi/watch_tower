CREATE TABLE IF NOT EXISTS http (
  id SERIAL PRIMARY KEY,
  program_name VARCHAR(255),
  subdomain VARCHAR(255),
  scope VARCHAR(255),
  ips JSONB,
  tech JSONB,
  title VARCHAR(255),
  status_code VARCHAR(255),
  headers JSONB,
  url VARCHAR(255),
  final_url VARCHAR(255),
  favicon VARCHAR(255),
  created_date TIMESTAMP DEFAULT now(),
  last_update TIMESTAMP DEFAULT now(),
  UNIQUE (program_name, subdomain)
);