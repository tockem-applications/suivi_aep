<?php
require_once("traitement/aep_t.php");

$is_aep_selected = Aep_t::isAepIdInSession();

?>
<nav class="navbar navbar-expand-lg w-100 shadow-sm"
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
                            <li><a class="dropdown-item" href="?page=reseaux"><i
                                        class="bi bi-diagram-3 me-2"></i>Réseaux</a></li>
                            <li><a class="dropdown-item" href="?page=abonne"><i class="bi bi-people me-2"></i>Abonnés
                                    (BP)</a></li>
                            <li><a class="dropdown-item" href="?page=borne_fontaine"><i
                                        class="bi bi-droplet me-2"></i>Bornes Fontaines</a></li>
                            <!--                            <li><a class="dropdown-item" href="?form=reseau">Nouveau réseau</a></li>-->
                            <li><a class="dropdown-item" href="?page=aep"><i class="bi bi-building me-2"></i>AEPs</a></li>
                            <!--                            <li><a class="dropdown-item" href="?form=abone">Ajouter un abonné</a></li>-->
                            <!-- <li><a class="dropdown-item" href="?form=abone">Ajouter un abonné</a></li>
                            <li><a class="dropdown-item" href="?list=compteur_reseau">Liste des compteurs réseau</a></li>
                            <li><a class="dropdown-item" href="?list=distribution_simple">Liste des abonnés</a></li> -->
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="?page=fokoue_data"><i class="bi bi-database me-2"></i>Fokoue
                                    data</a></li>
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
                            <li><a class="dropdown-item" href="index.php?list=recouvrement"><i
                                        class="bi bi-cash-stack me-2"></i>Recouvrement</a></li>
                            <li><a class="dropdown-item" href="index.php?list=recouvrement_v2"><i
                                        class="bi bi-star-fill text-warning me-2"></i>Recouvrement V2</a></li>
                            <!--                            <li><a class="dropdown-item" href="index.php?list=facture_month">Facturation</a></li>-->
                            <li><a class="dropdown-item" href="index.php?page=releves"><i
                                        class="bi bi-journal-text me-2"></i>Relèves</a></li>
                            <li><a class="dropdown-item" href="index.php?page=penalites"><i
                                        class="bi bi-exclamation-triangle text-warning me-2"></i>Pénalités</a></li>
                            <li><a class="dropdown-item" href="index.php?list=mois_facturation"><i
                                        class="bi bi-calendar-month me-2"></i>Mois Facturés</a></li>
                            <li><a class="dropdown-item" href="index.php?form=constante_reseau"><i
                                        class="bi bi-tag me-2"></i>Tarifs AEP</a></li>
                            <li><a class="dropdown-item" href="index.php?page=reseau"><i
                                        class="bi bi-bar-chart me-2"></i>Statistiques</a></li>
                            <?php if (isset($_SESSION['id'], $_SESSION['email'], $_SESSION['nom'], $_SESSION['prenom']) && $_SESSION['id'] == '1'): ?>
                                <li><a class="dropdown-item" href="index.php?list=cle"><i class="bi bi-key me-2"></i>Clé</a>
                                </li>
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
                            <li><a class="dropdown-item" href="?page=transaction"><i
                                        class="bi bi-arrow-left-right me-2"></i>Entrée/Sortie</a></li>
                            <li><a class="dropdown-item" href="?page=compte_rendu_financier"><i
                                        class="bi bi-file-earmark-text me-2"></i>Compte Rendu Financier</a></li>
                            <li><a class="dropdown-item" href="?page=compte_rendu_tableau"><i
                                        class="bi bi-table me-2"></i>Compte d'Exploitation (Tableau)</a></li>
                            <li><a class="dropdown-item" href="?page=analyse_financiere"><i
                                        class="bi bi-graph-up me-2"></i>Analyse Financière</a></li>
                            <li><a class="dropdown-item" href="?page=config_compte_rendu"><i
                                        class="bi bi-gear me-2"></i>Config. Compte Rendu</a></li>
                            <li><a class="dropdown-item" href="?page=categories_flux_manuel"><i
                                        class="bi bi-funnel me-2"></i>Catégories Flux Manuels</a></li>
                            <!--                            <li><a class="dropdown-item" href="?list=transaction">Liste des dépenses</a></li>-->
                            <!--                            <li><a class="dropdown-item" href="?list=releve_manuelle">Liste des entrées</a></li>-->
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="?page=tarif_aep"><i class="bi bi-tag me-2"></i>Tarifs</a>
                            </li>
                            <li><a class="dropdown-item" href="?page=branchements"><i
                                        class="bi bi-plug me-2"></i>Branchements</a></li>
                            <li><a class="dropdown-item" href="?page=recouvrement"><i
                                        class="bi bi-calendar-check me-2"></i>Mois de Recouvrement</a></li>

                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="?page=redevance"><i
                                        class="bi bi-cash-coin me-2"></i>Redevances</a></li>
                            <li><a class="dropdown-item" href="?page=versement  "><i
                                        class="bi bi-wallet2 me-2"></i>Versements</a></li>
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
                            <!--                            <li><a class="dropdown-item" href="?page=aep">Gestion des aep</a></li>-->
                            <li><a class="dropdown-item" href="?page=role"><i class="bi bi-person-badge me-2"></i>Gestion
                                    des roles</a></li>
                            <li><a class="dropdown-item" href="?page=clefs"><i class="bi bi-key me-2"></i>Gestion des
                                    clefs</a></li>
                            <li><a class="dropdown-item" href="?page=register  "><i
                                        class="bi bi-person-plus me-2"></i>Enregistrement</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="?page=backup"><i
                                        class="bi bi-cloud-arrow-down me-2"></i>Sauvegarde & Restauration</a></li>
                        </ul>
                    </li>
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