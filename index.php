<?php include_once("donnees/manager.php")?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="presentation/assets/images/favicon.png">
    <link rel="stylesheet" href="presentation/style.css">
    <link rel="stylesheet" href="presentation/assets/css/recouvrement.css">
    <link rel="stylesheet" href="presentation/bootstrap/css/bootstrap.css">
    <link rel="stylesheet" href="presentation/assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="presentation/assets/bootstrap-icons-1.11.3/font/bootstrap-icons.min.css">

    <script src="presentation/assets/js/jsdelivr/chart.js"></script>
    <!-- <link rel="stylesheet" href="presentation/assets/css/styles.css"> -->

    <!-- <script src="presentation/js.js"></script> -->

    <script>
        var addresse_serveur_reseau = '<?php echo ($_SERVER['SERVER_ADDR']=="127.0.0.1"?"localhost":$_SERVER['SERVER_ADDR']).str_replace("/index.php", "", $_SERVER['PHP_SELF'])?>';
    </script>
    <script src="presentation/assets/@canvasjs/charts/canvasjs.min.js"></script>
    <script src="presentation/assets/jquery.js"></script>
    <script src="presentation/assets/js/bs-init.js"></script>
    <script src="presentation/assets/js/javascript.js"></script>

    <script src="presentation/js.js"></script>
    <script src="presentation/assets/jquery.js"></script>
    <!--<script src="presentation/bootstrap/js/bootstrap.js"></script>-->
    <script src="presentation/assets/bootstrap/js/bootstrap.min.js"></script>
    <script src="presentation/assets/js/download_csv.js"></script>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">


    <!-- Tailwind CSS CDN -->
<!--    <script src="https://cdn.tailwindcss.com"></script>-->
    <!-- Chart.js CDN -->
<!--    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>-->
    <!-- Font Awesome CDN pour les icônes -->
