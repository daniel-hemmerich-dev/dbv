DROP TABLE IF EXISTS `dbv_queries`;
CREATE TABLE IF NOT EXISTS `dbv_queries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version` bigint(20) unsigned NOT NULL,
  `name` varchar(128) NOT NULL DEFAULT '',
  `datetime` datetime NOT NULL,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `query` longblob NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ALREADY_EXECUTED` (`version`,`name`,`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;