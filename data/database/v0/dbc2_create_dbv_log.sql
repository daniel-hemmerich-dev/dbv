DROP TABLE IF EXISTS `dbv_log`;
CREATE TABLE IF NOT EXISTS `dbv_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `datetime` datetime NOT NULL,
  `version` bigint(10) unsigned NOT NULL,
  `name` varchar(128) NOT NULL DEFAULT '',
  `status` varchar(16) NOT NULL DEFAULT '',
  `message` longtext NOT NULL,
  `execution_time` float unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

LOCK TABLES `dbv_log` WRITE;
INSERT IGNORE INTO `dbv_log` (`datetime`, `version`, `name`, `status`, `message`, `execution_time`)
VALUES
	(NOW(), 0, 'create_dbv_queries', 'OK', '', 0);
UNLOCK TABLES;