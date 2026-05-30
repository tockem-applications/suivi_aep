<?php
@include_once(__DIR__ . '/../donnees/app_licence.php');
@include_once('donnees/app_licence.php');

$lic = app_licence();
$editeur = $lic->getEditeur();
$client = $lic->getClient();
$logiciel = $lic->getLogiciel();

function ap_champ($block, $key, $label)
{
    if (!is_array($block) || !isset($block[$key])) {
        return;
    }
    $v = trim((string) $block[$key]);
    if ($v === '') {
        return;
    }
    echo '<dt class="col-sm-4 text-muted">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</dt>';
    echo '<dd class="col-sm-8">' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '</dd>';
}
?>
<div class="container-fluid py-3 px-2">
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="?page=home">Accueil</a></li>
            <li class="breadcrumb-item active" aria-current="page">À propos</li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">À propos</h1>
            <p class="text-muted mb-0 small">Informations sur le logiciel et la licence d'utilisation.</p>
        </div>
        <span class="badge <?php echo htmlspecialchars($lic->getStatutBadgeClass(), ENT_QUOTES, 'UTF-8'); ?> fs-6">
            <?php echo htmlspecialchars($lic->getStatutLibelle(), ENT_QUOTES, 'UTF-8'); ?>
        </span>
    </div>

    <?php if (!$lic->isAccesAutorise()): ?>
        <div class="alert alert-warning">
            <strong><?php echo htmlspecialchars($lic->getStatutLibelle(), ENT_QUOTES, 'UTF-8'); ?></strong>
            <?php if ($lic->getLastError() !== ''): ?>
                — <?php echo htmlspecialchars($lic->getLastError(), ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
            <a href="?page=licence" class="alert-link ms-1">Importer une licence</a>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-light py-2">
                    <strong><i class="bi bi-pc-display me-2"></i>Logiciel</strong>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <?php
                        ap_champ($logiciel, 'nom', 'Nom');
                        ap_champ($logiciel, 'version', 'Version');
                        ap_champ($logiciel, 'reference', 'Référence');
                        ?>
                        <dt class="col-sm-4 text-muted">Fichier licence</dt>
                        <dd class="col-sm-8"><code><?php echo htmlspecialchars($lic->getSourceBasename() !== '' ? $lic->getSourceBasename() : '—', ENT_QUOTES, 'UTF-8'); ?></code></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-light py-2">
                    <strong><i class="bi bi-shield-check me-2"></i>Licence</strong>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">N° licence</dt>
                        <dd class="col-sm-8"><code><?php echo htmlspecialchars($lic->getNumeroLicence() !== '' ? $lic->getNumeroLicence() : '—', ENT_QUOTES, 'UTF-8'); ?></code></dd>
                        <dt class="col-sm-4 text-muted">Type</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($lic->getTypeLicence() !== '' ? $lic->getTypeLicence() : '—', ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt class="col-sm-4 text-muted">Début</dt>
                        <dd class="col-sm-8"><?php echo AppLicence::fmtDateFr($lic->getDateDebut()); ?></dd>
                        <dt class="col-sm-4 text-muted">Expiration</dt>
                        <dd class="col-sm-8"><?php echo AppLicence::fmtDateFr($lic->getDateExpiration()); ?></dd>
                        <?php
                        $jours = $lic->getJoursRestants();
                        if ($jours !== null):
                        ?>
                        <dt class="col-sm-4 text-muted">Jours restants</dt>
                        <dd class="col-sm-8"><?php echo (int) $jours; ?></dd>
                        <?php endif; ?>
                    </dl>
                    <?php if ($lic->getNotes() !== ''): ?>
                        <hr class="my-2">
                        <p class="small text-muted mb-0"><?php echo nl2br(htmlspecialchars($lic->getNotes(), ENT_QUOTES, 'UTF-8')); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-light py-2">
                    <strong><i class="bi bi-building me-2"></i>Client / utilisateur autorisé</strong>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <?php
                        ap_champ($client, 'organisme', 'Organisme');
                        ap_champ($client, 'service', 'Service');
                        ap_champ($client, 'contact', 'Contact');
                        ap_champ($client, 'email', 'E-mail');
                        ap_champ($client, 'telephone', 'Téléphone');
                        ap_champ($client, 'adresse', 'Adresse');
                        ?>
                    </dl>
                    <?php
                    $hasClient = false;
                    if (is_array($client)) {
                        foreach ($client as $v) {
                            if (trim((string) $v) !== '') {
                                $hasClient = true;
                                break;
                            }
                        }
                    }
                    if (!$hasClient):
                    ?>
                        <p class="text-muted small mb-0">Aucune information client dans la licence active.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-light py-2">
                    <strong><i class="bi bi-person-workspace me-2"></i>Éditeur</strong>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <?php
                        ap_champ($editeur, 'nom', 'Raison / projet');
                        ap_champ($editeur, 'contact', 'Contact');
                        ap_champ($editeur, 'titre', 'Profil');
                        ap_champ($editeur, 'email', 'E-mail');
                        ap_champ($editeur, 'telephone', 'Téléphone');
                        ap_champ($editeur, 'adresse', 'Adresse');
                        if (is_array($editeur) && !empty($editeur['site_web'])) {
                            $site = trim((string) $editeur['site_web']);
                            if ($site !== '') {
                                echo '<dt class="col-sm-4 text-muted">Site web</dt>';
                                echo '<dd class="col-sm-8"><a href="' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '</a></dd>';
                            }
                        }
                        ?>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <p class="text-muted small mt-3 mb-0">
        &copy; <?php echo date('Y'); ?>
        <?php echo htmlspecialchars(isset($editeur['nom']) ? $editeur['nom'] : 'Suivi AEP', ENT_QUOTES, 'UTF-8'); ?>.
        Tous droits réservés. Usage réservé au client désigné dans la licence.
    </p>
</div>
