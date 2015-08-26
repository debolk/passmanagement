CREATE DATABASE `passmanagement` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `passmanagement`;

CREATE TABLE `attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `card_id` varchar(255) NOT NULL,
  `username` varchar(45) DEFAULT NULL,
  `access_granted` tinyint(1) NOT NULL,
  `reason` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
