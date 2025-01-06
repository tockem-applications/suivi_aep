-- phpMyAdmin SQL Dump
-- version 3.3.9
-- http://www.phpmyadmin.net
--
-- Serveur: localhost
-- Généré le : Jeu 05 Décembre 2024 à 18:08
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
-- Structure de la table `abone`
--

CREATE TABLE IF NOT EXISTS `abone` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(128) NOT NULL,
  `numero_compteur` varchar(32) NOT NULL,
  `numero_telephone` varchar(16) NOT NULL,
  `numero_compte_anticipation` varchar(16) NOT NULL,
  `etat` varchar(10) NOT NULL,
  `rang` int(4) DEFAULT NULL,
  `id_reseau` int(3) unsigned NOT NULL,
  `derniers_index` decimal(7,2) unsigned DEFAULT '0.00',
  `type_compteur` varchar(16) DEFAULT 'distribution',
  PRIMARY KEY (`id`),
  KEY `id_reseau` (`id_reseau`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=200 ;

--
-- Contenu de la table `abone`
--

INSERT INTO `abone` VALUES(19, 'KUECHEU BONIFACE', '2012012', '677889955', '10', 'actif', 1, 1, '25.00', 'distribution');
INSERT INTO `abone` VALUES(20, 'FOGOH JOHN MUAFOR', '2012890', '654857412', '10', 'actif', 1, 1, '18.00', 'distribution');
INSERT INTO `abone` VALUES(21, 'erick Tsafack', '2012615', '654190514', '10', 'actif', 2, 2, '59.00', 'distribution');
INSERT INTO `abone` VALUES(22, 'FOMO FELIX', '78186232', '677889955', '10', 'actif', 2, 1, '54.00', 'distribution');
INSERT INTO `abone` VALUES(23, 'MAFODONG MARIE', '2012077', '677889955', '10', 'actif', 3, 1, '70.50', 'distribution');
INSERT INTO `abone` VALUES(24, 'NGUETIA  N. SAMUEL', '2012146', '677889955', '10', 'actif', 3, 1, '33.12', 'distribution');
INSERT INTO `abone` VALUES(25, 'MAKENGUIM M. JULIENNE', '2012540', '677889955', '10', 'actif', 4, 1, '19.60', 'distribution');
INSERT INTO `abone` VALUES(26, 'NDEMNOU ALPHONSE', '2012210', '677889955', '10', 'non actif', 4, 2, '4.00', 'distribution');
INSERT INTO `abone` VALUES(27, 'LONZI MICHAEL', '2012882', '677889955', '10', 'actif', 5, 1, '53.00', 'distribution');
INSERT INTO `abone` VALUES(28, 'FANZET', '2012322', '677889955', '10', 'actif', 5, 1, '21.00', 'distribution');
INSERT INTO `abone` VALUES(29, 'TESSA JEAN', '16051888', '677889955', '10', 'actif', 6, 1, '56.00', 'distribution');
INSERT INTO `abone` VALUES(30, 'NGANKENG JEAN', '16056859', '677889955', '10', 'actif', 6, 1, '8.20', 'distribution');
INSERT INTO `abone` VALUES(31, 'KAMKENG ALPHONSE', '16052767', '677889955', '10', 'actif', 7, 1, '69.90', 'distribution');
INSERT INTO `abone` VALUES(32, 'NGATCHOP TESSA', '17062000', '677889955', '10', 'actif', 7, 3, '102.30', 'distribution');
INSERT INTO `abone` VALUES(33, 'MEKONTCHOU ANTOINE', '2012866', '677889955', '10', 'actif', 8, 1, '48.40', 'distribution');
INSERT INTO `abone` VALUES(34, 'MEKONTCHOU ANTOINE', '2012167', '677889955', '10', 'actif', 8, 1, '9.50', 'distribution');
INSERT INTO `abone` VALUES(35, 'NGUEKOUO PATRICE', '2012567', '677889955', '10', 'actif', 9, 2, '14.60', 'distribution');
INSERT INTO `abone` VALUES(36, 'NGUIMGO PAULINE', '2012508', '677889955', '10', 'actif', 9, 3, '23.10', 'distribution');
INSERT INTO `abone` VALUES(37, 'DJOUMESSI MARCEL', '2012009', '677889955', '10', 'actif', 10, 3, '275.10', 'distribution');
INSERT INTO `abone` VALUES(38, 'TOUKAM LEKANE MARIE', '2012997', '677889955', '10', 'actif', 10, 2, '8.60', 'distribution');
INSERT INTO `abone` VALUES(39, 'MAWAMBA PAULINE', '2032422', '677889955', '10', 'actif', 11, 3, '7.30', 'distribution');
INSERT INTO `abone` VALUES(40, 'TCHUEKAM HERVE', '2012202', '677889955', '10', 'actif', 11, 2, '23.90', 'distribution');
INSERT INTO `abone` VALUES(41, 'TEDJOU JEAN CLAUDE', '2012058', '677889955', '10', 'actif', 12, 1, '36.80', 'distribution');
INSERT INTO `abone` VALUES(42, 'KOUMO ANDRE', '2012052', '677889955', '10', 'actif', 12, 1, '48.10', 'distribution');
INSERT INTO `abone` VALUES(43, 'NENKOUNKANG PIERRE', '', '677889955', '10', 'actif', 13, 2, '85.12', 'distribution');
INSERT INTO `abone` VALUES(44, 'ASSOBKENG JOSEPH', '2012083', '677889955', '10', 'actif', 13, 1, '43.60', 'distribution');
INSERT INTO `abone` VALUES(45, 'TEDJOU FULBERT', '2012215', '677889955', '10', 'actif', 14, 2, '7.50', 'distribution');
INSERT INTO `abone` VALUES(46, 'TETSOPGANG SAMUEL', '2012502', '677889955', '10', 'non actif', 14, 3, '27.00', 'distribution');
INSERT INTO `abone` VALUES(47, 'NTOPA BERNARD', '2012595', '677889955', '10', 'actif', 15, 2, '335.40', 'distribution');
INSERT INTO `abone` VALUES(48, 'NUPIOLEH BARTHELEMY', '2012319', '677889955', '10', 'actif', 15, 3, '26.20', 'distribution');
INSERT INTO `abone` VALUES(49, 'TATOU CAMI', '2012323', '677889955', '10', 'actif', 16, 2, '31.20', 'distribution');
INSERT INTO `abone` VALUES(50, 'MUFID FOKOUE', '2012992', '677889955', '10', 'actif', 16, 3, '23.90', 'distribution');
INSERT INTO `abone` VALUES(51, 'TAPIOLEU LEOPOLD', '2012054', '677889955', '10', 'actif', 17, 1, '24.90', 'distribution');
INSERT INTO `abone` VALUES(52, 'DJOUKENG AXEL', '2012717', '677889955', '10', 'actif', 17, 3, '236.10', 'distribution');
INSERT INTO `abone` VALUES(53, 'ASSONFACK T. ANTOINE', '2012012', '677889955', '10', 'actif', 18, 1, '18.40', 'distribution');
INSERT INTO `abone` VALUES(54, 'AWOUNANG', '2012916', '677889955', '10', 'actif', 18, 1, '23.50', 'distribution');
INSERT INTO `abone` VALUES(55, 'ABOUDEM PAUL', '2012602', '677889955', '10', 'actif', 19, 1, '7.20', 'distribution');
INSERT INTO `abone` VALUES(56, 'TIKOUOKA JEAN ROBERT', '2012062', '677889955', '10', 'actif', 19, 1, '53.20', 'distribution');
INSERT INTO `abone` VALUES(57, 'LADJOU JULIENNE', '2012051', '677889955', '10', 'actif', 20, 1, '13.40', 'distribution');
INSERT INTO `abone` VALUES(58, 'NINTEDEM MARIE', '2012072', '677889955', '10', 'actif', 20, 2, '21.60', 'distribution');
INSERT INTO `abone` VALUES(59, 'TEKOUNGANG ETIENNE', '2012152', '677889955', '10', 'actif', 21, 2, '33.20', 'distribution');
INSERT INTO `abone` VALUES(60, 'KODJEU ETIENNE', '2012794', '677889955', '10', 'actif', 0, 1, '23.20', 'distribution');
INSERT INTO `abone` VALUES(61, 'TEIKEU DIEUDONNE', '2012986', '677889955', '10', 'actif', 22, 1, '19.90', 'distribution');
INSERT INTO `abone` VALUES(62, 'KAMGANG GHISLAIN', '2012347', '677889955', '10', 'actif', 22, 1, '15.90', 'distribution');
INSERT INTO `abone` VALUES(63, 'TESSA NGUEGANG JUNIOR', '2012326', '677889955', '10', 'actif', 23, 1, '16.20', 'distribution');
INSERT INTO `abone` VALUES(64, 'TCHAPKENG REGINE', '2012385', '677889955', '10', 'actif', 23, 1, '6.30', 'distribution');
INSERT INTO `abone` VALUES(65, 'TENANGUE RENE', '2012950', '677889955', '10', 'actif', 24, 1, '9.90', 'distribution');
INSERT INTO `abone` VALUES(66, 'CHOUYEMGOU K. FRANCOIS', '2012683', '677889955', '10', 'actif', 24, 1, '49.10', 'distribution');
INSERT INTO `abone` VALUES(67, 'MEKONTCHOU ROBERT', '2012729', '677889955', '10', 'non actif', 25, 1, '2.10', 'distribution');
INSERT INTO `abone` VALUES(68, 'DONGMO HELLA HERVE', '2012026', '677889955', '10', 'actif', 26, 3, '22.40', 'distribution');
INSERT INTO `abone` VALUES(69, 'POKAM HELENE', '2012863', '677889955', '10', 'actif', 26, 1, '13.20', 'distribution');
INSERT INTO `abone` VALUES(70, 'TAMBAT FELIX', '2012702', '677889955', '10', 'actif', 27, 3, '115.40', 'distribution');
INSERT INTO `abone` VALUES(71, 'KOUEMO ANNIE EVELYNE', '2020813', '677889955', '10', 'actif', 27, 3, '41.60', 'distribution');
INSERT INTO `abone` VALUES(72, 'TCHINDA ZACEL BONSTON', '2020041', '677889955', '10', 'actif', 28, 1, '15.30', 'distribution');
INSERT INTO `abone` VALUES(73, 'MAKAMTE CECILE', '2012021', '677889955', '10', 'actif', 28, 2, '18.60', 'distribution');
INSERT INTO `abone` VALUES(74, 'KIGNI JOSEPH', '2020335', '677889955', '10', 'actif', 29, 3, '21.60', 'distribution');
INSERT INTO `abone` VALUES(75, 'DJIKENG VICTOR', '2020087', '677889955', '10', 'actif', 29, 1, '27.10', 'distribution');
INSERT INTO `abone` VALUES(76, 'NKENGNI ROBERT', '2020319', '677889955', '10', 'actif', 30, 2, '43.60', 'distribution');
INSERT INTO `abone` VALUES(77, 'APOUDAM NÃƒÂ©e DJUIKO', '2020025', '677889955', '10', 'actif', 30, 3, '16.40', 'distribution');
INSERT INTO `abone` VALUES(78, 'TEUZEUMG NOEL', '', '677889955', '10', 'actif', 0, 2, '501.30', 'distribution');
INSERT INTO `abone` VALUES(79, 'TEIDA CHANCELINE', '2020963', '677889955', '10', 'actif', 31, 2, '28.60', 'distribution');
INSERT INTO `abone` VALUES(80, 'BELGA MAGARET Epse', '2020519', '677889955', '10', 'actif', 32, 3, '50.90', 'distribution');
INSERT INTO `abone` VALUES(81, 'NGAKOU PIERRE', '2020255', '677889955', '10', 'non actif', 32, 1, '2.90', 'distribution');
INSERT INTO `abone` VALUES(82, 'METSANOU JEAN CLAUDE', '2020595', '677889955', '10', 'actif', 33, 3, '537.20', 'distribution');
INSERT INTO `abone` VALUES(83, 'SOBZE ETIENNE', '2020540', '677889955', '10', 'actif', 34, 2, '45.50', 'distribution');
INSERT INTO `abone` VALUES(84, 'TEKAM JOSEPH', '2020442', '677889955', '10', 'actif', 34, 2, '35.70', 'distribution');
INSERT INTO `abone` VALUES(85, 'TESSA DANIEL', '2020452', '677889955', '10', 'actif', 35, 2, '1.50', 'distribution');
INSERT INTO `abone` VALUES(86, 'NOUMSI OUAFO FELICITE', '2020482', '677889955', '10', 'actif', 35, 3, '48.30', 'distribution');
INSERT INTO `abone` VALUES(87, 'TSAFACK PAUL', '2020267', '677889955', '10', 'actif', 43, 1, '10.00', 'distribution');
INSERT INTO `abone` VALUES(88, 'KEUMEDJEU JOSEPH', '2012340', '677889955', '10', 'actif', 52, 2, '0.20', 'distribution');
INSERT INTO `abone` VALUES(89, 'FOKOUE LAZARE', '2020359', '677889955', '10', 'actif', 36, 2, '4.00', 'distribution');
INSERT INTO `abone` VALUES(90, 'TCHOMBA FIDELE', '2020667', '677889955', '10', 'actif', 36, 1, '3.90', 'distribution');
INSERT INTO `abone` VALUES(91, 'NGUEMFO JEANNINE', '2020452', '677889955', '10', 'actif', 37, 3, '15.50', 'distribution');
INSERT INTO `abone` VALUES(92, 'NGEUVEU JEROME', '2020825', '677889955', '10', 'actif', 37, 3, '0.20', 'distribution');
INSERT INTO `abone` VALUES(93, 'KIMO ANDRE', '2020302', '677889955', '10', 'actif', 46, 1, '13.10', 'distribution');
INSERT INTO `abone` VALUES(94, 'ZENANG ALAIN THOMAS', '2020206', '677889955', '10', 'actif', 46, 3, '24.90', 'distribution');
INSERT INTO `abone` VALUES(95, 'TCHOUMBA  ANDRE', '2020099', '677889955', '10', 'actif', 38, 2, '0.10', 'distribution');
INSERT INTO `abone` VALUES(96, 'NGUEDIA ODETTE', '2020310', '677889955', '10', 'actif', 38, 1, '4.10', 'distribution');
INSERT INTO `abone` VALUES(97, 'MAGEMETE TABANSO', '2020400', '677889955', '10', 'actif', 39, 3, '0.20', 'distribution');
INSERT INTO `abone` VALUES(98, 'TCHAMBA NGANKAM', '2020037', '677889955', '10', 'actif', 39, 3, '16.60', 'distribution');
INSERT INTO `abone` VALUES(99, 'DONFOUE NGANGUE', '2020445', '677889955', '10', 'actif', 40, 2, '1.10', 'distribution');
INSERT INTO `abone` VALUES(100, 'TABAKAM JEAN CLAUDE', '2020919', '677889955', '10', 'actif', 40, 2, '8.90', 'distribution');
INSERT INTO `abone` VALUES(101, 'NOUTSA HENRI', '2020934', '677889955', '10', 'actif', 41, 3, '28.20', 'distribution');
INSERT INTO `abone` VALUES(102, 'KENFACK PAULINE', '2020904', '677889955', '10', 'actif', 41, 2, '2.10', 'distribution');
INSERT INTO `abone` VALUES(103, 'TEUFACK JACQUELINE', '2020408', '677889955', '10', 'actif', 42, 3, '0.60', 'distribution');
INSERT INTO `abone` VALUES(104, 'KOUMETIO HELENE', '2020939', '677889955', '10', 'actif', 42, 2, '48.30', 'distribution');
INSERT INTO `abone` VALUES(105, 'FONDJIO MAURICE', '2020030', '677889955', '10', 'actif', 44, 3, '15.40', 'distribution');
INSERT INTO `abone` VALUES(106, 'TEUOUSSI JEAN', '2020554', '677889955', '10', 'actif', 44, 2, '5.40', 'distribution');
INSERT INTO `abone` VALUES(107, 'NGUKO MARIE LOUISE', '2020589', '677889955', '10', 'actif', 45, 3, '2.00', 'distribution');
INSERT INTO `abone` VALUES(108, 'NAFACK OUAMBA WILLIAM', '2020313', '677889955', '10', 'actif', 45, 3, '2.60', 'distribution');
INSERT INTO `abone` VALUES(109, 'MATSAFACK JOSEPHINE', '2020953', '677889955', '10', 'actif', 47, 2, '0.20', 'distribution');
INSERT INTO `abone` VALUES(110, 'TIBONCHOUO BENOIT', '2020464', '677889955', '10', 'actif', 47, 3, '19.30', 'distribution');
INSERT INTO `abone` VALUES(111, 'MEKOUEDJEU SOPHINE', '2017073722', '677889955', '10', 'actif', 48, 1, '0.60', 'distribution');
INSERT INTO `abone` VALUES(112, 'NGANKEM JEAN PAUL', '2020582', '677889955', '10', 'actif', 48, 2, '0.70', 'distribution');
INSERT INTO `abone` VALUES(113, 'AWOMO RAPHAEL', '201707483', '677889955', '10', 'actif', 49, 3, '0.30', 'distribution');
INSERT INTO `abone` VALUES(114, 'TEDONKEU MARTIN LEDOUX', '', '677889955', '10', 'actif', 49, 2, '0.20', 'distribution');
INSERT INTO `abone` VALUES(115, 'TCHOMBA HERVE', '20170717605', '677889955', '10', 'actif', 50, 2, '0.20', 'distribution');
INSERT INTO `abone` VALUES(116, 'TETCHUE NIATIZE', '201707503', '677889955', '10', 'actif', 50, 1, '0.10', 'distribution');
INSERT INTO `abone` VALUES(117, 'TCHIOSSI LEOPOLD', '2017076087', '677889955', '10', 'actif', 51, 2, '13.10', 'distribution');
INSERT INTO `abone` VALUES(118, 'TANKA TCHOUMBA', '2020615', '677889955', '10', 'actif', 51, 2, '0.10', 'distribution');
INSERT INTO `abone` VALUES(119, 'NKENGNI ROBERT 2', '2017076087', '677889955', '10', 'actif', 55, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(120, 'KODJEU ANTOINE', '2012794', '677889955', '10', 'actif', 21, 1, '0.00', 'distribution');
INSERT INTO `abone` VALUES(121, 'NDONTSOP JEAN', '2012210', '677889955', '10', 'actif', 52, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(122, 'KEMGANG HENRI', '183265', '677889955', '10', 'actif', 53, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(123, 'TESSA BONIFACE', '2012665', '677889955', '10', 'actif', 53, 1, '0.00', 'distribution');
INSERT INTO `abone` VALUES(124, 'DIFFO TIHEWESSI', '2001133', '677889955', '10', 'actif', 54, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(125, 'MBOMEDA JEAN', '2012349', '677889955', '10', 'actif', 54, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(126, 'DIFFO TIHEWESSI 2', '201707914', '677889955', '10', 'actif', 55, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(127, 'MAKEUGUIM MARIE', '2012510', '677889955', '10', 'actif', 56, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(128, 'KENFACK BERNARD', '183597', '677889955', '10', 'actif', 56, 1, '0.00', 'distribution');
INSERT INTO `abone` VALUES(129, 'MEKENKEN JEAN', '2017075606', '677889955', '10', 'actif', 57, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(130, 'NGOUOZOCK ALAIN', '2100027', '677889955', '10', 'actif', 57, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(131, 'FOLEFACK JACQUELINE', '2012813', '677889955', '10', 'actif', 58, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(132, 'METSACHINT FOKOUA', '', '677889955', '10', 'actif', 58, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(133, 'TEMZEUMG KENTSA', '', '677889955', '10', 'actif', 0, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(134, 'PEWOUE A. REGINE', '2012878', '677889955', '10', 'actif', 59, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(135, 'MEKONCHU ARMAND', '2101467', '677889955', '10', 'actif', 59, 1, '0.00', 'distribution');
INSERT INTO `abone` VALUES(136, 'MEIKENG Epse AWOUNANG', '2012141', '677889955', '10', 'actif', 60, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(137, 'MEFOET NGUEVO', '2020897', '677889955', '10', 'actif', 61, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(138, 'KUECHEU BONIFACE 2', '183797', '677889955', '10', 'actif', 61, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(139, 'TEDJOU JEAN CLAUDE 2', '', '677889955', '10', 'actif', 0, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(140, 'JAZE TEKAM', '9065883', '677889955', '10', 'actif', 62, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(141, 'TCHOULA', '2177826', '677889955', '10', 'actif', 62, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(142, 'KENFACK REGINE', '9068077', '677889955', '10', 'non actif', 63, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(143, 'MBEUGMO BASILE', '2017074871', '677889955', '10', 'actif', 63, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(144, 'TCHOUGOUANG', '201707057', '677889955', '10', 'actif', 64, 1, '0.00', 'distribution');
INSERT INTO `abone` VALUES(145, 'KENFACK MICHELINE', '183797', '677889955', '10', 'actif', 64, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(146, 'MBOGNI SEBASTIEN', '2020700', '677889955', '10', 'actif', 65, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(147, 'MAZEDJOU ANDRE', '183110', '677889955', '10', 'actif', 65, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(148, 'TSAGNA FIDELE', '182710', '677889955', '10', 'actif', 66, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(149, 'FOMO FEUNANG CREPIN P.', '2110194', '677889955', '10', 'actif', 66, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(150, 'NINDJEU ROMARIC', '2110834', '677889955', '10', 'actif', 67, 1, '0.00', 'distribution');
INSERT INTO `abone` VALUES(151, 'TEKOUNGANG II', '2110293', '677889955', '10', 'actif', 67, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(152, 'TSAGNA RIGOBERT', '2110739', '677889955', '10', 'actif', 68, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(153, 'TCHEGUENA KEUTSA', '9065843', '677889955', '10', 'actif', 68, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(154, 'TENZEUMG KENTSA', '', '677889955', '10', 'actif', 31, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(155, 'MEIKAM LUCIENNE', '9064591', '677889955', '10', 'actif', 69, 1, '0.00', 'distribution');
INSERT INTO `abone` VALUES(156, 'KUEFOUET PROSPER', '9064590', '677889955', '10', 'actif', 69, 1, '0.00', 'distribution');
INSERT INTO `abone` VALUES(157, 'KITIO NGUEGANG Epse', '2010401', '677889955', '10', 'actif', 70, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(158, 'KWESSO GAETAN', '2010134', '677889955', '10', 'actif', 70, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(159, 'NOUKOULEPECK', '9064763', '677889955', '10', 'actif', 71, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(160, 'KENFACK KENBENG', '9067762', '677889955', '10', 'actif', 71, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(161, 'TESSA TAKOUGANG', '12014639', '677889955', '10', 'actif', 72, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(162, 'TEFOIT TCHOUANGA', '2010725', '677889955', '10', 'actif', 72, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(163, 'NGUEKOUO PATRICE 2', 'W12011623', '677889955', '10', 'actif', 73, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(164, 'TESSA MENOUGONG', '9061278', '677889955', '10', 'actif', 73, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(165, 'MAGNI DJEUGUE', 'W12016500', '677889955', '10', 'actif', 74, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(166, 'KOSSOP DESIRE FABRICE', '182271', '677889955', '10', 'actif', 60, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(167, 'TENANFOUET EMMANUEL', 'W12812396', '677889955', '10', 'actif', 74, 1, '0.00', 'distribution');
INSERT INTO `abone` VALUES(168, 'MBONENG NORBERT', 'W12106673', '677889955', '10', 'actif', 75, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(169, 'JAMBONG ANTOINE', '9064418', '677889955', '10', 'actif', 75, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(170, 'TSAFACK HENRI', '9064541', '677889955', '10', 'actif', 76, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(171, 'NKENGNI ROBERT I', '', '677889955', '10', 'actif', 0, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(172, 'TCHIGUI ETIENNE', '2304104', '677889955', '10', 'actif', 76, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(173, 'NGOUNI SIMPLICE', '2304406', '677889955', '10', 'actif', 77, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(174, 'NOUTITOUT FranÃƒÂ§ois', '23', '677889955', '10', 'actif', 77, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(175, 'SANFO DOGHA RIGOBERT', '2304170', '677889955', '10', 'actif', 78, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(176, 'AWOUNANGFOKOU ROGER', '2304077', '677889955', '10', 'non actif', 78, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(177, 'SOMBAKAM  AZIS B', '2304204', '677889955', '10', 'actif', 79, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(178, 'HIKOUATCHIA GAEL', '2304752', '677889955', '10', 'actif', 79, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(179, 'DJEUKOUO  BERTRAND', '2304681', '677889955', '10', 'actif', 80, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(180, 'JAMBONG ANTOINE II', '', '677889955', '10', 'actif', 0, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(181, 'TEKEM PAUL ROGER', '2304923', '677889955', '10', 'actif', 80, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(182, 'DJAMBONG ANTOINE II', '2304565', '677889955', '10', 'actif', 81, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(183, 'KOZANG JEAN PAUL', '2304621', '677889955', '10', 'actif', 81, 1, '0.00', 'distribution');
INSERT INTO `abone` VALUES(184, 'CHEUDJIEU ALAIN ANDRE', '2304108', '677889955', '10', 'actif', 82, 1, '0.00', 'distribution');
INSERT INTO `abone` VALUES(185, 'NGUETIA CLAUDE URBAIN', '2304108', '677889955', '10', 'actif', 82, 1, '0.00', 'distribution');
INSERT INTO `abone` VALUES(186, 'CHEDO GASTON', '2304898', '677889955', '10', 'actif', 83, 1, '0.00', 'distribution');
INSERT INTO `abone` VALUES(188, 'FONDJO LUNO CELESTIN', '2304801', '677889955', '10', 'actif', 84, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(189, 'MBEUMO JAZE BRICE', '2304890', '677889955', '10', 'actif', 84, 3, '0.00', 'distribution');
INSERT INTO `abone` VALUES(190, 'MBOUZEUKEU  JEROME', '2304023', '677889955', '10', 'non actif', 85, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(191, 'FOKOUE JEAN PAUL', '2304724', '677889955', '10', 'actif', 85, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(192, 'TSAGUE MARIE', '2304104', '677889955', '10', 'actif', 86, 1, '0.00', 'distribution');
INSERT INTO `abone` VALUES(193, 'NGOUNGOURO Epse', '9064591', '677889955', '10', 'actif', 86, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(194, 'TENWOU', '2304093', '677889955', '10', 'actif', 87, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(195, 'TSAFACK ARNAUD', '2304778', '677889955', '10', 'actif', 87, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(196, 'FODA CHARLES', '3204180', '677889955', '10', 'actif', 88, 1, '0.00', 'distribution');
INSERT INTO `abone` VALUES(197, 'KAMKENG JEAN', '2304132', '677889955', '10', 'actif', 88, 2, '0.00', 'distribution');
INSERT INTO `abone` VALUES(198, 'compteur Nzah', '12014658', '654745685', '11', 'actif', 0, 1, '5.00', 'production');
INSERT INTO `abone` VALUES(199, 'compteur Mbouh', '12014755', '654745688', '11', 'actif', 0, 2, '11.00', 'production');

-- --------------------------------------------------------

--
-- Structure de la table `aep`
--

CREATE TABLE IF NOT EXISTS `aep` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `libele` varchar(64) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;

alter table aep
    add column fichier_facture varchar(64) not null;
alter table aep
    add column date date not null;

--
-- Contenu de la table `aep`
--

INSERT INTO `aep` VALUES(1, 'Fokoue', 'Il s''agit de l''aep de la commune de fokoue');

-- --------------------------------------------------------

--
-- Structure de la table `avoir_role`
--

CREATE TABLE IF NOT EXISTS `avoir_role` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `id_role` int(4) unsigned NOT NULL,
  `id_utilisateur` int(4) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_role_avoir_role` (`id_role`),
  KEY `fk_utilisateur_avoir_role` (`id_utilisateur`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Contenu de la table `avoir_role`
--


-- --------------------------------------------------------

--
-- Structure de la table `constante_reseau`
--

CREATE TABLE IF NOT EXISTS `constante_reseau` (
  `id` int(2) unsigned NOT NULL AUTO_INCREMENT,
  `prix_metre_cube_eau` int(5) unsigned NOT NULL,
  `prix_entretient_compteur` int(5) unsigned NOT NULL,
  `prix_tva` decimal(7,2) unsigned NOT NULL,
  `date_creation` date NOT NULL,
  `est_actif` tinyint(1) NOT NULL,
  `description` text,
  `id_aep` int(4) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `fk_aep_constante` (`id_aep`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=12 ;

--
-- Contenu de la table `constante_reseau`
--

INSERT INTO `constante_reseau` VALUES(11, 300, 0, '0.00', '0000-00-00', 1, 'Tarif de l''aep de fokoue', 1);

-- --------------------------------------------------------

--
-- Structure de la table `facture`
--

CREATE TABLE IF NOT EXISTS `facture` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `ancien_index` decimal(7,2) unsigned NOT NULL,
  `nouvel_index` decimal(7,2) unsigned NOT NULL,
  `montant_verse` decimal(8,2) DEFAULT '0.00',
  `date_paiement` date DEFAULT NULL,
  `penalite` decimal(8,2) DEFAULT '0.00',
  `id_mois_facturation` int(3) unsigned NOT NULL,
  `id_abone` int(4) unsigned NOT NULL,
  `message` text,
  PRIMARY KEY (`id`),
  KEY `id_mois_facturation` (`id_mois_facturation`),
  KEY `id_abone` (`id_abone`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2182 ;

--
-- Contenu de la table `facture`
--

INSERT INTO `facture` VALUES(2011, '100.00', '102.30', '0.00', '0000-00-00', '0.00', 130, 32, '');
INSERT INTO `facture` VALUES(2012, '18.00', '23.10', '0.00', '0000-00-00', '0.00', 130, 36, '');
INSERT INTO `facture` VALUES(2013, '270.00', '275.10', '0.00', '0000-00-00', '0.00', 130, 37, '');
INSERT INTO `facture` VALUES(2014, '4.20', '7.30', '0.00', '0000-00-00', '0.00', 130, 39, '');
INSERT INTO `facture` VALUES(2015, '23.00', '26.20', '0.00', '0000-00-00', '0.00', 130, 48, '');
INSERT INTO `facture` VALUES(2016, '23.00', '23.90', '0.00', '0000-00-00', '0.00', 130, 50, '');
INSERT INTO `facture` VALUES(2017, '230.00', '236.10', '0.00', '0000-00-00', '0.00', 130, 52, '');
INSERT INTO `facture` VALUES(2018, '19.00', '22.40', '0.00', '0000-00-00', '0.00', 130, 68, '');
INSERT INTO `facture` VALUES(2019, '112.90', '115.40', '0.00', '0000-00-00', '0.00', 130, 70, '');
INSERT INTO `facture` VALUES(2020, '38.70', '41.60', '0.00', '0000-00-00', '0.00', 130, 71, '');
INSERT INTO `facture` VALUES(2021, '15.80', '21.60', '0.00', '0000-00-00', '0.00', 130, 74, '');
INSERT INTO `facture` VALUES(2022, '12.80', '16.40', '0.00', '0000-00-00', '0.00', 130, 77, '');
INSERT INTO `facture` VALUES(2023, '49.40', '50.90', '0.00', '0000-00-00', '0.00', 130, 80, '');
INSERT INTO `facture` VALUES(2024, '530.20', '537.20', '0.00', '0000-00-00', '0.00', 130, 82, '');
INSERT INTO `facture` VALUES(2025, '48.30', '48.30', '0.00', '0000-00-00', '0.00', 130, 86, '');
INSERT INTO `facture` VALUES(2026, '15.50', '15.50', '0.00', '0000-00-00', '0.00', 130, 91, '');
INSERT INTO `facture` VALUES(2027, '0.20', '0.20', '0.00', '0000-00-00', '0.00', 130, 92, '');
INSERT INTO `facture` VALUES(2028, '24.90', '24.90', '0.00', '0000-00-00', '0.00', 130, 94, '');
INSERT INTO `facture` VALUES(2029, '0.20', '0.20', '0.00', '0000-00-00', '0.00', 130, 97, '');
INSERT INTO `facture` VALUES(2030, '16.60', '16.60', '0.00', '0000-00-00', '0.00', 130, 98, '');
INSERT INTO `facture` VALUES(2031, '28.20', '28.20', '0.00', '0000-00-00', '0.00', 130, 101, '');
INSERT INTO `facture` VALUES(2032, '0.60', '0.60', '0.00', '0000-00-00', '0.00', 130, 103, '');
INSERT INTO `facture` VALUES(2033, '15.40', '15.40', '0.00', '0000-00-00', '0.00', 130, 105, '');
INSERT INTO `facture` VALUES(2034, '2.00', '2.00', '0.00', '0000-00-00', '0.00', 130, 107, '');
INSERT INTO `facture` VALUES(2035, '2.60', '2.60', '0.00', '0000-00-00', '0.00', 130, 108, '');
INSERT INTO `facture` VALUES(2036, '19.30', '19.30', '0.00', '0000-00-00', '0.00', 130, 110, '');
INSERT INTO `facture` VALUES(2037, '0.30', '0.30', '0.00', '0000-00-00', '0.00', 130, 113, '');
INSERT INTO `facture` VALUES(2038, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 121, '');
INSERT INTO `facture` VALUES(2039, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 124, '');
INSERT INTO `facture` VALUES(2040, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 126, '');
INSERT INTO `facture` VALUES(2041, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 129, '');
INSERT INTO `facture` VALUES(2042, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 131, '');
INSERT INTO `facture` VALUES(2043, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 132, '');
INSERT INTO `facture` VALUES(2044, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 134, '');
INSERT INTO `facture` VALUES(2045, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 136, '');
INSERT INTO `facture` VALUES(2046, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 141, '');
INSERT INTO `facture` VALUES(2047, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 145, '');
INSERT INTO `facture` VALUES(2048, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 146, '');
INSERT INTO `facture` VALUES(2049, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 147, '');
INSERT INTO `facture` VALUES(2050, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 149, '');
INSERT INTO `facture` VALUES(2051, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 151, '');
INSERT INTO `facture` VALUES(2052, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 153, '');
INSERT INTO `facture` VALUES(2053, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 157, '');
INSERT INTO `facture` VALUES(2054, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 158, '');
INSERT INTO `facture` VALUES(2055, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 160, '');
INSERT INTO `facture` VALUES(2056, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 161, '');
INSERT INTO `facture` VALUES(2057, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 162, '');
INSERT INTO `facture` VALUES(2058, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 164, '');
INSERT INTO `facture` VALUES(2059, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 174, '');
INSERT INTO `facture` VALUES(2060, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 175, '');
INSERT INTO `facture` VALUES(2061, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 188, '');
INSERT INTO `facture` VALUES(2062, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 189, '');
INSERT INTO `facture` VALUES(2063, '55.00', '59.00', '0.00', '0000-00-00', '0.00', 130, 21, '');
INSERT INTO `facture` VALUES(2064, '11.00', '14.60', '0.00', '0000-00-00', '0.00', 130, 35, '');
INSERT INTO `facture` VALUES(2065, '6.00', '8.60', '0.00', '0000-00-00', '0.00', 130, 38, '');
INSERT INTO `facture` VALUES(2066, '21.00', '23.90', '0.00', '0000-00-00', '0.00', 130, 40, '');
INSERT INTO `facture` VALUES(2067, '83.00', '85.12', '0.00', '0000-00-00', '0.00', 130, 43, '');
INSERT INTO `facture` VALUES(2068, '4.10', '7.50', '0.00', '0000-00-00', '0.00', 130, 45, '');
INSERT INTO `facture` VALUES(2069, '329.00', '335.40', '0.00', '0000-00-00', '0.00', 130, 47, '');
INSERT INTO `facture` VALUES(2070, '25.00', '31.20', '0.00', '0000-00-00', '0.00', 130, 49, '');
INSERT INTO `facture` VALUES(2071, '20.00', '21.60', '0.00', '0000-00-00', '0.00', 130, 58, '');
INSERT INTO `facture` VALUES(2072, '30.00', '33.20', '0.00', '0000-00-00', '0.00', 130, 59, '');
INSERT INTO `facture` VALUES(2073, '16.60', '18.60', '0.00', '0000-00-00', '0.00', 130, 73, '');
INSERT INTO `facture` VALUES(2074, '39.20', '43.60', '0.00', '0000-00-00', '0.00', 130, 76, '');
INSERT INTO `facture` VALUES(2075, '495.90', '501.30', '0.00', '0000-00-00', '0.00', 130, 78, '');
INSERT INTO `facture` VALUES(2076, '25.60', '28.60', '0.00', '0000-00-00', '0.00', 130, 79, '');
INSERT INTO `facture` VALUES(2077, '45.50', '45.50', '0.00', '0000-00-00', '0.00', 130, 83, '');
INSERT INTO `facture` VALUES(2078, '35.70', '35.70', '0.00', '0000-00-00', '0.00', 130, 84, '');
INSERT INTO `facture` VALUES(2079, '1.50', '1.50', '0.00', '0000-00-00', '0.00', 130, 85, '');
INSERT INTO `facture` VALUES(2080, '0.20', '0.20', '0.00', '0000-00-00', '0.00', 130, 88, '');
INSERT INTO `facture` VALUES(2081, '4.00', '4.00', '0.00', '0000-00-00', '0.00', 130, 89, '');
INSERT INTO `facture` VALUES(2082, '0.10', '0.10', '0.00', '0000-00-00', '0.00', 130, 95, '');
INSERT INTO `facture` VALUES(2083, '1.10', '1.10', '0.00', '0000-00-00', '0.00', 130, 99, '');
INSERT INTO `facture` VALUES(2084, '8.90', '8.90', '0.00', '0000-00-00', '0.00', 130, 100, '');
INSERT INTO `facture` VALUES(2085, '2.10', '2.10', '0.00', '0000-00-00', '0.00', 130, 102, '');
INSERT INTO `facture` VALUES(2086, '48.30', '48.30', '0.00', '0000-00-00', '0.00', 130, 104, '');
INSERT INTO `facture` VALUES(2087, '5.40', '5.40', '0.00', '0000-00-00', '0.00', 130, 106, '');
INSERT INTO `facture` VALUES(2088, '0.20', '0.20', '0.00', '0000-00-00', '0.00', 130, 109, '');
INSERT INTO `facture` VALUES(2089, '0.70', '0.70', '0.00', '0000-00-00', '0.00', 130, 112, '');
INSERT INTO `facture` VALUES(2090, '0.20', '0.20', '0.00', '0000-00-00', '0.00', 130, 114, '');
INSERT INTO `facture` VALUES(2091, '0.20', '0.20', '0.00', '0000-00-00', '0.00', 130, 115, '');
INSERT INTO `facture` VALUES(2092, '13.10', '13.10', '0.00', '0000-00-00', '0.00', 130, 117, '');
INSERT INTO `facture` VALUES(2093, '0.10', '0.10', '0.00', '0000-00-00', '0.00', 130, 118, '');
INSERT INTO `facture` VALUES(2094, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 119, '');
INSERT INTO `facture` VALUES(2095, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 122, '');
INSERT INTO `facture` VALUES(2096, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 125, '');
INSERT INTO `facture` VALUES(2097, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 127, '');
INSERT INTO `facture` VALUES(2098, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 130, '');
INSERT INTO `facture` VALUES(2099, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 133, '');
INSERT INTO `facture` VALUES(2100, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 137, '');
INSERT INTO `facture` VALUES(2101, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 138, '');
INSERT INTO `facture` VALUES(2102, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 139, '');
INSERT INTO `facture` VALUES(2103, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 140, '');
INSERT INTO `facture` VALUES(2104, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 143, '');
INSERT INTO `facture` VALUES(2105, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 148, '');
INSERT INTO `facture` VALUES(2106, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 152, '');
INSERT INTO `facture` VALUES(2107, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 154, '');
INSERT INTO `facture` VALUES(2108, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 159, '');
INSERT INTO `facture` VALUES(2109, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 163, '');
INSERT INTO `facture` VALUES(2110, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 165, '');
INSERT INTO `facture` VALUES(2111, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 166, '');
INSERT INTO `facture` VALUES(2112, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 168, '');
INSERT INTO `facture` VALUES(2113, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 169, '');
INSERT INTO `facture` VALUES(2114, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 170, '');
INSERT INTO `facture` VALUES(2115, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 171, '');
INSERT INTO `facture` VALUES(2116, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 172, '');
INSERT INTO `facture` VALUES(2117, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 173, '');
INSERT INTO `facture` VALUES(2118, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 177, '');
INSERT INTO `facture` VALUES(2119, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 178, '');
INSERT INTO `facture` VALUES(2120, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 179, '');
INSERT INTO `facture` VALUES(2121, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 180, '');
INSERT INTO `facture` VALUES(2122, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 181, '');
INSERT INTO `facture` VALUES(2123, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 182, '');
INSERT INTO `facture` VALUES(2124, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 191, '');
INSERT INTO `facture` VALUES(2125, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 193, '');
INSERT INTO `facture` VALUES(2126, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 194, '');
INSERT INTO `facture` VALUES(2127, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 195, '');
INSERT INTO `facture` VALUES(2128, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 197, '');
INSERT INTO `facture` VALUES(2129, '23.00', '25.00', '0.00', '0000-00-00', '0.00', 130, 19, '');
INSERT INTO `facture` VALUES(2130, '15.00', '18.00', '0.00', '0000-00-00', '0.00', 130, 20, '');
INSERT INTO `facture` VALUES(2131, '51.00', '54.00', '0.00', '0000-00-00', '0.00', 130, 22, '');
INSERT INTO `facture` VALUES(2132, '68.00', '70.50', '0.00', '0000-00-00', '0.00', 130, 23, '');
INSERT INTO `facture` VALUES(2133, '31.00', '33.12', '0.00', '0000-00-00', '0.00', 130, 24, '');
INSERT INTO `facture` VALUES(2134, '17.00', '19.60', '0.00', '0000-00-00', '0.00', 130, 25, '');
INSERT INTO `facture` VALUES(2135, '49.00', '53.00', '0.00', '0000-00-00', '0.00', 130, 27, '');
INSERT INTO `facture` VALUES(2136, '16.00', '21.00', '0.00', '0000-00-00', '0.00', 130, 28, '');
INSERT INTO `facture` VALUES(2137, '52.00', '56.00', '0.00', '0000-00-00', '0.00', 130, 29, '');
INSERT INTO `facture` VALUES(2138, '5.90', '8.20', '0.00', '0000-00-00', '0.00', 130, 30, '');
INSERT INTO `facture` VALUES(2139, '68.00', '69.90', '0.00', '0000-00-00', '0.00', 130, 31, '');
INSERT INTO `facture` VALUES(2140, '45.00', '48.40', '0.00', '0000-00-00', '0.00', 130, 33, '');
INSERT INTO `facture` VALUES(2141, '7.00', '9.50', '0.00', '0000-00-00', '0.00', 130, 34, '');
INSERT INTO `facture` VALUES(2142, '35.00', '36.80', '0.00', '0000-00-00', '0.00', 130, 41, '');
INSERT INTO `facture` VALUES(2143, '44.00', '48.10', '0.00', '0000-00-00', '0.00', 130, 42, '');
INSERT INTO `facture` VALUES(2144, '41.00', '43.60', '0.00', '0000-00-00', '0.00', 130, 44, '');
INSERT INTO `facture` VALUES(2145, '24.00', '24.90', '0.00', '0000-00-00', '0.00', 130, 51, '');
INSERT INTO `facture` VALUES(2146, '16.00', '18.40', '0.00', '0000-00-00', '0.00', 130, 53, '');
INSERT INTO `facture` VALUES(2147, '22.00', '23.50', '0.00', '0000-00-00', '0.00', 130, 54, '');
INSERT INTO `facture` VALUES(2148, '4.00', '7.20', '0.00', '0000-00-00', '0.00', 130, 55, '');
INSERT INTO `facture` VALUES(2149, '50.00', '53.20', '0.00', '0000-00-00', '0.00', 130, 56, '');
INSERT INTO `facture` VALUES(2150, '10.00', '13.40', '0.00', '0000-00-00', '0.00', 130, 57, '');
INSERT INTO `facture` VALUES(2151, '20.00', '23.20', '0.00', '0000-00-00', '0.00', 130, 60, '');
INSERT INTO `facture` VALUES(2152, '18.60', '19.90', '0.00', '0000-00-00', '0.00', 130, 61, '');
INSERT INTO `facture` VALUES(2153, '13.70', '15.90', '0.00', '0000-00-00', '0.00', 130, 62, '');
INSERT INTO `facture` VALUES(2154, '13.80', '16.20', '0.00', '0000-00-00', '0.00', 130, 63, '');
INSERT INTO `facture` VALUES(2155, '3.80', '6.30', '0.00', '0000-00-00', '0.00', 130, 64, '');
INSERT INTO `facture` VALUES(2156, '6.80', '9.90', '0.00', '0000-00-00', '0.00', 130, 65, '');
INSERT INTO `facture` VALUES(2157, '44.60', '49.10', '0.00', '0000-00-00', '0.00', 130, 66, '');
INSERT INTO `facture` VALUES(2158, '10.00', '13.20', '0.00', '0000-00-00', '0.00', 130, 69, '');
INSERT INTO `facture` VALUES(2159, '12.10', '15.30', '0.00', '0000-00-00', '0.00', 130, 72, '');
INSERT INTO `facture` VALUES(2160, '19.90', '27.10', '0.00', '0000-00-00', '0.00', 130, 75, '');
INSERT INTO `facture` VALUES(2161, '10.00', '10.00', '0.00', '0000-00-00', '0.00', 130, 87, '');
INSERT INTO `facture` VALUES(2162, '3.90', '3.90', '0.00', '0000-00-00', '0.00', 130, 90, '');
INSERT INTO `facture` VALUES(2163, '13.10', '13.10', '0.00', '0000-00-00', '0.00', 130, 93, '');
INSERT INTO `facture` VALUES(2164, '4.10', '4.10', '0.00', '0000-00-00', '0.00', 130, 96, '');
INSERT INTO `facture` VALUES(2165, '0.60', '0.60', '0.00', '0000-00-00', '0.00', 130, 111, '');
INSERT INTO `facture` VALUES(2166, '0.10', '0.10', '0.00', '0000-00-00', '0.00', 130, 116, '');
INSERT INTO `facture` VALUES(2167, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 120, '');
INSERT INTO `facture` VALUES(2168, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 123, '');
INSERT INTO `facture` VALUES(2169, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 128, '');
INSERT INTO `facture` VALUES(2170, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 135, '');
INSERT INTO `facture` VALUES(2171, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 144, '');
INSERT INTO `facture` VALUES(2172, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 150, '');
INSERT INTO `facture` VALUES(2173, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 155, '');
INSERT INTO `facture` VALUES(2174, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 156, '');
INSERT INTO `facture` VALUES(2175, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 167, '');
INSERT INTO `facture` VALUES(2176, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 183, '');
INSERT INTO `facture` VALUES(2177, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 184, '');
INSERT INTO `facture` VALUES(2178, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 185, '');
INSERT INTO `facture` VALUES(2179, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 186, '');
INSERT INTO `facture` VALUES(2180, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 192, '');
INSERT INTO `facture` VALUES(2181, '0.00', '0.00', '0.00', '0000-00-00', '0.00', 130, 196, '');

-- --------------------------------------------------------

--
-- Structure de la table `flux_financier`
--

CREATE TABLE IF NOT EXISTS `flux_financier` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `mois` varchar(7) NOT NULL,
  `libele` varchar(128) NOT NULL,
  `prix` int(7) unsigned NOT NULL,
  `type` varchar(8) NOT NULL DEFAULT 'sortie',
  `description` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=5 ;

--
-- Contenu de la table `flux_financier`
--

INSERT INTO `flux_financier` VALUES(1, '2024-11-22', '2024-11', '25 Compteurs', 575000, 'sortie', 'Bour faire le banchement de tout les nouveaux abonement de l&#39;AEP');
INSERT INTO `flux_financier` VALUES(2, '2024-11-22', '2024-04', 'reparation', 8500, 'sortie', 'fuite du 14/11/2024 a Lewet chez Asoning Martial');
INSERT INTO `flux_financier` VALUES(3, '2024-11-22', '2024-06', 'Don de TaneDjou Ulrich', 10000, 'entree', 'Bour faire le banchement de tout les nouveaux abonement de l&#39;AEP');
INSERT INTO `flux_financier` VALUES(4, '2024-11-22', '2024-11', 'Achat', 50000, 'sortie', '');

-- --------------------------------------------------------

--
-- Structure de la table `impaye`
--

CREATE TABLE IF NOT EXISTS `impaye` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `montant` int(6) unsigned NOT NULL,
  `est_regle` int(1) DEFAULT '0',
  `id_facture` int(4) unsigned NOT NULL,
  `date_reglement` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_facture` (`id_facture`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;


create table versement(
    id integer(6) unsigned primary key auto_increment,
    montant integer(6) unsigned not null,
    date date not null,
    id_facture integer(5) unsigned not null,
    constraint fk_versement_facture foreign key (id_facture) references facture(id)
)engine=innodb default charset = utf8;


create table versement_redevance(
    id integer(6) unsigned primary key auto_increment,
    date date not null,
    id_versement integer(6) unsigned not null,
    mois varchar(10) not null ,
    constraint fk_versement_redevance_versement foreign key (id_versement) references versement(id) on delete cascade
)engine = innodb, default charset=utf8;
--
-- Contenu de la table `impaye`
--


-- --------------------------------------------------------

--
-- Structure de la table `mois_facturation`
--

CREATE TABLE IF NOT EXISTS `mois_facturation` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `mois` varchar(32) NOT NULL,
  `date_facturation` date NOT NULL,
  `date_depot` date NOT NULL,
  `id_constante` int(2) unsigned NOT NULL,
  `description` text,
  `est_actif` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mois` (`mois`),
  KEY `id_constante` (`id_constante`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=131 ;

--
-- Contenu de la table `mois_facturation`
--

INSERT INTO `mois_facturation` VALUES(130, '2024-01', '0000-00-00', '2024-12-02', 11, '', 1);

-- --------------------------------------------------------

--
-- Structure de la table `redevance`
--

CREATE TABLE IF NOT EXISTS `redevance` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `libele` varchar(64) NOT NULL,
  `pourcentage` decimal(5,2) NOT NULL,
  `description` text,
  `id_aep` int(4) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_aep_redevance` (`id_aep`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Contenu de la table `redevance`
--


-- --------------------------------------------------------

--
-- Structure de la table `reseau`
--

CREATE TABLE IF NOT EXISTS `reseau` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(32) NOT NULL,
  `abreviation` varchar(16) DEFAULT NULL,
  `date_creation` date NOT NULL,
  `description_reseau` text,
  `id_aep` int(4) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom` (`nom`),
  KEY `fk_aep_reseau` (`id_aep`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=4 ;

--
-- Contenu de la table `reseau`
--

INSERT INTO `reseau` VALUES(1, 'Nza-ah', 'Nza', '2024-09-26', '', 1);
INSERT INTO `reseau` VALUES(2, 'Mbouh', 'MB', '2024-09-26', '', 1);
INSERT INTO `reseau` VALUES(3, 'Lewet', 'LW', '2021-07-16', '', 1);

-- --------------------------------------------------------

--
-- Structure de la table `role`
--

CREATE TABLE IF NOT EXISTS `role` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `role` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Contenu de la table `role`
--


-- --------------------------------------------------------

--
-- Structure de la table `travail`
--

CREATE TABLE IF NOT EXISTS `travail` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `id_aep` int(4) unsigned NOT NULL,
  `id_utilisateur` int(4) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_aep_travail` (`id_aep`),
  KEY `fk_utilisateur_travail` (`id_utilisateur`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Contenu de la table `travail`
--


-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

CREATE TABLE IF NOT EXISTS `utilisateur` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(32) NOT NULL,
  `nom` varchar(32) NOT NULL,
  `prenom` varchar(32) NOT NULL,
  `numero_telephone` varchar(16) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Contenu de la table `utilisateur`
--


--
-- Contraintes pour les tables exportées
--

--
-- Contraintes pour la table `abone`
--
ALTER TABLE `abone`
  ADD CONSTRAINT `abone_ibfk_1` FOREIGN KEY (`id_reseau`) REFERENCES `reseau` (`id`);

--
-- Contraintes pour la table `avoir_role`
--
ALTER TABLE `avoir_role`
  ADD CONSTRAINT `fk_role_avoir_role` FOREIGN KEY (`id_role`) REFERENCES `aep` (`id`),
  ADD CONSTRAINT `fk_utilisateur_avoir_role` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`);

--
-- Contraintes pour la table `constante_reseau`
--
ALTER TABLE `constante_reseau`
  ADD CONSTRAINT `fk_aep_constante` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`);

--
-- Contraintes pour la table `facture`
--
ALTER TABLE `facture`
  ADD CONSTRAINT `facture_ibfk_1` FOREIGN KEY (`id_mois_facturation`) REFERENCES `mois_facturation` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `facture_ibfk_2` FOREIGN KEY (`id_abone`) REFERENCES `abone` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `impaye`
--
ALTER TABLE `impaye`
  ADD CONSTRAINT `impaye_ibfk_1` FOREIGN KEY (`id_facture`) REFERENCES `facture` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `mois_facturation`
--
ALTER TABLE `mois_facturation`
  ADD CONSTRAINT `mois_facturation_ibfk_1` FOREIGN KEY (`id_constante`) REFERENCES `constante_reseau` (`id`);

--
-- Contraintes pour la table `redevance`
--
ALTER TABLE `redevance`
  ADD CONSTRAINT `fk_aep_redevance` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`);

--
-- Contraintes pour la table `reseau`
--
ALTER TABLE `reseau`
  ADD CONSTRAINT `fk_aep_reseau` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`);

--
-- Contraintes pour la table `travail`
--
ALTER TABLE `travail`
  ADD CONSTRAINT `fk_aep_travail` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`),
  ADD CONSTRAINT `fk_utilisateur_travail` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`);


alter table flux_financier
    add column id_aep integer(5) unsigned not null default 1,
    add constraint fk_aep_flux_financier foreign key (id_aep) references aep(id) on delete cascade ;