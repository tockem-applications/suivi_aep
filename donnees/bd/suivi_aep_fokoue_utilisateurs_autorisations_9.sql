-- phpMyAdmin SQL Dump
-- version 3.3.9
-- http://www.phpmyadmin.net
--
-- Serveur: localhost
-- Généré le : Jeu 18 Décembre 2025 à 18:13
-- Version du serveur: 5.1.53
-- Version de PHP: 5.3.4

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Base de données: `suivi_aep_fokoue`
--

-- --------------------------------------------------------

--
-- Structure de la table `clefs`
--

CREATE TABLE IF NOT EXISTS `clefs` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `value` (`value`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=14 ;

--
-- Contenu de la table `clefs`
--

INSERT INTO `clefs` (`id`, `value`) VALUES
(1, 'Toto'),
(2, 'Bami'),
(3, 'Tami'),
(4, 'popo'),
(5, 'lewis1234'),
(6, 'blanche1234'),
(7, 'bertin1234'),
(8, 'ange1234'),
(9, 'achile1234'),
(10, 'testblanche'),
(11, 'martial1234'),
(12, 'eric1234'),
(13, 'lau');

-- --------------------------------------------------------

--
-- Structure de la table `logs`
--

CREATE TABLE IF NOT EXISTS `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `page_libelle` varchar(255) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `timestamp` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=45 ;

--
-- Contenu de la table `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `page_libelle`, `action`, `timestamp`) VALUES
(1, NULL, 'page de role', 'update_role', '0000-00-00 00:00:00'),
(2, NULL, 'role', 'add_role#4', '2025-04-22 18:54:20'),
(3, NULL, 'role', 'add_role#5', '2025-04-22 18:55:00'),
(4, NULL, 'role', 'add_role#6', '2025-04-22 18:56:03'),
(5, NULL, 'role', 'add_role#7', '2025-04-22 18:57:36'),
(6, NULL, 'role', 'delete_role', '0000-00-00 00:00:00'),
(7, NULL, 'role', 'delete_role', '0000-00-00 00:00:00'),
(8, NULL, 'role', 'delete_role', '0000-00-00 00:00:00'),
(9, NULL, 'role', 'delete_role', '0000-00-00 00:00:00'),
(10, NULL, 'role', 'delete_role#4', '0000-00-00 00:00:00'),
(11, NULL, 'page de role', 'update_role', '0000-00-00 00:00:00'),
(12, NULL, 'role_detail', 'remove_user_role#1', '2025-04-22 21:24:28'),
(13, NULL, 'role_detail', 'add_user_role#1', '2025-04-22 21:25:06'),
(14, NULL, 'role', 'delete_role#2', '0000-00-00 00:00:00'),
(15, NULL, 'role', 'add_role#8', '2025-04-23 13:59:18'),
(16, NULL, 'role', 'add_role#9', '2025-04-23 13:59:27'),
(17, NULL, 'role_detail', 'remove_page_access#1', '2025-04-27 11:29:38'),
(18, NULL, 'role_detail', 'remove_page_access#1', '2025-04-27 11:30:07'),
(19, NULL, 'role_detail', 'add_page_access#1#1', '2025-04-27 11:33:17'),
(20, NULL, 'role_detail', 'add_page_access#8#1', '2025-04-27 11:44:32'),
(21, NULL, 'role_detail', 'add_user_role#8', '2025-04-27 11:48:16'),
(22, NULL, 'role_detail', 'add_user_role#1', '2025-04-29 12:24:17'),
(23, NULL, 'role_detail', 'add_page_access#8#3', '2025-04-29 13:01:16'),
(24, NULL, 'role_detail', 'add_page_access#8#3', '2025-04-29 13:04:42'),
(25, NULL, 'role_detail', 'add_page_access#8#3', '2025-04-29 13:05:35'),
(26, NULL, 'role_detail', 'add_user_role#8', '2025-04-29 13:18:57'),
(27, NULL, 'role_detail', 'remove_user_role#8', '2025-04-29 14:06:40'),
(28, NULL, 'role_detail', 'remove_user_role#8', '2025-04-29 14:44:20'),
(29, NULL, 'role_detail', 'remove_user_role#8', '2025-04-29 14:44:35'),
(30, NULL, 'role_detail', 'remove_page_access#9', '2025-04-29 14:45:31'),
(31, NULL, 'role_detail', 'remove_page_access#9', '2025-04-29 14:45:44'),
(32, NULL, 'role_detail', 'remove_page_access#9', '2025-04-29 14:45:58'),
(33, NULL, 'role', 'add_role#10', '2025-04-29 14:46:57'),
(34, NULL, 'role', 'add_role#11', '2025-04-30 13:18:27'),
(35, NULL, 'role_detail', 'add_user_role#9', '2025-05-12 11:39:30'),
(36, NULL, 'role_detail', 'add_user_role#9', '2025-06-20 12:20:25'),
(37, NULL, 'role_detail', 'add_user_role#8', '2025-06-20 12:24:57'),
(38, NULL, 'role_detail', 'add_user_role#10', '2025-06-20 12:38:27'),
(39, NULL, 'role_detail', 'add_user_role#1', '2025-08-01 12:38:26'),
(40, NULL, 'role_detail', 'add_user_role#1', '2025-08-01 12:38:37'),
(41, NULL, 'role_detail', 'add_user_role#1', '2025-08-01 12:38:56'),
(42, NULL, 'role_detail', 'add_user_role#1', '2025-08-01 12:40:32'),
(43, NULL, 'role', 'add_role#12', '2025-08-01 12:42:57'),
(44, NULL, 'role_detail', 'add_user_role#12', '2025-08-01 12:43:07');

