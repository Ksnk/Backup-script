--
-- "test" database with +"Zodiak"-"" tables
--     Zodiak
-- backup created: 25 Sep 12 19:53:32
--

DROP TABLE IF EXISTS `Zodiak`;

CREATE TABLE `Zodiak` (
  `IdZodiak` int(11) NOT NULL AUTO_INCREMENT,
  `Zodiak` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`IdZodiak`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=cp1251;

/*!50111 ALTER table `Zodiak` DISABLE KEYS */;

INSERT INTO `Zodiak` VALUES
  (1, 'Овен'),
  (2, 'Телец'),
  (3, 'Близнецы'),
  (4, 'Рак'),
  (5, 'Лев'),
  (6, 'Дева'),
  (7, 'Весы'),
  (8, 'Скорпион'),
  (9, 'Стрелец'),
  (10, 'Козерог'),
  (11, 'Водолей'),
  (12, 'Рыбы');

/*!50111 ALTER table `Zodiak` ENABLE KEYS */;
