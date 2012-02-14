-- phpMyAdmin SQL Dump
-- version 3.2.3
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Feb 09, 2012 at 11:41 PM
-- Server version: 5.1.40
-- PHP Version: 5.3.10

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `test`
--

-- --------------------------------------------------------

--
-- Table structure for table `Zodiak`
--

DROP TABLE IF EXISTS `Zodiak`;
CREATE TABLE `Zodiak` (
  `IdZodiak` int(11) NOT NULL AUTO_INCREMENT,
  `Zodiak` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`IdZodiak`)
) ENGINE=MyISAM  DEFAULT CHARSET=cp1251 AUTO_INCREMENT=13 ;

--
-- Dumping data for table `Zodiak`
--

INSERT INTO `Zodiak` (`IdZodiak`, `Zodiak`) VALUES
(1, 'Овен'),
(2, 'Телец'),
(3, 'Близнецы'),
(4, 'Рак'),
(5, 'Лев'),
(6, 'Дева'),
(7, 'Весы'),
(8, 'Скорпион'),
(9x, 'Стрелец'),
(10, 'Козерог'),
(11, 'Водолей'),
(12, 'Рыбы');
