-- Executed by Postgres on first run
CREATE TABLE IF NOT EXISTS process_metrics (
  ts TIMESTAMPTZ DEFAULT now(),
  conveyor_run BOOLEAN,
  emergency_ok BOOLEAN,
  quality_score INT
);
