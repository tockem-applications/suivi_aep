<div style="height: 100px"></div>
<div class="access-denied-container pt-5">
    <style>
        .access-denied-container {
            max-width: 900px;
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.5s ease-in-out;
            margin: 0 auto;
        }

        .access-denied-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }

        .access-denied-title {
            font-size: 2rem;
            color: #343a40;
            margin-bottom: 1rem;
        }

        .access-denied-message {
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 0;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 576px) {
            .access-denied-container {
                margin: 1rem;
                padding: 1.5rem;
            }

            .access-denied-icon {
                font-size: 3rem;
            }

            .access-denied-title {
                font-size: 1.5rem;
            }

            .access-denied-message {
                font-size: 1rem;
            }
        }
    </style>
    <!--    <i class="fas fa-exclamation-triangle access-denied-icon"></i>-->
    <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 5rem;"></i>
    <h1 class="access-denied-title">Accès Refusé</h1>
    <p class="access-denied-message">
        Désolé, vous n'avez pas les autorisations nécessaires pour accéder à cette page.<br>
        Veuillez contacter l'administrateur si vous pensez qu'il s'agit d'une erreur.
    </p>
    <div>
        <?php if (isset($_SESSION['PREVIOUS_REQUEST_HEADER'])): ?>
            <a class="btn btn-primary " href="?<?php echo $_SESSION['PREVIOUS_REQUEST_HEADER']; ?>">Retourner</a>
        <?php endif; ?>
    </div>
</div>