CREATE TABLE IF NOT EXISTS `dbv_queries` (
  `version` bigint(20) unsigned NOT NULL,
  `name` varchar(128) NOT NULL DEFAULT '',
  `datetime` datetime NOT NULL,
  `hash` varchar(32) DEFAULT NULL,
  `query` longtext NOT NULL,
  PRIMARY KEY (`version`,`name`,`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;