-- Favorites2 extension table
-- MySQL / MariaDB

CREATE TABLE IF NOT EXISTS /*_*/favorites (
  favorite_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  favorite_user_id   INT UNSIGNED NOT NULL,
  favorite_page_id   INT UNSIGNED NOT NULL,
  favorite_timestamp VARBINARY(14) NOT NULL DEFAULT '',
  PRIMARY KEY (favorite_id),
  UNIQUE KEY favorite_user_page (favorite_user_id, favorite_page_id),
  KEY favorite_user (favorite_user_id),
  KEY favorite_page (favorite_page_id)
) /*$wgDBTableOptions*/;