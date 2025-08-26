<?php
@include_once '..donnees/Reseau2.php';
@include_once '/donnees/Reseau2.php';


$host = 'localhost';
$dbname = 'suivi_aep_fokoue';
$username = 'root';
$password = '';



try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

function getAllReseaux() {
    global $pdo;
    $reseaux = array();
    $stmt = $pdo->query("SELECT * FROM reseau WHERE id_aep = " . $_SESSION['id_aep']);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $reseaux[] = new Reseau(
            $row['id'],
            $row['nom'],
            $row['abreviation'],
            $row['date_creation'],
            $row['description_reseau'],
            $row['id_aep']
        );
    }
    return $reseaux;
}

function getReseauById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM reseau WHERE id = ? AND id_aep = ?");
    $stmt->execute(array($id, $_SESSION['id_aep']));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return new Reseau(
            $row['id'],
            $row['nom'],
            $row['abreviation'],
            $row['date_creation'],
            $row['description_reseau'],
            $row['id_aep']
        );
    }
    return null;
}

function addReseau($nom, $abreviation, $date_creation, $description_reseau) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO reseau (nom, abreviation, date_creation, description_reseau, id_aep) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(array($nom, $abreviation, $date_creation, $description_reseau, $_SESSION['id_aep']));
    return $pdo->lastInsertId();
}

function updateReseau($id, $nom, $abreviation, $date_creation, $description_reseau) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE reseau SET nom = ?, abreviation = ?, date_creation = ?, description_reseau = ? WHERE id = ? AND id_aep = ?");
    $stmt->execute(array($nom, $abreviation, $date_creation, $description_reseau, $id, $_SESSION['id_aep']));
}

function deleteReseau($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM reseau WHERE id = ? AND id_aep = ?");
    $stmt->execute(array($id, $_SESSION['id_aep']));
}

// Nouvelles fonctions pour la logique métier
function getTotalAbonnesByReseau($id_reseau) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM abone WHERE id_reseau = ?");
    $stmt->execute(array($id_reseau));
    return $stmt->fetchColumn();
}

function getActiveAbonnesByReseau($id_reseau) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM abone WHERE id_reseau = ? AND etat = 'actif'");
    $stmt->execute(array($id_reseau));
    return $stmt->fetchColumn();
}

function getInactiveAbonnesByReseau($id_reseau) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM abone WHERE id_reseau = ? AND etat != 'actif'");
    $stmt->execute(array($id_reseau));
    return $stmt->fetchColumn();
}

function getFactureCountByReseau($id_reseau) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT f.id_abone) FROM facture f 
                          JOIN abone a ON f.id_abone = a.id 
                          WHERE a.id_reseau = ?");
    $stmt->execute(array($id_reseau));
    return $stmt->fetchColumn();
}

function getRecouvrementRateByReseau($id_reseau) {
    global $pdo;
    // Total facturé
    $stmt = $pdo->prepare("SELECT SUM(f.montant_verse) FROM facture f 
                          JOIN abone a ON f.id_abone = a.id 
                          WHERE a.id_reseau = ? AND f.date_paiement IS NOT NULL");
    $stmt->execute(array($id_reseau));
    $totalRecovered = $stmt->fetchColumn() ?: 0;

    // Total dû
    $stmt = $pdo->prepare("SELECT SUM(f.montant_verse) FROM facture f 
                          JOIN abone a ON f.id_abone = a.id 
                          WHERE a.id_reseau = ?");
    $stmt->execute(array($id_reseau));
    $totalBilled = $stmt->fetchColumn() ?: 1; // Éviter division par zéro

    return $totalBilled > 0 ? ($totalRecovered / $totalBilled) * 100 : 0;
}

function getCompteurCountByReseau($id_reseau) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM compteur_reseau WHERE id_reseau = ?");
    $stmt->execute(array($id_reseau));
    return $stmt->fetchColumn();
}
?>