-- Export SQL généré par l'application (PHP)
SET NAMES utf8;
SET FOREIGN_KEY_CHECKS=0;


-- -----------------------------
-- Structure de la table `abone`
-- -----------------------------
DROP TABLE IF EXISTS `abone`;
CREATE TABLE `abone` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(128) NOT NULL,
  `numero_telephone` varchar(16) NOT NULL,
  `numero_compte_anticipation` varchar(16) NOT NULL,
  `etat` varchar(10) NOT NULL,
  `rang` int(4) DEFAULT NULL,
  `id_reseau` int(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_reseau` (`id_reseau`),
  CONSTRAINT `abone_ibfk_1` FOREIGN KEY (`id_reseau`) REFERENCES `reseau` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=470 DEFAULT CHARSET=latin1;

-- Données de `abone`

-- -----------------------------
-- Structure de la table `aep`
-- -----------------------------
DROP TABLE IF EXISTS `aep`;
CREATE TABLE `aep` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `libele` varchar(64) NOT NULL,
  `description` text,
  `fichier_facture` varchar(64) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `numero_compte` varchar(32) DEFAULT NULL,
  `nom_banque` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=latin1;

-- Données de `aep`

-- -----------------------------
-- Structure de la table `clefs`
-- -----------------------------
DROP TABLE IF EXISTS `clefs`;
CREATE TABLE `clefs` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `value` (`value`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=latin1;

-- Données de `clefs`
INSERT INTO `clefs` (`id`,`value`) VALUES ('1','Toto'),('2','Bami'),('3','Tami'),('4','popo'),('5','lewis1234'),('6','blanche1234'),('7','bertin1234'),('8','ange1234'),('9','achile1234'),('10','testblanche'),('11','martial1234'),('12','eric1234');


-- -----------------------------
-- Structure de la table `compteur`
-- -----------------------------
DROP TABLE IF EXISTS `compteur`;
CREATE TABLE `compteur` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `numero_compteur` varchar(16) NOT NULL,
  `longitude` decimal(12,6) DEFAULT NULL,
  `latitude` decimal(12,6) DEFAULT NULL,
  `derniers_index` decimal(7,2) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=472 DEFAULT CHARSET=latin1;

-- Données de `compteur`
INSERT INTO `compteur` (`id`,`numero_compteur`,`longitude`,`latitude`,`derniers_index`,`description`) VALUES ('1','Bass00001','0.000000','0.000000','27.00',''),('2','Bass00002','0.000000','0.000000','20.00',''),('3','00000020','0.000000','0.000000','20.00',''),('4','000154','0.000000','0.000000','25.00',''),('5','000154','0.000000','0.000000','0.00',''),('6','15421613','0.000000','0.000000','11.20',''),('7','654123','0.000000','0.000000','6.30',''),('8','123520p','0.000000','0.000000','2.20',''),('9','Rs123456','0.000000','0.000000','3.50',''),('10','000154857','0.000000','0.000000','5.50',''),('11','5487546','0.000000','0.000000','3.00',''),('271','2012012','0.000000','0.000000','13.10',''),('272','2012890','0.000000','0.000000','47.90',''),('273','2012615','0.000000','0.000000','302.80',''),('274','78186232','0.000000','0.000000','514.60',''),('275','2012077','0.000000','0.000000','135.30',''),('276','2012146','0.000000','0.000000','209.30',''),('277','2012540','0.000000','0.000000','146.70',''),('278','2012210','0.000000','0.000000','187.60',''),('279','2012882','0.000000','0.000000','79.10',''),('280','2012322','0.000000','0.000000','36.70',''),('281','16051888','0.000000','0.000000','471.00',''),('282','16056859','0.000000','0.000000','461.00',''),('283','16052767','0.000000','0.000000','112.20',''),('284','17062000','0.000000','0.000000','357.20',''),('285','2012866','0.000000','0.000000','369.50',''),('286','2012167','0.000000','0.000000','47.20',''),('287','2012567','0.000000','0.000000','77.80',''),('288','2012508','0.000000','0.000000','139.80',''),('289','2012009','0.000000','0.000000','393.60',''),('290','2012997','0.000000','0.000000','21.50',''),('291','2032422','0.000000','0.000000','17.90',''),('292','2012202','0.000000','0.000000','97.50',''),('293','2012058','0.000000','0.000000','67.50',''),('294','2012052','0.000000','0.000000','184.70',''),('295','2012055','0.000000','0.000000','310.50',''),('296','2012083','0.000000','0.000000','50.00',''),('297','2012215','0.000000','0.000000','3.80',''),('298','2012502','0.000000','0.000000','22.70',''),('299','2012595','0.000000','0.000000','334.10',''),('300','2012319','0.000000','0.000000','77.50',''),('301','2012323','0.000000','0.000000','201.50',''),('302','2012992','0.000000','0.000000','99.70',''),('303','2012054','0.000000','0.000000','96.70',''),('304','2012717','0.000000','0.000000','1079.00',''),('305','2012012','0.000000','0.000000','27.70',''),('306','2012916','0.000000','0.000000','66.70',''),('307','2012602','0.000000','0.000000','1.50',''),('308','2012062','0.000000','0.000000','426.20',''),('309','2012051','0.000000','0.000000','97.90',''),('310','2012072','0.000000','0.000000','155.00',''),('311','2012152','0.000000','0.000000','96.20',''),('312','2012794','0.000000','0.000000','98.00',''),('313','2012986','0.000000','0.000000','55.60',''),('314','2012347','0.000000','0.000000','6.30',''),('315','2012326','0.000000','0.000000','14.50',''),('316','2012385','0.000000','0.000000','27.00',''),('317','2012950','0.000000','0.000000','48.50',''),('318','2012683','0.000000','0.000000','67.20',''),('319','2012729','0.000000','0.000000','19.00',''),('320','2012026','0.000000','0.000000','60.10',''),('321','2012863','0.000000','0.000000','32.70',''),('322','2012589','0.000000','0.000000','205.20',''),('323','2012702','0.000000','0.000000','525.00',''),('324','2020813','0.000000','0.000000','264.70',''),('325','2020041','0.000000','0.000000','69.80',''),('326','2012021','0.000000','0.000000','85.10',''),('327','2020335','0.000000','0.000000','16.20',''),('328','2020087','0.000000','0.000000','74.60',''),('329','2020319','0.000000','0.000000','263.60',''),('330','2020025','0.000000','0.000000','160.60',''),('331','LETK30082025','0.000000','0.000000','1411.10',''),('332','2020963','0.000000','0.000000','208.30',''),('333','2020519','0.000000','0.000000','540.30',''),('334','2020255','0.000000','0.000000','24.50',''),('335','2020448','0.000000','0.000000','20.10',''),('336','2020595','0.000000','0.000000','1170.00',''),('337','2020540','0.000000','0.000000','63.80',''),('338','2020442','0.000000','0.000000','142.20',''),('339','2020452','0.000000','0.000000','3.20',''),('340','2020482','0.000000','0.000000','134.80',''),('341','2020359','0.000000','0.000000','9.20',''),('342','2020667','0.000000','0.000000','68.10',''),('343','2020452','0.000000','0.000000','152.90',''),('344','2020825','0.000000','0.000000','101.30',''),('345','2020099','0.000000','0.000000','10.90',''),('346','2020310','0.000000','0.000000','52.40',''),('347','2020400','0.000000','0.000000','69.10',''),('348','2020037','0.000000','0.000000','60.50',''),('349','2020445','0.000000','0.000000','115.50',''),('350','2020919','0.000000','0.000000','25.10',''),('351','2020934','0.000000','0.000000','442.00',''),('352','2020904','0.000000','0.000000','100.70',''),('353','2020408','0.000000','0.000000','120.00',''),('354','2020939','0.000000','0.000000','52.40',''),('355','2020030','0.000000','0.000000','299.90',''),('356','2020554','0.000000','0.000000','90.80',''),('357','2020589','0.000000','0.000000','13.00',''),('358','2020313','0.000000','0.000000','37.90',''),('359','2020302','0.000000','0.000000','70.80',''),('360','2020206','0.000000','0.000000','215.00',''),('361','2020953','0.000000','0.000000','99.20',''),('362','2020464','0.000000','0.000000','168.10',''),('363','201707483','0.000000','0.000000','50.80',''),('364','20170718027','0.000000','0.000000','355.20',''),('365','20170717605','0.000000','0.000000','297.30',''),('366','201707503','0.000000','0.000000','8.30',''),('367','2017076087','0.000000','0.000000','93.00',''),('368','2020615','0.000000','0.000000','315.60',''),('369','2012340','0.000000','0.000000','507.20',''),('370','2012210','0.000000','0.000000','29.60',''),('371','183265','0.000000','0.000000','22.20',''),('372','2012665','0.000000','0.000000','14.20',''),('373','2001133','0.000000','0.000000','600.70',''),('374','2012349','0.000000','0.000000','25.50',''),('375','2017076087','0.000000','0.000000','13.80',''),('376','201707914','0.000000','0.000000','25.90',''),('377','2012510','0.000000','0.000000','52.30',''),('378','183597','0.000000','0.000000','13.70',''),('379','2017075606','0.000000','0.000000','7.50',''),('380','2100027','0.000000','0.000000','82.40',''),('381','2012813','0.000000','0.000000','168.00',''),('382','MBMKJ30082025','0.000000','0.000000','10.00',''),('383','2012878','0.000000','0.000000','104.00',''),('384','2101467','0.000000','0.000000','140.60',''),('385','201707057','0.000000','0.000000','148.90',''),('386','183797','0.000000','0.000000','19.90',''),('387','2020897','0.000000','0.000000','630.70',''),('388','183797','0.000000','0.000000','1.30',''),('389','2012141','0.000000','0.000000','34.40',''),('390','182271','0.000000','0.000000','52.00',''),('391','9064541','0.000000','0.000000','160.20',''),('392','2304104','0.000000','0.000000','232.50',''),('393','2304406','0.000000','0.000000','93.20',''),('394','2304236','0.000000','0.000000','23.50',''),('395','2304170','0.000000','0.000000','206.70',''),('396','2304077','0.000000','0.000000','132.50',''),('397','2304204','0.000000','0.000000','37.60',''),('398','2304752','0.000000','0.000000','35.00',''),('399','2304681','0.000000','0.000000','115.40',''),('400','2304923','0.000000','0.000000','10.80',''),('401','2304565','0.000000','0.000000','36.30',''),('402','2304621','0.000000','0.000000','76.70',''),('403','2304108','0.000000','0.000000','23.20',''),('404','2304108','0.000000','0.000000','3.40',''),('405','2304898','0.000000','0.000000','16.00',''),('406','2304062','0.000000','0.000000','41.30',''),('407','2304801','0.000000','0.000000','35.20',''),('408','2304890','0.000000','0.000000','358.20',''),('409','2304023','0.000000','0.000000','7714.00',''),('410','2304724','0.000000','0.000000','33.50',''),('411','2304104','0.000000','0.000000','33.30',''),('412','9064591','0.000000','0.000000','599.90',''),('413','2304093','0.000000','0.000000','22.20',''),('414','2304778','0.000000','0.000000','109.10',''),('415','3204180','0.000000','0.000000','8.50',''),('416','2304132','0.000000','0.000000','56.80',''),('417','112024','0.000000','0.000000','10.70',''),('418','112024','0.000000','0.000000','6.80',''),('419','18142024','0.000000','0.000000','23.90',''),('420','8113024','0.000000','0.000000','85.00',''),('421','9064541','0.000000','0.000000','160.20',''),('422','2304104','0.000000','0.000000','232.50',''),('423','2304406','0.000000','0.000000','93.20',''),('424','2304236','0.000000','0.000000','23.50',''),('425','2304170','0.000000','0.000000','206.70',''),('426','2304077','0.000000','0.000000','132.50',''),('427','2304204','0.000000','0.000000','37.60',''),('428','2304752','0.000000','0.000000','35.00',''),('429','2304681','0.000000','0.000000','115.40',''),('430','2304923','0.000000','0.000000','10.80',''),('431','2304565','0.000000','0.000000','36.30',''),('432','2304621','0.000000','0.000000','76.70',''),('433','2304108','0.000000','0.000000','23.20',''),('434','2304108','0.000000','0.000000','3.40',''),('435','2304898','0.000000','0.000000','16.00',''),('436','2304062','0.000000','0.000000','41.30',''),('437','2304801','0.000000','0.000000','35.20',''),('438','2304890','0.000000','0.000000','358.20',''),('439','2304023','0.000000','0.000000','7714.00',''),('440','2304724','0.000000','0.000000','33.50',''),('441','2304104','0.000000','0.000000','33.30',''),('442','9064591','0.000000','0.000000','599.90',''),('443','2304093','0.000000','0.000000','22.20',''),('444','2304778','0.000000','0.000000','109.10',''),('445','3204180','0.000000','0.000000','8.50',''),('446','2304132','0.000000','0.000000','56.80',''),('447','112024','0.000000','0.000000','10.70',''),('448','112024','0.000000','0.000000','6.80',''),('449','18142024','0.000000','0.000000','23.90',''),('450','8113024','0.000000','0.000000','85.00',''),('451','21152024','0.000000','0.000000','10.80',''),('452','20162024','0.000000','0.000000','105.00',''),('453','171212024','0.000000','0.000000','0.70',''),('454','161202024','0.000000','0.000000','49.20',''),('455','28-T2025','0.000000','0.000000','5.00',''),('456','W1661760','0.000000','0.000000','21.50',''),('457','21152024','0.000000','0.000000','120.30',''),('458','W-2-2025','0.000000','0.000000','7.60',''),('459','6052025','0.000000','0.000000','7.90',''),('460','20250303','0.000000','0.000000','9.20',''),('461','12052025','0.000000','0.000000','5.60',''),('462','W12017534','0.000000','0.000000','6.50',''),('463','12052025','0.000000','0.000000','11.20',''),('464','28052025','0.000000','0.000000','6.70',''),('465','07M72025','0.000000','0.000000','0.10',''),('466','07W72025','0.000000','0.000000','29.00',''),('467','08K72025','0.000000','0.000000','0.10',''),('468','8T72025','0.000000','0.000000','11.80',''),('469','12M982025','0.000000','0.000000','0.40',''),('470','01NI82025','0.000000','0.000000','0.20',''),('471','54565254','0.000000','0.000000','12.00','');


-- -----------------------------
-- Structure de la table `compteur_abone`
-- -----------------------------
DROP TABLE IF EXISTS `compteur_abone`;
CREATE TABLE `compteur_abone` (
  `id_abone` int(5) unsigned NOT NULL,
  `id_compteur` int(5) unsigned NOT NULL,
  KEY `fk_abone_compteur_abone` (`id_abone`),
  KEY `fk_compteur_compteur_abone` (`id_compteur`),
  CONSTRAINT `fk_abone_compteur_abone` FOREIGN KEY (`id_abone`) REFERENCES `abone` (`id`),
  CONSTRAINT `fk_compteur_compteur_abone` FOREIGN KEY (`id_compteur`) REFERENCES `compteur` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Données de `compteur_abone`

-- -----------------------------
-- Structure de la table `compteur_aep`
-- -----------------------------
DROP TABLE IF EXISTS `compteur_aep`;
CREATE TABLE `compteur_aep` (
  `id_aep` int(4) unsigned NOT NULL,
  `id_compteur` int(5) unsigned NOT NULL,
  `id_position` int(2) unsigned NOT NULL,
  KEY `fk_position_compteur_aep` (`id_position`),
  KEY `fk_aep_compteur_aep` (`id_aep`),
  KEY `fk_compteur_compteur_aep` (`id_compteur`),
  CONSTRAINT `fk_aep_compteur_aep` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`),
  CONSTRAINT `fk_compteur_compteur_aep` FOREIGN KEY (`id_compteur`) REFERENCES `compteur` (`id`),
  CONSTRAINT `fk_position_compteur_aep` FOREIGN KEY (`id_position`) REFERENCES `position_compteur_aep` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Données de `compteur_aep`

