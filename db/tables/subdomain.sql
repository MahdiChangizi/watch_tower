CREATE TABLE IF NOT EXISTS subdomains (
  id SERIAL PRIMARY KEY,
  program_name VARCHAR(255),
  subdomain VARCHAR(255),
  scope VARCHAR(255),
  provider VARCHAR(255),
  created_date TIMESTAMP DEFAULT now(),
  last_update TIMESTAMP DEFAULT now(),
  UNIQUE (program_name, subdomain)
);