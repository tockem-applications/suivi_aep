<?php
/**
 * Jeu de données fictif pour les démonstrations de rendement de réseau.
 *
 * Crée un AEP de démonstration complet (réseau principal en RDS avec compteurs
 * de production, de réservoir et de distribution, trois antennes équipées
 * chacune d'un compteur de distribution, une soixantaine d'abonnés BP/BF) et
 * douze mois de relevés et de factures cohérents entre eux.
 *
 * Le scénario est celui d'une publication sur l'eau non facturée : le réseau
 * produit environ 1 000 m³ par mois, l'antenne Sud décroche à partir de mars
 * 2026 (fuite), les deux autres restent stables. Les rendements par étage et
 * par antenne se lisent dans « Réseaux » → détail du réseau principal.
 *
 * Toutes les personnes, lieux et numéros sont inventés.
 *
 * Usage (depuis la racine du projet) :
 *   php utils/seed_demo_rendement.php            crée l'AEP « Ndzem-Lah (démo) »
 *   php utils/seed_demo_rendement.php --supprimer  supprime cet AEP et ses données
 *
 * Le script refuse de créer deux fois le même AEP.
 */

if (php_sapi_name() !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    echo "Script en ligne de commande uniquement.\n";
    exit;
}

chdir(dirname(__FILE__) . '/..');
require_once 'donnees/manager.php';

$LIBELE_AEP = 'Ndzem-Lah (démo)';
$PREMIER_MOIS = '2025-09';
$NB_MOIS = 12;
mt_srand(20260913);

// ---------------------------------------------------------------------------
// Suppression
// ---------------------------------------------------------------------------
$idAepExistant = Manager::prepare_query("SELECT id FROM aep WHERE libele = ?", array($LIBELE_AEP))->fetchColumn();

if (in_array('--supprimer', $argv, true)) {
    if (!$idAepExistant) {
        echo "Aucun AEP « $LIBELE_AEP » à supprimer.\n";
        exit;
    }
    $idAep = (int) $idAepExistant;
    $reseaux = Manager::prepare_query("SELECT id FROM reseau WHERE id_aep = ?", array($idAep))->fetchAll(PDO::FETCH_COLUMN);
    $mois = Manager::prepare_query(
        "SELECT m.id FROM mois_facturation m INNER JOIN constante_reseau c ON c.id = m.id_constante WHERE c.id_aep = ?",
        array($idAep)
    )->fetchAll(PDO::FETCH_COLUMN);
    $compteurs = array();
    foreach ($reseaux as $idReseau) {
        $compteurs = array_merge(
            $compteurs,
            Manager::prepare_query("SELECT id_compteur FROM compteur_reseau WHERE id_reseau = ?", array($idReseau))->fetchAll(PDO::FETCH_COLUMN),
            Manager::prepare_query(
                "SELECT ca.id_compteur FROM compteur_abone ca INNER JOIN abone a ON a.id = ca.id_abone WHERE a.id_reseau = ?",
                array($idReseau)
            )->fetchAll(PDO::FETCH_COLUMN)
        );
    }
    foreach ($mois as $idMois) {
        Manager::prepare_query(
            "DELETE f FROM facture f INNER JOIN indexes i ON i.id = f.id_indexes WHERE i.id_mois_facturation = ?",
            array($idMois)
        );
        Manager::prepare_query("DELETE FROM indexes WHERE id_mois_facturation = ?", array($idMois));
        Manager::prepare_query("DELETE FROM mois_facturation WHERE id = ?", array($idMois));
    }
    foreach ($reseaux as $idReseau) {
        $abones = Manager::prepare_query("SELECT id FROM abone WHERE id_reseau = ?", array($idReseau))->fetchAll(PDO::FETCH_COLUMN);
        foreach ($abones as $idAbone) {
            Manager::prepare_query("DELETE FROM borne_fontaine WHERE id_abone = ?", array($idAbone));
            Manager::prepare_query("DELETE FROM compteur_abone WHERE id_abone = ?", array($idAbone));
            Manager::prepare_query("DELETE FROM facture WHERE id_abone = ?", array($idAbone));
        }
        Manager::prepare_query("DELETE FROM abone WHERE id_reseau = ?", array($idReseau));
        Manager::prepare_query("DELETE FROM compteur_reseau WHERE id_reseau = ?", array($idReseau));
    }
    // Les antennes référencent le réseau principal : elles partent d'abord.
    Manager::prepare_query("DELETE FROM reseau WHERE id_aep = ? AND id_reseau_parent IS NOT NULL", array($idAep));
    Manager::prepare_query("DELETE FROM reseau WHERE id_aep = ?", array($idAep));
    foreach (array_unique(array_map('intval', $compteurs)) as $idCompteur) {
        Manager::prepare_query("DELETE FROM indexes WHERE id_compteur = ?", array($idCompteur));
        Manager::prepare_query("DELETE FROM compteur WHERE id = ?", array($idCompteur));
    }
    Manager::prepare_query("DELETE FROM constante_reseau WHERE id_aep = ?", array($idAep));
    Manager::prepare_query("DELETE FROM aep WHERE id = ?", array($idAep));
    echo "AEP « $LIBELE_AEP » supprimé (" . count($reseaux) . " réseaux, " . count($mois) . " mois, " . count($compteurs) . " compteurs).\n";
    exit;
}

