CREATE TABLE IF NOT EXISTS officers (
  officer_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  discord_id VARCHAR(32) NOT NULL,
  discord_username VARCHAR(100) NOT NULL,
  marks INT NOT NULL DEFAULT 0,
  rank VARCHAR(64) DEFAULT NULL,
  is_blacklisted TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (officer_id),
  UNIQUE KEY uq_officers_discord_id (discord_id),
  KEY idx_officers_marks (marks)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS officer_audit_logs (
  log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  action VARCHAR(64) NOT NULL,
  actor_discord_id VARCHAR(32) NOT NULL,
  target_discord_id VARCHAR(32) DEFAULT NULL,
  metadata_json JSON DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (log_id),
  KEY idx_audit_actor (actor_discord_id),
  KEY idx_audit_target (target_discord_id),
  KEY idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
