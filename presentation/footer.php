<!---->
<!--<footer class="bg-light text-center py-4" id="contact">-->
<!--    <p>Contactez-nous : <a href="mailto:info@tockem.org">info@tockem.org</a></p>-->
<!--    <p>&copy; 2024 Association Tockem. Tous droits réservés.</p>-->
<!--</footer>-->

<footer class="text-white py-5" style="background: linear-gradient(90deg, #2c3e50, #34495e);">
    <div class="container">
        <div class="row">
            <!-- Section 1: À propos -->
            <div class="col-md-4 mb-4">
                <h5 class="fw-bold mb-3" style="font-family: 'Segoe UI', sans-serif;">Suivi AEP</h5>
                <p class="text-white-50">
                    Suivi AEP est une plateforme dédiée à la gestion efficace des réseaux d'eau potable. Suivez vos facturations, opérations et finances en toute simplicité.
                </p>
            </div>

            <!-- Section 2: Liens rapides -->
            <div class="col-md-4 mb-4">
                <h5 class="fw-bold mb-3" style="font-family: 'Segoe UI', sans-serif;">Liens rapides</h5>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="index.php?page=home" class="text-white-50 text-decoration-none hover-link">Accueil</a>
                    <li class="mb-2">
                    <a href="?page=reseau" class="text-white-50 text-decoration-none hover-link">Réseaux</a>
                    </li>
                    <li class="mb-2">
                    <a href="index.php?list=facture_month" class="text-white-50 text-decoration-none hover-link">Facturation</a>
                    </li>
                    <li class="mb-2">
                    <a href="?form=finance" class="text-white-50 text-decoration-none hover-link">Finances</a>
                    </li>
                    </li>
                    <li class="mb-2">
                        <a href="index.php?page=login" class="text-white-50 text-decoration-none hover-link">Connexion</a>
                    </li>
                </ul>
            </div>

            <!-- Section 3: Contact -->
            <div class="col-md-4 mb-4">
                <h5 class="fw-bold mb-3" style="font-family: 'Segoe UI', sans-serif;">Contact</h5>
                <ul class="list-unstyled text-white-50">
                    <li class="mb-2"><i class="bi bi-envelope-fill me-2"></i>Email: ericktsafack2017@gmail.com</li>
                    <li class="mb-2"><i class="bi bi-telephone-fill me-2"></i>Téléphone: +237 654 19 05 14</li>
                    <li class="mb-2"><i class="bi bi-geo-alt-fill me-2"></i>Adresse: Entré Bafou chefferie - Direction Tockem</li>
                </ul>
            </div>
        </div>

        <!-- Copyright -->
        <div class="row">
            <div class="col-12 text-center pt-4 border-top border-white-50">
                <p class="mb-0 text-white-50">&copy; <?php echo date('Y'); ?> Suivi AEP. Tous droits réservés.</p>
            </div>
        </div>
    </div>
</footer>

<!-- Inclure Bootstrap Icons pour les icônes de contact -->
<!--<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">-->

<style>
    /* Styles personnalisés pour le footer */
    footer {
        margin-top: auto; /* Pour s'assurer que le footer reste en bas si le contenu est court */
    }

    .hover-link {
        transition: color 0.3s ease;
    }

    .hover-link:hover {
        color: #ffffff !important;
    }

    .text-white-50 {
        color: rgba(255, 255, 255, 0.7) !important;
    }

    /* Ajustements pour mobile */
    @media (max-width: 768px) {
        footer h5 {
            font-size: 1.1rem;
        }

        footer p, footer li {
            font-size: 0.9rem;
        }
    }
</style>



<?php
