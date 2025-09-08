<?php
require("traitement/aep_t.php");

$is_aep_selected = Aep_t::isAepIdInSession();

?>
<nav class="navbar navbar-expand-lg position-fixed z-3 w-100 shadow-sm"
    style="min-height: 10vh; background: linear-gradient(90deg, #2c9D11, #34495e);">
    <div class="container-fluid">
        <!-- Logo/Brand -->
        <a class="navbar-brand text-white fw-bold fs-4" href="index.php?page=home"
            style="font-family: 'Segoe UI', sans-serif;">
            Tockem SPE
        </a>
        <!-- Toggler Button -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse"
            data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
            aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <?php if ($is_aep_selected): ?>
            <!-- Navbar Content -->
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <!-- AEP Selection -->
                    <!--                    --><?php //display_li_aep_to_select(); ?>
                    <!--                    --><?php //display_li_aep_to_select(); ?>
                    <!-- Dropdown: Structure -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white fw-medium" href="#" id="navbarDropdownStructure"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false"
                            style="transition: color 0.3s ease;">
                            Structure
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark shadow-sm" aria-labelledby="navbarDropdownStructure">
                            <li><a class="dropdown-item" href="?page=reseaux">Réseaux</a></li>
                            <li><a class="dropdown-item" href="?form=reseau">Nouveau réseau</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="?page=abonne">Abonnés</a></li>
                            <!-- <li><a class="dropdown-item" href="?form=abone">Ajouter un abonné</a></li>
                            <li><a class="dropdown-item" href="?list=compteur_reseau">Liste des compteurs réseau</a></li>
                            <li><a class="dropdown-item" href="?list=distribution_simple">Liste des abonnés</a></li> -->
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="?page=fokoue_data">Fokoue data</a></li>
                            <!--                            <li><a class="dropdown-item" href="?list=production_simple">Liste des compteurs de production</a></li>-->
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                        </ul>
                    </li>
                    <!-- Dropdown: Facturation -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white fw-medium" href="#" id="navbarDropdownFacturation"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false"
                            style="transition: color 0.3s ease;">
                            Facturation
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark shadow-sm" aria-labelledby="navbarDropdownFacturation">
                            <li><a class="dropdown-item" href="index.php?list=recouvrement">Recouvrement</a></li>
                            <!--                            <li><a class="dropdown-item" href="index.php?list=facture_month">Facturation</a></li>-->
                            <li><a class="dropdown-item" href="index.php?page=releves">Relèves</a></li>
                            <li><a class="dropdown-item" href="index.php?list=mois_facturation">Mois Facturés</a></li>
                            <li><a class="dropdown-item" href="index.php?form=constante_reseau">Tarifs AEP</a></li>
                            <li><a class="dropdown-item" href="index.php?page=reseau">Statistiques</a></li>
                            <?php if (isset($_SESSION['id'], $_SESSION['email'], $_SESSION['nom'], $_SESSION['prenom']) && $_SESSION['id'] == '1'): ?>
                                <li><a class="dropdown-item" href="index.php?list=cle">Clé</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <!-- Dropdown: Finances -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white fw-medium" href="#" id="navbarDropdownFinances"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false"
                            style="transition: color 0.3s ease;">
                            Finances
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark shadow-sm" aria-labelledby="navbarDropdownFinances">
                            <li><a class="dropdown-item" href="?page=transaction">Entrée/Sortie</a></li>
                            <!--                            <li><a class="dropdown-item" href="?list=transaction">Liste des dépenses</a></li>-->
                            <!--                            <li><a class="dropdown-item" href="?list=releve_manuelle">Liste des entrées</a></li>-->
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="?page=redevance">Redevances</a></li>
                            <li><a class="dropdown-item" href="?page=tarif_aep">Tarifs</a></li>
                            <li><a class="dropdown-item" href="?page=recouvrement">Mois de Recouvrement</a></li>

                            <li><a class="dropdown-item" href="?page=versement  ">Versements</a></li>
                            <li><a class="dropdown-item" href="?page=register  ">Enregistrement</a></li>
                        </ul>
                    </li>

                    <!-- Dropdown: Opération -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white fw-medium" href="#" id="navbarDropdownOperation"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false"
                            style="transition: color 0.3s ease;">
                            Administration
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark shadow-sm" aria-labelledby="navbarDropdownOperation">

                            <!--                            <li><a class="dropdown-item" href="?page=download_index&action=export_index" target="_blank">Exporter vers mobile</a></li>-->
                            <!--                            <li><a class="dropdown-item" href="?form=import_index">Relève automatique</a></li>-->
                            <!--                            <li><a class="dropdown-item" href="?list=releve_manuelle">Relève manuelle</a></li>-->
                            <li><a class="dropdown-item" href="?page=role">Gestion des roles</a></li>
                            <li><a class="dropdown-item" href="?page=clefs">Gestion des clefs</a></li>
                            <li><a class="dropdown-item" href="?page=aep">Gestion des AEP</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="?page=backup">Sauvegarde & Restauration</a></li>
                        </ul>
                    </li>
                    <!-- Connexion/Déconnexion -->
                    <!--                    <li class="nav-item">-->
                    <!--                        <a class="nav-link text-white fw-medium" href="--><?php //echo isset($_SESSION['id'], $_SESSION['email'], $_SESSION['nom'], $_SESSION['prenom']) ? '../traitement/admin_t.php?deconnecter=true' : 'index.php?form=login'; ?><!--" style="transition: color 0.3s ease;">-->
                    <!--                            --><?php //echo isset($_SESSION['id'], $_SESSION['email'], $_SESSION['nom'], $_SESSION['prenom']) ? 'Se déconnecter' : 'Se connecter'; ?>
                    <!--                        </a>-->
                    <!--                    </li>-->
                </ul>

                <!-- AEP Label et Formulaire de recherche -->
                <div class="d-flex align-items-center">
                    <span class="h3 text-white me-3"><a class="" style="color: white"
                            href="?page=aep_dashboard&aep_id=<?php echo htmlspecialchars($_SESSION['id_aep']); ?>"><?php echo htmlspecialchars($_SESSION['libele_aep']); ?></a></span>
                    <form class="d-flex">
                        <input class="form-control me-2 rounded-pill" type="search" id="le_input"
                            placeholder="Rechercher..." aria-label="Search" style="border: 1px solid #ced4da;">

                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</nav>