-- --------------------------------------------------------

--
-- Structure de la table `page_role_aep`
--

CREATE TABLE IF NOT EXISTS `page_role_aep` (
  `page_id` int(11) NOT NULL DEFAULT '0',
  `role_id` int(11) NOT NULL DEFAULT '0',
  `write_access` int(1) DEFAULT '0',
  PRIMARY KEY (`page_id`,`role_id`),
  KEY `role_id` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Contenu de la table `page_role_aep`
--

INSERT INTO `page_role_aep` (`page_id`, `role_id`, `write_access`) VALUES
(24, 1, 1),
(25, 1, 1),
(26, 1, 1),
(27, 1, 1),
(28, 1, 1),
(53, 1, 1),
(30, 1, 1),
(31, 1, 1),
(32, 1, 1),
(33, 1, 1),
(34, 1, 1),
(35, 1, 1),
(36, 1, 1),
(37, 1, 1),
(38, 1, 1),
(39, 1, 1),
(40, 1, 1),
(41, 1, 1),
(42, 1, 1),
(43, 1, 1),
(44, 1, 1),
(45, 1, 1),
(46, 1, 1),
(29, 1, 1),
(48, 1, 1),
(49, 1, 1),
(50, 1, 1),
(51, 1, 1),
(52, 1, 1),
(53, 8, 0),
(39, 8, 0),
(38, 8, 1),
(54, 1, 1),
(36, 10, 0),
(33, 10, 0),
(26, 10, 0),
(50, 9, 0),
(43, 9, 0),
(42, 9, 0),
(41, 9, 0),
(40, 9, 0),
(26, 9, 0),
(26, 8, 0),
(54, 10, 0),
(55, 1, 1),
(56, 1, 1),
(26, 11, 0),
(57, 1, 1),
(58, 1, 1),
(59, 1, 1),
(60, 1, 1),
(61, 1, 1),
(62, 1, 1),
(63, 1, 1),
(64, 1, 1),
(65, 1, 1),
(66, 1, 1),
(67, 1, 1),
(68, 1, 1),
(69, 1, 1),
(70, 1, 1),
(71, 1, 1),
(72, 1, 1),
(73, 1, 1),
(74, 1, 1),
(75, 1, 1),
(76, 1, 1),
(77, 1, 1),
(78, 1, 1),
(69, 9, 0),
(70, 9, 0),
(74, 8, 0),
(25, 12, 0),
(26, 12, 0),
(27, 12, 0),
(28, 12, 0),
(29, 12, 0),
(30, 12, 0),
(31, 12, 0),
(32, 12, 0),
(33, 12, 0),
(34, 12, 0),
(35, 12, 0),
(36, 12, 0),
(37, 12, 0),
(38, 12, 0),
(39, 12, 0),
(40, 12, 0),
(41, 12, 0),
(42, 12, 0),
(43, 12, 0),
(45, 12, 0),
(46, 12, 0),
(47, 12, 0),
(48, 12, 0),
(49, 12, 0),
(50, 12, 0),
(51, 12, 0),
(52, 12, 0),
(53, 12, 0),
(54, 12, 0),
(55, 12, 0),
(56, 12, 0),
(57, 12, 0),
(58, 12, 0),
(59, 12, 0),
(60, 12, 0),
(61, 12, 0),
(62, 12, 0),
(63, 12, 0),
(64, 12, 0),
(65, 12, 0),
(66, 12, 0),
(67, 12, 0),
(68, 12, 0),
(69, 12, 0),
(70, 12, 0),
(71, 12, 0),
(72, 12, 0),
(73, 12, 0),
(74, 12, 0),
(75, 12, 0),
(76, 12, 0),
(77, 12, 0),
(78, 12, 0),
(79, 1, 1),
(80, 1, 1),
(81, 1, 1),
(82, 1, 1),
(83, 1, 1),
(84, 1, 1),
(85, 1, 1),
(86, 1, 1),
(87, 1, 1),
(88, 1, 1),
(89, 1, 1),
(90, 1, 1),
(91, 1, 1),
(92, 1, 1),
(93, 1, 1),
(94, 1, 1),
(95, 1, 1),
(96, 1, 1),
(97, 1, 1),
(98, 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=13 ;

--
-- Contenu de la table `roles`
--

INSERT INTO `roles` (`id`, `nom`) VALUES
(1, 'Administrateur'),
(8, 'Releveur'),
(9, 'Comptable'),
(10, 'Recouvreur'),
(11, 'Visiteur'),
(12, 'all');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(32) NOT NULL,
  `nom` varchar(32) NOT NULL,
  `prenom` varchar(32) NOT NULL,
  `numero_telephone` varchar(16) NOT NULL,
  `password` varchar(64) DEFAULT NULL,
  `salt` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=17 ;

--
-- Contenu de la table `users`
--

INSERT INTO `users` (`id`, `email`, `nom`, `prenom`, `numero_telephone`, `password`, `salt`) VALUES
(1, 'sd@gmail.com', 'slks aslk', 'M', '654152545', 'ef797c8118f02dfb', ''),
(2, 'tockemapplications@gmail.com', 'Tsafack', 'Erick', '654190514', 'ef797c8118f02dfb', ''),
(3, 'tockemapplications2@gmail.com', 'sncdsl;n', 'sldnlds', '25412545', 'e150f4455ed28e8c', ''),
(4, 'tockemapplications3@gmail.com', 'sksk', 'sksk', '6565656565', 'aa005661a830c458', 'ae8604e73a48e7022190406c201ca358'),
(5, 'tockemapplications5@gmail.com', 'olol', 'lolo', '653636363', 'f916e27059dc0b41', '5bbb445a4f503f8234ecbe37903f0c24'),
(6, 'tockemapplications6@gmail.com', 'opopo', 'poioi', '658585959', '3270175410fe1cc8ee5633e04a14fcad56ee04bbaa5329604385bed55fe1098d', '15f7ce4388a49a9be16cf70015ec2f9e'),
(7, 'tockemapplications0@gmail.com', 'Takam Ulrich', 'Erick', '6565656565', '1f3b595625daad307953bb1153055ef9d28ffa2bbde1dc384c6d109fca6d552a', '7a85e01b353c290533d2deda2c84805a'),
(8, 'ericktsafack@gmail.com', 'Tsafack', 'Erick', '654190514', 'cd1a8acb20f03325aa0ba8b053af01d1c4eb34a592311b4041022000af3ee295', '8562ef2428bccd16369b80173adf2e65'),
(9, 'bonjour@gmail.com', 'Mezankeu', 'Darelle', '658595653', '66b69e627ba0867e07b680bdc8966ded1c0d97edc8e079cc3a1ca6936b4a7cee', '249324c1cb4a556af910a1d2ef1eb9e2'),
(10, 'tockemapplications10@gmail.com', 'platon', 'juju', '698977445', 'e7c90bb79468a7b6a01ce361628c0ceae4365071a7dace2808ef5aea8532c683', 'cdb3babd4215c8db8f9abd2b7c4e65fd'),
(11, 'testblanche@gmail.com', 'Test Momo', 'blanche', '677565453', '285af19d2e8f258a544d15781bb5312a3c336d5695533044c6178bd5a5f867a2', '1d395a8551f727d8d1c09cbf7a7c8349'),
(12, 'martialtsobeng@gmail.com', 'TSOBENG', 'Martial', '697621294', '789473a2831b79cdcc5450d2088b0092660cf3e116a1d2e46578bf842dbba545', '0e8d639eb5510f72802da07d09dec59c'),
(13, 'blanchemomo1991@gmail.com', 'MOMO TSOPFACK', 'Blanche', '698193280', 'e1302e2f72b5f2a2011f8604f32384dfd11616a54be08552d164abe3e4b666ce', '6ea512ef0586ad887caeca3174932e5f'),
(14, 'mcnyama@gmail.com', 'NDZOU', 'Lewis', '670029033', '1b114dbd823229a5bba420252277b8bf79dabd3187664d8c2d8a739395676851', '6726619066239e885f37eb173f8ad7fc'),
(15, 'tockemapplicationsp@gmail.com', 'toto', 'Eric', '654190514', '7f595eb4847906a7209df1803231cc70d8ed502492f99a8ae2603922c8cf89c4', '8040d34cb3038874663428a566ea383b'),
(16, 'mabamomoachille@gmail.com', 'maba momo', 'achille', '690409882', '8ea827a5102a45523620df3b018947fca58114de66cac6a05e4966f60dfe0fd1', '1c674f480c9cb77f239236dc7d1cca8e');

-- --------------------------------------------------------

--
-- Structure de la table `user_clefs`
--

CREATE TABLE IF NOT EXISTS `user_clefs` (
  `clef_id` int(3) unsigned NOT NULL,
  `user_id` int(3) unsigned NOT NULL,
  PRIMARY KEY (`clef_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Contenu de la table `user_clefs`
--

INSERT INTO `user_clefs` (`clef_id`, `user_id`) VALUES
(3, 8),
(2, 9),
(4, 10),
(10, 11),
(11, 12),
(6, 13),
(5, 14),
(12, 15),
(9, 16);

-- --------------------------------------------------------

--
-- Structure de la table `user_roles`
--

CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` int(11) NOT NULL DEFAULT '0',
  `role_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `role_id` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Contenu de la table `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1),
(7, 1),
(8, 8),
(9, 8),
(9, 10),
(10, 9),
(10, 11),
(11, 8),
(11, 9),
(11, 10),
(11, 11),
(12, 1),
(12, 8),
(12, 11),
(12, 12),
(13, 1),
(13, 11),
(14, 1),
(14, 11),
(15, 11),
(16, 1),
(16, 11);
