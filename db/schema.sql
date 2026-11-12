CREATE TABLE IF NOT EXISTS teams (
  id         CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name       VARCHAR(100) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(255) NOT NULL UNIQUE,
  sso_id        VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
  password_hash VARCHAR(255) NULL,
  is_admin      TINYINT(1) NOT NULL DEFAULT 0,
  team_id       CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_team FOREIGN KEY (team_id)
    REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vacations (
  id         CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id    CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  start_date DATE NOT NULL,
  end_date   DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_vac_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT ck_vac_range CHECK (end_date >= start_date),
  INDEX ix_vac_user (user_id),
  INDEX ix_vac_range (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(255) NOT NULL,
  ip           VARBINARY(16) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_attempt_email (email, attempted_at),
  INDEX ix_attempt_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
