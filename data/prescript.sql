-- this is an example pre-script file
LOCK TABLES `dbv_log` WRITE;
INSERT IGNORE INTO `dbv_log` (`datetime`, `version`, `name`, `status`, `message`, `execution_time`)
VALUES
	(NOW(), 0, '--START DEPLOYMENT--', 'OK', '', 0);
UNLOCK TABLES;