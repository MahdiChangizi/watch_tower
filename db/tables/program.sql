CREATE TABLE IF NOT EXISTS programs (
  id SERIAL PRIMARY KEY,
  program_name VARCHAR(255) UNIQUE,
  created_date TIMESTAMP DEFAULT now(),
  config JSONB,
  scopes JSONB,
  ooscopes JSONB
);