<!--    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">-->
    <title><?php echo isset($_SESSION['libele_aep'])? $_SESSION['libele_aep']: 'Tockem'?> SPE</title>

    <style>
        body {
            /*background-color: #f8f9fa;*/
        }

        @media print {
            body {
                background-color: white;
            }
        }

        .card-hover:hover {
            background-color: rgba(0, 0, 0, 0.1); /* Ombre légère */
        }

        .hero {
            background: url('https://source.unsplash.com/1600x900/?water') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 100px 0;
            text-align: center;
        }

        .feature {
            text-align: center;
            margin-bottom: 30px;
        }

        /* Pour les navigateurs basés sur WebKit (Chrome, Safari) */
        ::-webkit-scrollbar {
            width: 8px; /* Largeur de la barre de défilement */
        }

        ::-webkit-scrollbar-track {
            /*background: #f1f1f1; !* Couleur de l'arrière-plan de la barre de défilement *!*/
            border-radius: 5px; /* Coins arrondis */
        }

        ::-webkit-scrollbar-thumb {
            background: darkgrey; /* Couleur de la barre de défilement */
            border-radius: 10px; /* Coins arrondis */
        }

        ::-webkit-scrollbar-thumb:hover {
            background: grey; /* Couleur lorsque la souris survole */
        }

        .fixed-div {
            /*width: 100%; !* Prend toute la largeur sur mobile *!*/
            height: 92vh; /* Hauteur automatique pour le contenu */
        }

        .sous-header {
            min-height: 10vh
        }

        /* Styles pour les écrans non mobiles */
        @media (max-width: 550px) {
            /* 768px est un point de rupture commun pour les tablettes */
            .fixed-div {
                overflow-y: auto;
                /*width: 600px; !* Fixe la largeur à 600px *!*/
                height: 50vh; /* Fixe la hauteur à 400px */
            }

            .sous-header {
                min-height: 15vh
            }
        }

        @media (max-width: 768px) {
            /* 768px est un point de rupture commun pour les tablettes */
            .fixed-div {
                overflow-y: auto;
                /*width: 600px; !* Fixe la largeur à 600px *!*/
                height: 40vh; /* Fixe la hauteur à 400px */
            }

            .sous-header {
                min-height: 10vh
            }

        }

        .container2 {
            display: flex;
            flex-direction: column;
            min-height: 80vh;
        }

        .content {
            flex: 1;
        }


        .card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .form-section {
            margin-bottom: 1.5rem;
        }

        .model-option {
            cursor: pointer;
            transition: transform 0.2s, border-color 0.3s;
            border: 2px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
        }

        .model-option:hover {
            transform: scale(1.05);
            border-color: #0d6efd;
        }

        .model-option.selected {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }

        .model-option img {
            max-width: 100%;
            height: auto;
            border-radius: 5px;
        }

        .preview-container {
            margin-top: 20px;
            text-align: center;
        }

        .preview-container img {
            max-width: 80%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .error-message {
            color: #dc3545;
            font-size: 0.875rem;
            display: none;
        }

        .is-invalid + .error-message {
            display: block;
        }

        @media (max-width: 576px) {
            .card {
                margin: 10px;
            }

            .model-option {
                margin-bottom: 15px;
            }
        }


        .table-responsive {
            margin-top: 20px;
        }

        .action-btn {
            margin-right: 5px;
        }

        .modal-body img {
            max-width: 100%;
            height: auto;
            border-radius: 5px;
        }

        .truncate {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media (max-width: 576px) {
            .truncate {
                max-width: 100px;
            }

            .action-btn {
                margin-bottom: 5px;
            }
        }

        .table-responsive {
            margin-top: 20px;
        }

        .action-btn {
            margin-right: 5px;
        }

        @media (max-width: 576px) {
            .action-btn {
                margin-bottom: 5px;
            }
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .table-responsive {
            margin-top: 20px;
        }

        .action-btn {
            margin-right: 5px;
        }

        @media (max-width: 576px) {
            .action-btn {
                margin-bottom: 5px;
            }
        }


        .color-circle {
            display: inline-block;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            margin-right: 5px;
        }
        /* Style du bouton d'exportation */
        .export-button {
            position: fixed;
            top: 100px;
            right: 20px;
            background-color: #28a745; /* Vert pour une apparence moderne */
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 50%;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px; /* Espace entre l'icône et le texte */
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s, transform 0.2s;
            z-index: 1000; /* S'assurer que le bouton est au-dessus des autres éléments */
        }

        .export-button:hover {
            background-color: #218838; /* Couleur plus foncée au survol */
            transform: translateY(-2px); /* Léger effet de soulèvement */
        }

        .export-button:active {
            transform: translateY(0); /* Retour à la position initiale au clic */
        }

        .export-button i {
            font-size: 18px; /* Taille de l'icône */
        }


    </style>
</head>
<body class="bg-white h-100 flex-column overflow-y-hidden" style="background-color: rgba(220, 220, 220, 1);">
<?php
setlocale(LC_TIME, 'fr_FR.UTF-8');
//var_dump($_SERVER);

include_once("presentation/aep_components.php");
include_once ("traitement/aep_t.php");
include_once("presentation/header.php");
?>

<!--<div class="sous-header"></div>-->
<main class="bg-white overflow-y-scroll" style="flex: 1;height: 90vh">
    <!-- <aside class="left_side_body" style="border-right: solid white 1px;">
        <img src="presentation/assets/images/aside_image.png" alt="tockem image" style="width: 100%; height: 50%;">
    </aside> -->
    <article class="rigth_side_body overflow-x-hidden content" id="" style="">
        <?php

//        var_dump($_SERVER['SERVER_ADDR'].str_replace("/index.php", "", $_SERVER['PHP_SELF']));
        ob_start();



        include_once("donnees/page.php");
        include_once("traitement/role_t.php");
        $request = explode('?', $_SERVER['REQUEST_URI']);
        $present_request = explode("&", $request[1]);
        $libele = explode('=', $present_request[0]);
        //        var_dump($libele);
        $page = new Page(0, $libele[1], $present_request[0], "");
//        $data =  ($_SERVER['SERVER_ADDR'] == "127.0.0.1" ? "localhost" : $_SERVER['SERVER_ADDR']) . str_replace("/index.php", "", $_SERVER['PHP_SELF']);


        try {
            $page->ajouter();
            Page::prepare_query("Insert into page_role_aep (page_id, role_id, write_access) values (?, ?, ?)", array($page->id, 1, 1));
        } catch (Exception $e) {
            echo "Erreur : " . $e->getMessage() . '<br>  la page existe deja surement';
        }
        //        var_dump($present_request);
        //                var_dump($_SESSION);
        include_once("traitement/user_t.php");
        $access_level = AuthManager::checkPageAccess($_SESSION['user_id'], $libele[1]);
        ob_get_clean();
//        var_dump($data);
//        var_dump($data);
        //        var_dump($access_level);
        //        var_dump($_SESSION, $access_level <= 0 && isset($_SESSION['id_aep'], $_SESSION['user_id']));
        if (isset($_GET['page'])) {
            if ($_GET['page'] == 'logout') {
                AuthManager::logout();
            }elseif ($_GET['page'] == 'login') {
                include_once ("presentation/login_component.php");
                exit();
            }elseif ($_GET['page'] == 'register') {
                include_once ("presentation/register_component.php");
                exit();
            }
        }
        if (count($_GET) == 0) {
            header("location: ?page=home");
        } elseif (($access_level == -1) && isset($_SESSION['id_aep'], $_SESSION['user_id'])) {
            include_once("presentation/nos_access_page.php");
        } else {
//            echo "<div class=contai"
            if (Aep_t::isAepIdInSession()) {
//                var_dump($_SESSION);
                include_once("presentation/message.php");
                include_once("presentation/liste.php");
            } else {
                include_once("presentation/home.php");
            }
            include_once("presentation/compteur_component.php");
            include_once("presentation/fomulaire.php");

            $previous_request = explode('?', $_SERVER['REQUEST_URI']);
            $_SESSION['PREVIOUS_REQUEST_HEADER'] = count($previous_request) > 1 ? $previous_request[1] : '';
        }
        ?>

    </article>
    <?php include_once "presentation/footer.php"; ?>
</main>

<div id="container"></div>
<script>
    document.getElementById('<?php echo isset($_GET['operation']) ? $_GET['operation'] : 'rien'?>')
    $('#<?php echo isset($_GET['operation']) ? $_GET['operation'] : 'rien'?>').slideToggle();
    setTimeout(() => {
        $('#<?php echo isset($_GET['operation']) ? $_GET['operation'] : 'rien'?>').slideUp();
    }, 5000);
    $('#aggrandir').on('click', () => {
        document.getElementById("grand").value = document.getElementById("contenu").value;
    });
</script>


<script>
    // Exemple de tableau JavaScript
    const data = [
        ['Nom', 'Âge', 'Ville'],
        ['Alice', 30, 'Paris'],
        ['Bob', 25, 'Lyon'],
        ['Charlie', 35, 'Marseille']
    ];

    // Fonction pour convertir le tableau en CSV
    function convertToCSV(array) {
        return array.map(row => row.join(',')).join('\n');
    }

    // Fonction pour télécharger le CSV
    function downloadCSV() {
        const csvContent = convertToCSV(data);
        const blob = new Blob([csvContent], {type: 'text/csv;charset=utf-8;'});
        const url = URL.createObjectURL(blob);

        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', 'data.csv'); // Nom du fichier
        document.body.appendChild(link); // Nécessaire pour Firefox

        link.click(); // Simule un clic pour télécharger le fichier
        document.body.removeChild(link); // Supprime le lien après le téléchargement
    }

    // Événement de clic sur le bouton
    // document.getElementById('downloadBtn').addEventListener('click', downloadCSV);
</script>

<script src="presentation/assets/js/js.js"></script>
<script>
    function showDetails(button) {
        const aep = JSON.parse(button.dataset.aep);
        document.getElementById('detail-libele').textContent = aep.libele || 'Non défini';
        document.getElementById('detail-date').textContent = aep.date || 'Non défini';
        document.getElementById('detail-description').textContent = aep.description || 'Non défini';
        document.getElementById('detail-nom_banque').textContent = aep.nom_banque || 'Non défini';
        document.getElementById('detail-numero_compte').textContent = aep.numero_compte || 'Non défini';
        document.getElementById('detail-fichier_facture').textContent = aep.fichier_facture || 'Non défini';

        // Afficher l'image du modèle de facture
        const image = document.getElementById('detail-image');
        if (aep.fichier_facture === 'model_fokoue') {
            image.src = 'presentation/assets/images/model_fokoue.png';
            image.classList.remove('d-none');
        } else if (aep.fichier_facture === 'model_nkongzem') {
            image.src = 'presentation/assets/images/model_nkongzem.png';
            image.classList.remove('d-none');
        } else {
            image.classList.add('d-none');
        }

        // Statistiques (à remplacer par des appels AJAX ou des données réelles)
        document.getElementById('detail-nb_reseaux').textContent = 'N/A';
        document.getElementById('detail-nb_abonnes').textContent = 'N/A';
    }
</script>


</body>
</html>