if ($idAepExistant) {
    echo "L'AEP « $LIBELE_AEP » existe déjà (id $idAepExistant). Lancez avec --supprimer pour le recréer.\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// Scénario
// ---------------------------------------------------------------------------

/** Volume produit au captage chaque mois, en m³ (légère saisonnalité). */
function demo_production($indexMois)
{
    $base = array(960, 985, 1010, 1040, 1065, 1050, 1020, 995, 1000, 1010, 990, 1000);
    return $base[$indexMois % 12];
}

/**
 * Rendement de chaque antenne (volume abonnés / volume au compteur de
 * l'antenne) selon le mois : Sud décroche à partir de mars 2026 (mois n° 6).
 */
function demo_rendement_antenne($cle, $indexMois)
{
    $stable = array('nord' => 0.84, 'centre' => 0.76, 'sud' => 0.84);
    if ($cle === 'sud' && $indexMois >= 6) {
        $chute = array(6 => 0.70, 7 => 0.58, 8 => 0.50, 9 => 0.45, 10 => 0.42, 11 => 0.40);
        return $chute[$indexMois];
    }
    return $stable[$cle] + (mt_rand(-15, 15) / 1000);
}

/** Répartit un volume entier entre des poids, en conservant exactement le total. */
function demo_repartir($total, array $poids)
{
    $somme = array_sum($poids);
    $parts = array();
    $reste = $total;
    $n = count($poids);
    $i = 0;
    foreach ($poids as $cle => $p) {
        $i++;
        if ($i === $n) {
            $parts[$cle] = $reste;
        } else {
            $v = (int) round($total * $p / $somme);
            $parts[$cle] = $v;
            $reste -= $v;
        }
    }
    return $parts;
}

function demo_mois($premier, $offset)
{
    $d = DateTime::createFromFormat('Y-m-d', $premier . '-01');
    $d->modify('+' . $offset . ' month');
    return $d;
}

$prenoms = array('Jean', 'Pauline', 'Marcel', 'Estelle', 'Bernard', 'Rosine', 'Clément', 'Odile', 'Alain', 'Solange',
    'Émile', 'Viviane', 'Lucien', 'Béatrice', 'Gilbert', 'Sylvie', 'Roger', 'Mireille', 'Joseph', 'Nadège',
    'Paul', 'Henriette', 'Samuel', 'Christine', 'Michel', 'Georgette', 'Thomas', 'Adèle', 'Pierre', 'Yvette');
$noms = array('TCHOUMI', 'NGUEMO', 'FOTSO', 'KAMDEM', 'DJOUMESSI', 'TAGNE', 'NANA', 'MBIANDA', 'FEUDJIO', 'KEMAJOU',
    'TEMGOUA', 'NGANSOP', 'DONFACK', 'TSOPMO', 'WAFO', 'KENFACK', 'MOMO', 'ZEBAZE', 'DJUIKOM', 'SIMO',
    'NGATCHOU', 'POKAM', 'FOKOU', 'YIMGA', 'TEDONGMO', 'TIENTCHEU', 'KOUAM', 'WAMBO', 'NGOUFACK', 'NOUBISSI',
    'SOP', 'TENE', 'KAPTUE', 'MOUAFO', 'FOMEKONG', 'DOUANLA', 'NGUIMFACK', 'TALLA', 'KEUGNI', 'DEMANOU');

$antennes = array(
    'nord' => array('nom' => 'Antenne Nord (Chefferie)', 'abrev' => 'AN', 'part' => 0.30, 'nb_bp' => 17, 'bf' => array('BF Chefferie'),
        'lat' => 5.4712, 'lon' => 10.1120),
    'centre' => array('nom' => 'Antenne Centre (Marché)', 'abrev' => 'AC', 'part' => 0.40, 'nb_bp' => 22, 'bf' => array('BF Marché', 'BF École publique'),
        'lat' => 5.4650, 'lon' => 10.1085),
    'sud' => array('nom' => 'Antenne Sud (Ndzem-Bas)', 'abrev' => 'AS', 'part' => 0.30, 'nb_bp' => 18, 'bf' => array('BF Ndzem-Bas'),
        'lat' => 5.4581, 'lon' => 10.1102),
);

$bd = Manager::getBdd();
if ($bd === null) {
    Manager::prepare_query('SELECT 1', array());
    $bd = Manager::getBdd();
}
$bd->beginTransaction();
try {
    // --- AEP, tarif, réseau principal et antennes
    Manager::prepare_query(
        "INSERT INTO aep (libele, description, date, numero_compte, nom_banque, type_distribution)
         VALUES (?, ?, ?, ?, ?, 'RDS')",
        array(
            $LIBELE_AEP,
            "Réseau de démonstration (données fictives) : captage gravitaire, réservoir de 150 m³, trois antennes de distribution.",
            '2019-03-15',
            '10005-00021-73648152-11',
            'Crédit Communautaire',
        )
    );
    $idAep = (int) $bd->lastInsertId();

    Manager::prepare_query(
        "INSERT INTO constante_reseau (prix_metre_cube_eau, prix_entretient_compteur, prix_tva, date_creation, est_actif, description, id_aep)
         VALUES (350, 500, 0, ?, 1, 'Tarif unique 350 F/m³, entretien compteur 500 F/mois', ?)",
        array($PREMIER_MOIS . '-01', $idAep)
    );
    $idConstante = (int) $bd->lastInsertId();

    Manager::prepare_query(
        "INSERT INTO reseau (nom, abreviation, date_creation, description_reseau, id_aep, id_reseau_parent)
         VALUES ('Réseau principal Ndzem-Lah', 'RP', '2019-03-15', 'Captage, conduite d\\'adduction, réservoir et départ de distribution', ?, NULL)",
        array($idAep)
    );
    $idReseauPrincipal = (int) $bd->lastInsertId();

    $compteursReseau = array();
    $defsCompteurs = array(
        array('num' => 'NDZ-PROD-01', 'type' => 'production', 'reseau' => $idReseauPrincipal, 'desc' => 'Compteur de captage (source de Lah)', 'lat' => 5.4790, 'lon' => 10.1010),
        array('num' => 'NDZ-RESV-01', 'type' => 'reservoir', 'reseau' => $idReseauPrincipal, 'desc' => 'Compteur d\'arrivée au réservoir de 150 m³', 'lat' => 5.4748, 'lon' => 10.1071),
        array('num' => 'NDZ-DIST-00', 'type' => 'distribution', 'reseau' => $idReseauPrincipal, 'desc' => 'Compteur de départ de distribution (sortie réservoir)', 'lat' => 5.4745, 'lon' => 10.1074),
    );
    $idsAntennes = array();
    foreach ($antennes as $cle => $a) {
        Manager::prepare_query(
            "INSERT INTO reseau (nom, abreviation, date_creation, description_reseau, id_aep, id_reseau_parent)
             VALUES (?, ?, '2019-06-01', ?, ?, ?)",
            array($a['nom'], $a['abrev'], 'Antenne de distribution — ' . $a['nom'], $idAep, $idReseauPrincipal)
        );
        $idsAntennes[$cle] = (int) $bd->lastInsertId();
        $defsCompteurs[] = array(
            'num' => 'NDZ-DIST-' . strtoupper(substr($cle, 0, 1)) . '1',
            'type' => 'distribution',
            'reseau' => $idsAntennes[$cle],
            'desc' => 'Compteur de tête — ' . $a['nom'],
            'lat' => $a['lat'] + 0.004,
            'lon' => $a['lon'],
        );
    }
    foreach ($defsCompteurs as $c) {
        Manager::prepare_query(
            "INSERT INTO compteur (numero_compteur, longitude, latitude, derniers_index, description) VALUES (?, ?, ?, 0, ?)",
            array($c['num'], $c['lon'], $c['lat'], $c['desc'])
        );
        $idCompteur = (int) $bd->lastInsertId();
        Manager::prepare_query(
            "INSERT INTO compteur_reseau (id_reseau, id_compteur, type_compteur) VALUES (?, ?, ?)",
            array($c['reseau'], $idCompteur, $c['type'])
        );
        $compteursReseau[$c['num']] = array('id' => $idCompteur, 'index' => (float) mt_rand(12000, 48000));
    }

    // --- Abonnés
    $abones = array(); // liste de : id, id_compteur, cle antenne, poids, type, index courant
    $rang = 0;
    $numeroCompteur = 240001;
    $utilises = array();
    foreach ($antennes as $cle => $a) {
        $liste = array();
        for ($i = 0; $i < $a['nb_bp']; $i++) {
            do {
                $nom = $noms[mt_rand(0, count($noms) - 1)] . ' ' . $prenoms[mt_rand(0, count($prenoms) - 1)];
            } while (isset($utilises[$nom]));
            $utilises[$nom] = true;
            // Un ménage consomme 4 à 25 m³ ; quelques gros consommateurs.
            $poids = mt_rand(4, 25);
            if (mt_rand(1, 10) === 1) {
                $poids += mt_rand(10, 25);
            }
            $liste[] = array('nom' => $nom, 'type' => 'BP', 'poids' => $poids);
        }
        foreach ($a['bf'] as $nomBf) {
            $liste[] = array('nom' => $nomBf, 'type' => 'BF', 'poids' => mt_rand(35, 60));
        }
        foreach ($liste as $ab) {
            $rang++;
            $tel = '6' . mt_rand(5, 9) . str_pad((string) mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
            Manager::prepare_query(
                "INSERT INTO abone (nom, numero_telephone, numero_compte_anticipation, etat, rang, id_reseau, type_abone, tarif_differencie_autorise)
                 VALUES (?, ?, '', 'actif', ?, ?, ?, 1)",
                array($ab['nom'], $tel, $rang, $idsAntennes[$cle], $ab['type'])
            );
            $idAbone = (int) $bd->lastInsertId();
            $numeroCompteur++;
            Manager::prepare_query(
                "INSERT INTO compteur (numero_compteur, longitude, latitude, derniers_index, description) VALUES (?, ?, ?, 0, '')",
                array(
                    'C' . $numeroCompteur,
                    $a['lon'] + mt_rand(-40, 40) / 10000,
                    $a['lat'] + mt_rand(-40, 40) / 10000,
                )
            );
            $idCompteur = (int) $bd->lastInsertId();
            Manager::prepare_query("INSERT INTO compteur_abone (id_abone, id_compteur) VALUES (?, ?)", array($idAbone, $idCompteur));
            if ($ab['type'] === 'BF') {
                Manager::prepare_query(
                    "INSERT INTO borne_fontaine (id_abone, numero_borne, localisation, date_creation, description)
                     VALUES (?, ?, ?, NOW(), 'Borne fontaine publique gérée par un fontainier')",
                    array($idAbone, 'BF-' . str_pad((string) count($abones) + 1, 2, '0', STR_PAD_LEFT), $ab['nom'])
                );
            }
            $abones[] = array(
                'id' => $idAbone,
                'id_compteur' => $idCompteur,
                'antenne' => $cle,
                'poids' => $ab['poids'],
                'type' => $ab['type'],
                'index' => (float) mt_rand(0, 300),
                'payeur' => mt_rand(1, 100), // profil de paiement
            );
        }
    }

    // --- Mois de facturation, relevés et factures
    $recap = array();
    for ($m = 0; $m < $NB_MOIS; $m++) {
        $d = demo_mois($PREMIER_MOIS, $m);
        $mois = $d->format('Y-m');
        $finMois = $d->format('Y-m-t');
        $releve = $d->format('Y-m-') . str_pad((string) mt_rand(26, 28), 2, '0', STR_PAD_LEFT);
        $dSuivant = demo_mois($PREMIER_MOIS, $m + 1);
        $dateFacturation = $dSuivant->format('Y-m-') . '02';
        $dateDepot = $dSuivant->format('Y-m-') . '06';
        $estActif = $m === $NB_MOIS - 1 ? 1 : 0;

        Manager::prepare_query(
            "INSERT INTO mois_facturation (mois, date_facturation, date_depot, id_constante, description, est_actif, date_releve, est_mois_base)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0)",
            array($mois, $dateFacturation, $dateDepot, $idConstante, 'Facturation de ' . getLetterMonth($mois), $estActif, $releve)
        );
        $idMois = (int) $bd->lastInsertId();

        // Volumes par étage : production → réservoir → distribution → antennes.
        $vProd = demo_production($m);
        $vRes = (int) round($vProd * (0.965 + mt_rand(0, 15) / 1000));
        $vDist = (int) round($vRes * (0.982 + mt_rand(0, 8) / 1000));
        // Une petite perte sur la conduite maîtresse avant les têtes d'antenne.
        $vAntennesTotal = (int) round($vDist * (0.968 + mt_rand(0, 8) / 1000));
        $partsAntennes = demo_repartir($vAntennesTotal, array('nord' => 0.30, 'centre' => 0.40, 'sud' => 0.30));

        $volumesReseau = array(
            'NDZ-PROD-01' => $vProd,
            'NDZ-RESV-01' => $vRes,
            'NDZ-DIST-00' => $vDist,
            'NDZ-DIST-N1' => $partsAntennes['nord'],
            'NDZ-DIST-C1' => $partsAntennes['centre'],
            'NDZ-DIST-S1' => $partsAntennes['sud'],
        );
        foreach ($volumesReseau as $num => $vol) {
            $ancien = $compteursReseau[$num]['index'];
            $nouveau = $ancien + $vol;
            Manager::prepare_query(
                "INSERT INTO indexes (id_compteur, id_mois_facturation, ancien_index, nouvel_index, message) VALUES (?, ?, ?, ?, '')",
                array($compteursReseau[$num]['id'], $idMois, $ancien, $nouveau)
            );
            $compteursReseau[$num]['index'] = $nouveau;
        }

        // Volume facturé aux abonnés de chaque antenne, réparti selon les poids.
        $vAbonnes = 0;
        foreach ($antennes as $cle => $a) {
            $volAntenne = (int) round($partsAntennes[$cle] * demo_rendement_antenne($cle, $m));
            $poids = array();
            foreach ($abones as $k => $ab) {
                if ($ab['antenne'] === $cle) {
                    // Variation mensuelle de ±30 % autour du profil de l'abonné.
                    $poids[$k] = max(0, $ab['poids'] * (0.7 + mt_rand(0, 60) / 100));
                }
            }
            $parts = demo_repartir($volAntenne, $poids);
            foreach ($parts as $k => $conso) {
                $conso = max(0, (int) $conso);
                $ancien = $abones[$k]['index'];
                $nouveau = $ancien + $conso;
                Manager::prepare_query(
                    "INSERT INTO indexes (id_compteur, id_mois_facturation, ancien_index, nouvel_index, message) VALUES (?, ?, ?, ?, '')",
                    array($abones[$k]['id_compteur'], $idMois, $ancien, $nouveau)
                );
                $idIndex = (int) $bd->lastInsertId();
                $abones[$k]['index'] = $nouveau;
                $vAbonnes += $conso;

                // Facture : 350 F/m³ + 500 F d'entretien. Trois profils de payeurs.
                $montant = $conso * 350 + 500;
                $profil = $abones[$k]['payeur'];
                if ($m === $NB_MOIS - 1) {
                    // Mois en cours : factures tout juste éditées, rien d'encaissé.
                    $verse = 0;
                    $datePaiement = null;
                } elseif ($profil <= 70) {
                    $verse = $montant;
                    $datePaiement = $dSuivant->format('Y-m-') . str_pad((string) mt_rand(6, 25), 2, '0', STR_PAD_LEFT);
                } elseif ($profil <= 88) {
                    $verse = mt_rand(1, 3) === 1 ? 0 : $montant;
                    $datePaiement = $verse > 0 ? $dSuivant->format('Y-m-') . str_pad((string) mt_rand(10, 28), 2, '0', STR_PAD_LEFT) : null;
                } else {
                    $verse = mt_rand(1, 4) === 1 ? $montant : 0;
                    $datePaiement = $verse > 0 ? $dSuivant->format('Y-m-') . '28' : null;
                }
                Manager::prepare_query(
                    "INSERT INTO facture (id_indexes, montant_verse, date_paiement, penalite, id_abone, message) VALUES (?, ?, ?, 0, ?, '')",
                    array($idIndex, $verse, $datePaiement, $abones[$k]['id'])
                );
            }
        }
        $recap[] = array($mois, $vProd, $vRes, $vDist, $partsAntennes, $vAbonnes);
    }

    // Derniers index des compteurs : ce que lira le prochain mois.
    foreach ($compteursReseau as $c) {
        Manager::prepare_query("UPDATE compteur SET derniers_index = ? WHERE id = ?", array($c['index'], $c['id']));
    }
    foreach ($abones as $ab) {
        Manager::prepare_query("UPDATE compteur SET derniers_index = ? WHERE id = ?", array($ab['index'], $ab['id_compteur']));
    }

    $bd->commit();
} catch (Exception $e) {
    $bd->rollBack();
    echo "Échec, rien n'a été écrit : " . $e->getMessage() . "\n";
    exit(1);
}

echo "AEP « $LIBELE_AEP » créé (id $idAep) : 1 réseau principal, " . count($antennes) . " antennes, " . count($abones) . " abonnés, $NB_MOIS mois.\n\n";
printf("%-8s %6s %6s %6s | %5s %5s %5s | %8s %6s\n", 'Mois', 'Prod', 'Réserv', 'Distr', 'Nord', 'Centre', 'Sud', 'Abonnés', 'Rdt');
foreach ($recap as $r) {
    printf(
        "%-8s %6d %6d %6d | %5d %5d %5d | %8d %5.0f%%\n",
        $r[0], $r[1], $r[2], $r[3], $r[4]['nord'], $r[4]['centre'], $r[4]['sud'], $r[5], 100 * $r[5] / $r[1]
    );
}
echo "\nOuvrez l'application, sélectionnez l'AEP puis « Réseaux » → « Réseau principal Ndzem-Lah » pour les rendements.\n";
