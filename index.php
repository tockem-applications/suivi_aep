<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="presentation/assets/images/favicon.png">
    <link rel="stylesheet" href="presentation/style.css">
    <link rel="stylesheet" href="presentation/bootstrap/css/bootstrap.css">
    <link rel="stylesheet" href="presentation/assets/bootstrap/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="presentation/assets/css/styles.css"> -->

    <!-- <script src="presentation/js.js"></script> -->
    <script src="presentation/assets/@canvasjs/charts/canvasjs.min.js"></script>
    <script src="presentation/assets/jquery.js"></script>
    <script src="presentation/assets/js/bs-init.js"></script>
    <script src="presentation/assets/js/javascript.js"></script>

    <script src="presentation/js.js"></script>
    <script src="presentation/assets/jquery.js"></script>
    <!--<script src="presentation/bootstrap/js/bootstrap.js"></script>-->
    <script src="presentation/assets/bootstrap/js/bootstrap.min.js"></script>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fokoue Water</title>

    <style>
        body {
            background-color: #f8f9fa;
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

        /* Styles pour les écrans non mobiles */
        @media (max-width: 550px) {
            /* 768px est un point de rupture commun pour les tablettes */
            .fixed-div {
                overflow-y: auto;
                /*width: 600px; !* Fixe la largeur à 600px *!*/
                height: 50vh; /* Fixe la hauteur à 400px */
            }
        }

        @media (max-width: 768px) {
            /* 768px est un point de rupture commun pour les tablettes */
            .fixed-div {
                overflow-y: auto;
                /*width: 600px; !* Fixe la largeur à 600px *!*/
                height: 40vh; /* Fixe la hauteur à 400px */
            }
        }
    </style>
</head>
<body style="background-color: rgba(220, 220, 220, 1);">
<?php
setlocale(LC_TIME, 'fr_FR.UTF-8');

include_once("presentation/aep_components.php");
include_once("presentation/header.php");
?>
<main class="my_body overflow-hidden" style="">
    <!-- <aside class="left_side_body" style="border-right: solid white 1px;">
        <img src="presentation/assets/images/aside_image.png" alt="tockem image" style="width: 100%; height: 50%;">
    </aside> -->
    <article class="rigth_side_body container-fluid overflow-y-scroll" id="a_imprimer" style="height: 90vh">
        <?php
//        var_dump($_SESSION);
        if (count($_GET) == 0)
            header("location: ?page=home");
//            include_once("presentation/home.php");
        else {
            if (Aep_t::isAepIdInSession()) {
//                var_dump($_SESSION);
                include_once("presentation/message.php");
                include_once("presentation/liste.php");
            } else {
                include_once("presentation/home.php");
            }
            include_once("presentation/fomulaire.php");

            $previous_request = explode('?',  $_SERVER['REQUEST_URI']);
            $_SESSION['PREVIOUS_REQUEST_HEADER'] = count($previous_request) > 1 ? $previous_request[1] : '';
        }
        ?>

    </article>
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
</body>
</html>