<style>
    /* Styles personnalisés pour le header */
    .navbar {
        padding: 1rem 2rem;
    }

    .navbar-brand {
        color: #ffffff !important;
        transition: color 0.3s ease;
    }

    .navbar-brand:hover {
        color: #e0e0e0 !important;
    }

    .nav-link {
        color: #ffffff !important;
        padding: 0.75rem 1.25rem !important;
        border-radius: 5px;
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: #e0e0e0 !important;
    }

    .dropdown-menu {
        background-color: #2c6650 !important;
        border: none;
        border-radius: 8px;
        margin-top: 0.5rem;
    }

    .dropdown-item {
        color: #ffffff !important;
        padding: 0.5rem 1.5rem;
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    .dropdown-item:hover {
        background-color: #34665e;
        color: #e0e0e0 !important;
    }

    .dropdown-divider {
        border-top: 1px solid rgba(255, 255, 255, 0.2);
    }

    .form-control {
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .form-control:focus {
        border-color: #28a745 !important;
        box-shadow: 0 0 5px rgba(40, 167, 69, 0.3) !important;
    }

    .btn-success:hover {
        background-color: #218838 !important;
    }

    /* Ajustements pour mobile */
    @media (max-width: 992px) {
        .navbar-nav {
            background-color: #39665e;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 0.5rem;
        }

        .nav-link {
            padding: 0.5rem 1rem !important;
        }

        .h3 {
            font-size: 1.25rem !important;
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            margin-bottom: 0.5rem;
        }

        .btn-success {
            width: 100%;
        }
    }
</style>