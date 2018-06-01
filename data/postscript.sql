-- this is an example post-script file
LOCK TABLES `dbv_log` WRITE;
INSERT IGNORE INTO `dbv_log` (`datetime`, `version`, `name`, `status`, `message`, `execution_time`)
VALUES
	(NOW(), 0, '--END DEPLOYMENT--', 'OK', '', 0);
UNLOCK TABLES;