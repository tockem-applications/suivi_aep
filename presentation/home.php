<?php

if (isset($_GET['page'])) {
    if ($_GET['page'] == 'home') {
        ?>
        <div style="background-image: url('presentation/assets/images/alimentation-en-eau-potable.webp')">
            <div class="hero" style="background-color: rgba(0,0,0,0.5);)">
                <h1>Bienvenue à l'Association Tockem</h1>
                <p>Suivi technique et financier de votre réseau d'adduction en eau potable</p>
                <!--            <a href="#features" class="btn btn-primary btn-lg">Découvrir</a>-->
            </div>

            <div>

            </div>

            <div class="container-fluid text-white " id="features" style="background-color: rgba(0, 0, 0, 0.5)">
                <h2 class="text-center">Fonctionnalités</h2>
                <div class="row">
                    <div class="col-md-4 feature">
                        <h3>Suivi Technique</h3>
                        <p>Gérez et suivez l'état de votre réseau d'eau potable avec des outils intuitifs.</p>
                    </div>
                    <div class="col-md-4 feature">
                        <h3>Analyse Financière</h3>
                        <p>Obtenez des rapports financiers clairs et précis pour une meilleure gestion de vos
                            ressources.</p>
                    </div>
                    <div class="col-md-4 feature">
                        <h3>Supports Communautaire</h3>
                        <p>Participez à notre communauté pour échanger des conseils et des bonnes pratiques.</p>
                    </div>
                </div>

            </div>

        </div>
        <?php
        display_aep_to_select();
        ?>

        <?php
    }

}
