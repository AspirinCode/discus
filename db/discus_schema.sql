CREATE TABLE `docking_project` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(200) COLLATE utf8_bin NOT NULL,
  `desc` text COLLATE utf8_bin NOT NULL,
  `db_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `docking_project_permitions` (
  `pid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  UNIQUE KEY `pid` (`pid`,`uid`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


CREATE TABLE `docking_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gid` int(11) NOT NULL,
  `login` varchar(50) COLLATE utf8_bin NOT NULL,
  `password` varchar(40) COLLATE utf8_bin NOT NULL,
  `salt` varchar(8) COLLATE utf8_bin NOT NULL,
  `fullname` varchar(250) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `docking_project_permitions`
  ADD CONSTRAINT `docking_project_permitions_ibfk_1` FOREIGN KEY (`pid`) REFERENCES `docking_project` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `docking_project_permitions_ibfk_2` FOREIGN KEY (`uid`) REFERENCES `docking_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
