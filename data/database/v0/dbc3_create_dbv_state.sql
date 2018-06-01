CREATE TABLE IF NOT EXISTS `dbv_state` (
  `name` varchar(128) NOT NULL DEFAULT '',
  `value` varchar(128) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

LOCK TABLES `dbv_state` WRITE;
INSERT IGNORE INTO `dbv_state` (`name`, `value`)
VALUES
	('current_version', '0'),
	('highest_version', '1');
UNLOCK TABLES;