-- -----------------------------
-- Structure de la table `compteur_reseau`
-- -----------------------------
DROP TABLE IF EXISTS `compteur_reseau`;
CREATE TABLE `compteur_reseau` (
  `id_reseau` int(5) unsigned NOT NULL,
  `id_compteur` int(5) unsigned NOT NULL,
  KEY `fk_reseau_compteur_reseau` (`id_reseau`),
  KEY `fk_compteur_compteur_reseau` (`id_compteur`),
  CONSTRAINT `fk_compteur_compteur_reseau` FOREIGN KEY (`id_compteur`) REFERENCES `compteur` (`id`),
  CONSTRAINT `fk_reseau_compteur_reseau` FOREIGN KEY (`id_reseau`) REFERENCES `reseau` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Données de `compteur_reseau`

-- -----------------------------
-- Structure de la table `constante_reseau`
-- -----------------------------
DROP TABLE IF EXISTS `constante_reseau`;
CREATE TABLE `constante_reseau` (
  `id` int(2) unsigned NOT NULL AUTO_INCREMENT,
  `prix_metre_cube_eau` int(5) unsigned NOT NULL,
  `prix_entretient_compteur` int(5) unsigned NOT NULL,
  `prix_tva` decimal(7,2) unsigned NOT NULL,
  `date_creation` date NOT NULL,
  `est_actif` tinyint(1) NOT NULL,
  `description` text,
  `id_aep` int(4) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `fk_aep_constante` (`id_aep`),
  CONSTRAINT `fk_aep_constante` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1;

-- Données de `constante_reseau`

-- -----------------------------
-- Structure de la table `facture`
-- -----------------------------
DROP TABLE IF EXISTS `facture`;
CREATE TABLE `facture` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `id_indexes` int(6) unsigned NOT NULL,
  `montant_verse` decimal(15,2) DEFAULT '0.00',
  `date_paiement` date DEFAULT NULL,
  `penalite` decimal(15,2) DEFAULT '0.00',
  `id_abone` int(4) unsigned NOT NULL,
  `message` text,
  PRIMARY KEY (`id`),
  KEY `id_abone` (`id_abone`),
  KEY `fk_index_facture` (`id_indexes`),
  CONSTRAINT `facture_ibfk_2` FOREIGN KEY (`id_abone`) REFERENCES `abone` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_index_facture` FOREIGN KEY (`id_indexes`) REFERENCES `indexes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1183 DEFAULT CHARSET=latin1;

-- Données de `facture`

-- -----------------------------
-- Structure de la table `flux_financier`
-- -----------------------------
DROP TABLE IF EXISTS `flux_financier`;
CREATE TABLE `flux_financier` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `mois` varchar(7) NOT NULL,
  `libele` varchar(128) NOT NULL,
  `prix` bigint(20) unsigned NOT NULL,
  `type` varchar(8) NOT NULL DEFAULT 'sortie',
  `description` text,
  `id_aep` int(5) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `fk_aep_flux_financier` (`id_aep`),
  CONSTRAINT `fk_aep_flux_financier` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1;

-- Données de `flux_financier`

-- -----------------------------
-- Structure de la table `impaye`
-- -----------------------------
DROP TABLE IF EXISTS `impaye`;
CREATE TABLE `impaye` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `montant` bigint(20) DEFAULT NULL,
  `est_regle` int(1) DEFAULT '0',
  `id_facture` int(4) unsigned NOT NULL,
  `date_reglement` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_facture` (`id_facture`),
  CONSTRAINT `impaye_ibfk_1` FOREIGN KEY (`id_facture`) REFERENCES `facture` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=211 DEFAULT CHARSET=latin1;

-- Données de `impaye`

-- -----------------------------
-- Structure de la table `indexes`
-- -----------------------------
DROP TABLE IF EXISTS `indexes`;
CREATE TABLE `indexes` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `id_compteur` int(5) unsigned NOT NULL,
  `id_mois_facturation` int(3) unsigned NOT NULL,
  `ancien_index` decimal(8,2) NOT NULL,
  `nouvel_index` decimal(7,2) unsigned NOT NULL,
  `message` text,
  PRIMARY KEY (`id`),
  KEY `fk_compteur_indexes` (`id_compteur`),
  KEY `fk_mois_facturatio_indexes` (`id_mois_facturation`),
  CONSTRAINT `fk_compteur_indexes` FOREIGN KEY (`id_compteur`) REFERENCES `compteur` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mois_facturatio_indexes` FOREIGN KEY (`id_mois_facturation`) REFERENCES `mois_facturation` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1205 DEFAULT CHARSET=latin1;

-- Données de `indexes`

-- -----------------------------
-- Structure de la table `intervention_rh`
-- -----------------------------
DROP TABLE IF EXISTS `intervention_rh`;
CREATE TABLE `intervention_rh` (
  `intervention_id` int(11) NOT NULL,
  `rh_id` int(11) NOT NULL,
  `role_sur_intervention` varchar(120) DEFAULT NULL,
  `heures_prevues` decimal(12,2) DEFAULT '0.00',
  `heures_reelles` decimal(12,2) DEFAULT '0.00',
  `cout_prevu` decimal(14,2) DEFAULT '0.00',
  `cout_reel` decimal(14,2) DEFAULT '0.00',
  PRIMARY KEY (`intervention_id`,`rh_id`),
  KEY `idx_irh_rh` (`rh_id`),
  CONSTRAINT `fk_irh_intervention` FOREIGN KEY (`intervention_id`) REFERENCES `interventions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_irh_rh` FOREIGN KEY (`rh_id`) REFERENCES `ressources_humaines` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Données de `intervention_rh`

-- -----------------------------
-- Structure de la table `intervention_rm`
-- -----------------------------
DROP TABLE IF EXISTS `intervention_rm`;
CREATE TABLE `intervention_rm` (
  `intervention_id` int(11) NOT NULL,
  `rm_id` int(11) NOT NULL,
  `quantite_prevue` decimal(12,2) DEFAULT '0.00',
  `quantite_reelle` decimal(12,2) DEFAULT '0.00',
  `cout_prevu` decimal(14,2) DEFAULT '0.00',
  `cout_reel` decimal(14,2) DEFAULT '0.00',
  PRIMARY KEY (`intervention_id`,`rm_id`),
  KEY `idx_irm_rm` (`rm_id`),
  CONSTRAINT `fk_irm_intervention` FOREIGN KEY (`intervention_id`) REFERENCES `interventions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_irm_rm` FOREIGN KEY (`rm_id`) REFERENCES `ressources_materielles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Données de `intervention_rm`

-- -----------------------------
-- Structure de la table `interventions`
-- -----------------------------
DROP TABLE IF EXISTS `interventions`;
CREATE TABLE `interventions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aep_id` int(11) NOT NULL,
  `titre` varchar(200) NOT NULL,
  `type` varchar(100) NOT NULL,
  `description` text,
  `localisation` varchar(200) DEFAULT NULL,
  `date_debut_prevue` datetime DEFAULT NULL,
  `date_fin_prevue` datetime DEFAULT NULL,
  `date_debut_reelle` datetime DEFAULT NULL,
  `date_fin_reelle` datetime DEFAULT NULL,
  `statut` enum('planifiee','en_cours','terminee','annulee') DEFAULT 'planifiee',
  `cout_estime` decimal(14,2) DEFAULT '0.00',
  `cout_reel` decimal(14,2) DEFAULT '0.00',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_interv_aep` (`aep_id`),
  KEY `idx_interv_statut` (`statut`),
  KEY `idx_interv_dates` (`date_debut_prevue`,`date_fin_prevue`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- Données de `interventions`
INSERT INTO `interventions` (`id`,`aep_id`,`titre`,`type`,`description`,`localisation`,`date_debut_prevue`,`date_fin_prevue`,`date_debut_reelle`,`date_fin_reelle`,`statut`,`cout_estime`,`cout_reel`,`created_by`,`created_at`,`updated_at`) VALUES ('1','1','Nettoyage des vanes','maintenances','djjdjdsjksdksd  sjsdksd dskd','Reservoire Nzah','2025-08-13 14:24:00','0000-00-00 00:00:00',NULL,'2025-08-29 14:26:05','terminee','0.00','0.00','7',NULL,NULL);


-- -----------------------------
-- Structure de la table `logs`
-- -----------------------------
DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `page_libelle` varchar(255) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `timestamp` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=45 DEFAULT CHARSET=latin1;

-- Données de `logs`
INSERT INTO `logs` (`id`,`user_id`,`page_libelle`,`action`,`timestamp`) VALUES ('1',NULL,'page de role','update_role','0000-00-00 00:00:00'),('2',NULL,'role','add_role#4','2025-04-22 18:54:20'),('3',NULL,'role','add_role#5','2025-04-22 18:55:00'),('4',NULL,'role','add_role#6','2025-04-22 18:56:03'),('5',NULL,'role','add_role#7','2025-04-22 18:57:36'),('6',NULL,'role','delete_role','0000-00-00 00:00:00'),('7',NULL,'role','delete_role','0000-00-00 00:00:00'),('8',NULL,'role','delete_role','0000-00-00 00:00:00'),('9',NULL,'role','delete_role','0000-00-00 00:00:00'),('10',NULL,'role','delete_role#4','0000-00-00 00:00:00'),('11',NULL,'page de role','update_role','0000-00-00 00:00:00'),('12',NULL,'role_detail','remove_user_role#1','2025-04-22 21:24:28'),('13',NULL,'role_detail','add_user_role#1','2025-04-22 21:25:06'),('14',NULL,'role','delete_role#2','0000-00-00 00:00:00'),('15',NULL,'role','add_role#8','2025-04-23 13:59:18'),('16',NULL,'role','add_role#9','2025-04-23 13:59:27'),('17',NULL,'role_detail','remove_page_access#1','2025-04-27 11:29:38'),('18',NULL,'role_detail','remove_page_access#1','2025-04-27 11:30:07'),('19',NULL,'role_detail','add_page_access#1#1','2025-04-27 11:33:17'),('20',NULL,'role_detail','add_page_access#8#1','2025-04-27 11:44:32'),('21',NULL,'role_detail','add_user_role#8','2025-04-27 11:48:16'),('22',NULL,'role_detail','add_user_role#1','2025-04-29 12:24:17'),('23',NULL,'role_detail','add_page_access#8#3','2025-04-29 13:01:16'),('24',NULL,'role_detail','add_page_access#8#3','2025-04-29 13:04:42'),('25',NULL,'role_detail','add_page_access#8#3','2025-04-29 13:05:35'),('26',NULL,'role_detail','add_user_role#8','2025-04-29 13:18:57'),('27',NULL,'role_detail','remove_user_role#8','2025-04-29 14:06:40'),('28',NULL,'role_detail','remove_user_role#8','2025-04-29 14:44:20'),('29',NULL,'role_detail','remove_user_role#8','2025-04-29 14:44:35'),('30',NULL,'role_detail','remove_page_access#9','2025-04-29 14:45:31'),('31',NULL,'role_detail','remove_page_access#9','2025-04-29 14:45:44'),('32',NULL,'role_detail','remove_page_access#9','2025-04-29 14:45:58'),('33',NULL,'role','add_role#10','2025-04-29 14:46:57'),('34',NULL,'role','add_role#11','2025-04-30 13:18:27'),('35',NULL,'role_detail','add_user_role#9','2025-05-12 11:39:30'),('36',NULL,'role_detail','add_user_role#9','2025-06-20 12:20:25'),('37',NULL,'role_detail','add_user_role#8','2025-06-20 12:24:57'),('38',NULL,'role_detail','add_user_role#10','2025-06-20 12:38:27'),('39',NULL,'role_detail','add_user_role#1','2025-08-01 12:38:26'),('40',NULL,'role_detail','add_user_role#1','2025-08-01 12:38:37'),('41',NULL,'role_detail','add_user_role#1','2025-08-01 12:38:56'),('42',NULL,'role_detail','add_user_role#1','2025-08-01 12:40:32'),('43',NULL,'role','add_role#12','2025-08-01 12:42:57'),('44',NULL,'role_detail','add_user_role#12','2025-08-01 12:43:07');


-- -----------------------------
-- Structure de la table `mois_facturation`
-- -----------------------------
DROP TABLE IF EXISTS `mois_facturation`;
CREATE TABLE `mois_facturation` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `mois` varchar(32) NOT NULL,
  `date_facturation` date NOT NULL,
  `date_depot` date NOT NULL,
  `id_constante` int(2) unsigned NOT NULL,
  `description` text,
  `est_actif` tinyint(1) NOT NULL,
  `date_releve` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_id_mois` (`id`,`mois`),
  KEY `id_constante` (`id_constante`),
  CONSTRAINT `mois_facturation_ibfk_1` FOREIGN KEY (`id_constante`) REFERENCES `constante_reseau` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=latin1;

-- Données de `mois_facturation`

-- -----------------------------
-- Structure de la table `page_role_aep`
-- -----------------------------
DROP TABLE IF EXISTS `page_role_aep`;
CREATE TABLE `page_role_aep` (
  `page_id` int(11) NOT NULL DEFAULT '0',
  `role_id` int(11) NOT NULL DEFAULT '0',
  `write_access` int(1) DEFAULT '0',
  PRIMARY KEY (`page_id`,`role_id`),
  KEY `role_id` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Données de `page_role_aep`
INSERT INTO `page_role_aep` (`page_id`,`role_id`,`write_access`) VALUES ('24','1','1'),('25','1','1'),('26','1','1'),('27','1','1'),('28','1','1'),('53','1','1'),('30','1','1'),('31','1','1'),('32','1','1'),('33','1','1'),('34','1','1'),('35','1','1'),('36','1','1'),('37','1','1'),('38','1','1'),('39','1','1'),('40','1','1'),('41','1','1'),('42','1','1'),('43','1','1'),('44','1','1'),('45','1','1'),('46','1','1'),('29','1','1'),('48','1','1'),('49','1','1'),('50','1','1'),('51','1','1'),('52','1','1'),('53','8','0'),('39','8','0'),('38','8','1'),('54','1','1'),('36','10','0'),('33','10','0'),('26','10','0'),('50','9','0'),('43','9','0'),('42','9','0'),('41','9','0'),('40','9','0'),('26','9','0'),('26','8','0'),('54','10','0'),('55','1','1'),('56','1','1'),('26','11','0'),('57','1','1'),('58','1','1'),('59','1','1'),('60','1','1'),('61','1','1'),('62','1','1'),('63','1','1'),('64','1','1'),('65','1','1'),('66','1','1'),('67','1','1'),('68','1','1'),('69','1','1'),('70','1','1'),('71','1','1'),('72','1','1'),('73','1','1'),('74','1','1'),('75','1','1'),('76','1','1'),('77','1','1'),('78','1','1'),('69','9','0'),('70','9','0'),('74','8','0'),('25','12','0'),('26','12','0'),('27','12','0'),('28','12','0'),('29','12','0'),('30','12','0'),('31','12','0'),('32','12','0'),('33','12','0'),('34','12','0'),('35','12','0'),('36','12','0'),('37','12','0'),('38','12','0'),('39','12','0'),('40','12','0'),('41','12','0'),('42','12','0'),('43','12','0'),('45','12','0'),('46','12','0'),('47','12','0'),('48','12','0'),('49','12','0'),('50','12','0'),('51','12','0'),('52','12','0'),('53','12','0'),('54','12','0'),('55','12','0'),('56','12','0'),('57','12','0'),('58','12','0'),('59','12','0'),('60','12','0'),('61','12','0'),('62','12','0'),('63','12','0'),('64','12','0'),('65','12','0'),('66','12','0'),('67','12','0'),('68','12','0'),('69','12','0'),('70','12','0'),('71','12','0'),('72','12','0'),('73','12','0'),('74','12','0'),('75','12','0'),('76','12','0'),('77','12','0'),('78','12','0'),('79','1','1'),('80','1','1'),('81','1','1'),('82','1','1'),('83','1','1'),('84','1','1'),('85','1','1'),('86','1','1'),('87','1','1'),('88','1','1'),('89','1','1'),('90','1','1'),('91','1','1'),('92','1','1'),('93','1','1'),('94','1','1'),('95','1','1'),('96','1','1');


-- -----------------------------
-- Structure de la table `pages`
-- -----------------------------
DROP TABLE IF EXISTS `pages`;
CREATE TABLE `pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chaine` varchar(255) NOT NULL,
  `libelle` varchar(255) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_chaine` (`chaine`)
) ENGINE=MyISAM AUTO_INCREMENT=97 DEFAULT CHARSET=latin1;

-- Données de `pages`
INSERT INTO `pages` (`id`,`chaine`,`libelle`,`description`) VALUES ('24','page=role_detail','role_detail',''),('25','form=aep','aep',''),('26','page=home','home',''),('27','page=reseau','reseau',''),('28','form=reseau','reseau',''),('29','form=abone','abone',''),('30','list=compteur_reseau','compteur_reseau',''),('31','list=distribution_simple','distribution_simple',''),('32','list=production_simple','production_simple',''),('33','list=recouvrement','recouvrement',''),('34','list=facture_month','facture_month',''),('35','list=liste_facture_month','liste_facture_month',''),('36','list=mois_facturation','mois_facturation',''),('37','form=constante_reseau','constante_reseau',''),('38','form=import_index','import_index',''),('39','list=releve_manuelle','releve_manuelle',''),('40','form=finance','finance',''),('41','list=transaction','transaction',''),('42','form=login','login',''),('43','form=register','register',''),('44','form=role','role',''),('45','page=register','register',''),('46','page=role','role',''),('47','page=login','login',''),('48','page=aep','aep',''),('49','page=edit_aep','edit_aep',''),('50','page=logout','logout',''),('51','list=logout','logout',''),('52','page=toto','toto',''),('53','page=download_index','download_index',''),('54','page=info_abone','info_abone',''),('55','page=clefs','clefs',''),('56','page=user_details','user_details',''),('57','page=redevance','redevance',''),('58','page=versement','versement',''),('59','page=clef','clef',''),('60','page=users','users',''),('61','page=page','page',''),('62','list=versements','versements',''),('63','page=versements','versements',''),('64','page=detail_revance','detail_revance',''),('65','page=revanc_detailse','revanc_detailse',''),('66','page=revance_detailse','revance_detailse',''),('67','page=redevance_detailse','redevance_detailse',''),('68','page=redevance_details','redevance_details',''),('69','page=aep_dashbord','aep_dashbord',''),('70','page=aep_dashboard','aep_dashboard',''),('71','page=transaction','transaction',''),('72','list=releve_page','releve_page',''),('73','form=export_index','export_index',''),('74','page=releves','releves',''),('75','list=releve_indexx','releve_indexx',''),('76','list=releve_index','releve_index',''),('77','id_mois=10','10',''),('78','list=abone_simple','abone_simple',''),('79','page=relevesoperation=error','relevesoperation',''),('80','page=constante_reseau','constante_reseau',''),('81','page=constante_reseau_page','constante_reseau_page',''),('82','id_mois=18','18',''),('83','page=ressource','ressource',''),('84','page=ressources','ressources',''),('85','page=interventions','interventions',''),('86','page=backups','backups',''),('87','page=backup','backup',''),('88','page=abones','abones',''),('89','page=fokoue_data','fokoue_data',''),('90','page=tarif_aep','tarif_aep',''),('91','page=recouvrement','recouvrement',''),('92','page=abonne','abonne',''),('93','ajout=1','1',''),('94','ajout_abone=1','1',''),('95','page=reseaux','reseaux',''),('96','page=reseau_detail','reseau_detail','');


-- -----------------------------
-- Structure de la table `position_compteur_aep`
-- -----------------------------
DROP TABLE IF EXISTS `position_compteur_aep`;
CREATE TABLE `position_compteur_aep` (
  `id` int(2) unsigned NOT NULL AUTO_INCREMENT,
  `position` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Données de `position_compteur_aep`

-- -----------------------------
-- Structure de la table `redevance`
-- -----------------------------
DROP TABLE IF EXISTS `redevance`;
CREATE TABLE `redevance` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `libele` varchar(64) NOT NULL,
  `pourcentage` decimal(5,2) NOT NULL,
  `description` text,
  `id_aep` int(4) unsigned DEFAULT NULL,
  `type` varchar(8) NOT NULL DEFAULT 'sortie',
  `mois_debut` varchar(8) DEFAULT '2025-03',
  PRIMARY KEY (`id`),
  KEY `fk_aep_redevance` (`id_aep`),
  CONSTRAINT `fk_aep_redevance` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;

-- Données de `redevance`

-- -----------------------------
-- Structure de la table `reseau`
-- -----------------------------
DROP TABLE IF EXISTS `reseau`;
CREATE TABLE `reseau` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(32) NOT NULL,
  `abreviation` varchar(16) DEFAULT NULL,
  `date_creation` date NOT NULL,
  `description_reseau` text,
  `id_aep` int(4) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `fk_aep_reseau` (`id_aep`),
  CONSTRAINT `fk_aep_reseau` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=latin1;

-- Données de `reseau`

-- -----------------------------
-- Structure de la table `ressources_humaines`
-- -----------------------------
DROP TABLE IF EXISTS `ressources_humaines`;
CREATE TABLE `ressources_humaines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aep_id` int(11) NOT NULL,
  `nom` varchar(150) NOT NULL,
  `fonction` varchar(120) DEFAULT NULL,
  `competences` text,
  `telephone` varchar(50) DEFAULT NULL,
  `statut` enum('disponible','occupe','indisponible') DEFAULT 'disponible',
  `cout_horaire` decimal(12,2) DEFAULT '0.00',
  `actif` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rh_aep` (`aep_id`),
  KEY `idx_rh_statut` (`statut`),
  KEY `idx_rh_actif` (`actif`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- Données de `ressources_humaines`
INSERT INTO `ressources_humaines` (`id`,`aep_id`,`nom`,`fonction`,`competences`,`telephone`,`statut`,`cout_horaire`,`actif`,`created_at`,`updated_at`) VALUES ('1','1','Tsafack','TOOOO','4152155','68547895214','disponible','0.00','1',NULL,NULL);


-- -----------------------------
-- Structure de la table `ressources_materielles`
-- -----------------------------
DROP TABLE IF EXISTS `ressources_materielles`;
CREATE TABLE `ressources_materielles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aep_id` int(11) NOT NULL,
  `libelle` varchar(180) NOT NULL,
  `categorie` varchar(120) DEFAULT NULL,
  `reference` varchar(120) DEFAULT NULL,
  `quantite_totale` decimal(12,2) DEFAULT '0.00',
  `quantite_disponible` decimal(12,2) DEFAULT '0.00',
  `unite` varchar(30) DEFAULT 'u',
  `cout_unitaire` decimal(12,2) DEFAULT '0.00',
  `statut` enum('disponible','occupe','panne','hors_service') DEFAULT 'disponible',
  `actif` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rm_aep` (`aep_id`),
  KEY `idx_rm_statut` (`statut`),
  KEY `idx_rm_categorie` (`categorie`),
  KEY `idx_rm_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Données de `ressources_materielles`

-- -----------------------------
-- Structure de la table `roles`
-- -----------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=latin1;

-- Données de `roles`
INSERT INTO `roles` (`id`,`nom`) VALUES ('1','Administrateur'),('8','Releveur'),('9','Comptable'),('10','Recouvreur'),('11','Visiteur'),('12','all');


-- -----------------------------
-- Structure de la table `travail`
-- -----------------------------
DROP TABLE IF EXISTS `travail`;
CREATE TABLE `travail` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `id_aep` int(4) unsigned NOT NULL,
  `id_utilisateur` int(4) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_aep_travail` (`id_aep`),
  KEY `fk_utilisateur_travail` (`id_utilisateur`),
  CONSTRAINT `fk_aep_travail` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`),
  CONSTRAINT `fk_utilisateur_travail` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Données de `travail`

-- -----------------------------
-- Structure de la table `user_clefs`
-- -----------------------------
DROP TABLE IF EXISTS `user_clefs`;
CREATE TABLE `user_clefs` (
  `clef_id` int(3) unsigned NOT NULL,
  `user_id` int(3) unsigned NOT NULL,
  PRIMARY KEY (`clef_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Données de `user_clefs`
INSERT INTO `user_clefs` (`clef_id`,`user_id`) VALUES ('3','8'),('2','9'),('4','10'),('10','11'),('11','12'),('6','13'),('5','14'),('12','15'),('9','16');


-- -----------------------------
-- Structure de la table `user_roles`
-- -----------------------------
DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL DEFAULT '0',
  `role_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `role_id` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Données de `user_roles`
INSERT INTO `user_roles` (`user_id`,`role_id`) VALUES ('1','1'),('7','1'),('8','8'),('9','8'),('9','10'),('10','9'),('10','11'),('11','8'),('11','9'),('11','10'),('11','11'),('12','1'),('12','8'),('12','11'),('12','12'),('13','1'),('13','11'),('14','1'),('14','11'),('15','11'),('16','1'),('16','11');


-- -----------------------------
-- Structure de la table `users`
-- -----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(32) NOT NULL,
  `nom` varchar(32) NOT NULL,
  `prenom` varchar(32) NOT NULL,
  `numero_telephone` varchar(16) NOT NULL,
  `password` varchar(64) DEFAULT NULL,
  `salt` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=17 DEFAULT CHARSET=latin1;

-- Données de `users`
INSERT INTO `users` (`id`,`email`,`nom`,`prenom`,`numero_telephone`,`password`,`salt`) VALUES ('1','sd@gmail.com','slks aslk','M','654152545','ef797c8118f02dfb',''),('2','tockemapplications@gmail.com','Tsafack','Erick','654190514','ef797c8118f02dfb',''),('3','tockemapplications2@gmail.com','sncdsl;n','sldnlds','25412545','e150f4455ed28e8c',''),('4','tockemapplications3@gmail.com','sksk','sksk','6565656565','aa005661a830c458','ae8604e73a48e7022190406c201ca358'),('5','tockemapplications5@gmail.com','olol','lolo','653636363','f916e27059dc0b41','5bbb445a4f503f8234ecbe37903f0c24'),('6','tockemapplications6@gmail.com','opopo','poioi','658585959','3270175410fe1cc8ee5633e04a14fcad56ee04bbaa5329604385bed55fe1098d','15f7ce4388a49a9be16cf70015ec2f9e'),('7','tockemapplications0@gmail.com','Takam Ulrich','Erick','6565656565','1f3b595625daad307953bb1153055ef9d28ffa2bbde1dc384c6d109fca6d552a','7a85e01b353c290533d2deda2c84805a'),('8','ericktsafack@gmail.com','Tsafack','Erick','654190514','fb259da39527c902bcbd1161b579ab84cb416e59208f637819e72f30e11751e1','2abf440fceec6545a861fd6a3b52a819'),('9','bonjour@gmail.com','Mezankeu','Darelle','658595653','66b69e627ba0867e07b680bdc8966ded1c0d97edc8e079cc3a1ca6936b4a7cee','249324c1cb4a556af910a1d2ef1eb9e2'),('10','tockemapplications10@gmail.com','platon','juju','698977445','e7c90bb79468a7b6a01ce361628c0ceae4365071a7dace2808ef5aea8532c683','cdb3babd4215c8db8f9abd2b7c4e65fd'),('11','testblanche@gmail.com','Test Momo','blanche','677565453','285af19d2e8f258a544d15781bb5312a3c336d5695533044c6178bd5a5f867a2','1d395a8551f727d8d1c09cbf7a7c8349'),('12','martialtsobeng@gmail.com','TSOBENG','Martial','697621294','789473a2831b79cdcc5450d2088b0092660cf3e116a1d2e46578bf842dbba545','0e8d639eb5510f72802da07d09dec59c'),('13','blanchemomo1991@gmail.com','MOMO TSOPFACK','Blanche','698193280','e1302e2f72b5f2a2011f8604f32384dfd11616a54be08552d164abe3e4b666ce','6ea512ef0586ad887caeca3174932e5f'),('14','mcnyama@gmail.com','NDZOU','Lewis','670029033','cfb4ed94663bd191622f65828fc638c9948affd1edb0490c7583fe3a38fefcd3','e0e091a6b29dc541a628e4368cc999aa'),('15','tockemapplicationsp@gmail.com','toto','Eric','654190514','7f595eb4847906a7209df1803231cc70d8ed502492f99a8ae2603922c8cf89c4','8040d34cb3038874663428a566ea383b'),('16','mabamomoachille@gmail.com','maba momo','achille','690409882','36ca59f93c6f45940d6fd749e022f44f22f5979153d54becfc609c92d389bac1','f6d7932db35bd7a2637f9ca93516d084');


-- -----------------------------
-- Structure de la table `utilisateur`
-- -----------------------------
DROP TABLE IF EXISTS `utilisateur`;
CREATE TABLE `utilisateur` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(32) NOT NULL,
  `nom` varchar(32) NOT NULL,
  `prenom` varchar(32) NOT NULL,
  `numero_telephone` varchar(16) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Données de `utilisateur`

-- -----------------------------
-- Structure de la table `versements`
-- -----------------------------
DROP TABLE IF EXISTS `versements`;
CREATE TABLE `versements` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `montant` decimal(15,2) NOT NULL,
  `date_versement` date NOT NULL,
  `id_mois_facturation` int(4) unsigned NOT NULL,
  `id_redevance` int(4) unsigned NOT NULL,
  `est_valide` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `id_mois_facturation` (`id_mois_facturation`),
  KEY `id_redevance` (`id_redevance`)
) ENGINE=MyISAM AUTO_INCREMENT=38 DEFAULT CHARSET=latin1;

-- Données de `versements`
SET FOREIGN_KEY_CHECKS=1;
