<?php
/**
 * Affectation de coordonnées GPS importées depuis un fichier CSV.
 *
 * Parcours : charger un CSV de coordonnées → choisir un réseau → cocher les
 * abonnés qui recevront une position (au maximum autant que de coordonnées
 * disponibles) → vérifier l'appariement proposé → enregistrer.
 *
 * Le CSV est lu dans le navigateur ; seul l'appariement final est transmis au
 * serveur, qui revalide tout (plausibilité des coordonnées et appartenance
 * réelle du compteur à l'AEP courant).
 */
@include_once(__DIR__ . '/../donnees/manager.php');
@include_once('donnees/manager.php');
@include_once(__DIR__ . '/../donnees/cartographie.php');
@include_once('donnees/cartographie.php');
@include_once(__DIR__ . '/../donnees/reseau.php');
@include_once('donnees/reseau.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id_aep = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
$libele_aep = isset($_SESSION['libele_aep']) ? htmlspecialchars($_SESSION['libele_aep'], ENT_QUOTES, 'UTF-8') : '';

if (!$id_aep) {
    echo '<div class="container mt-5"><div class="alert alert-warning">Sélectionne un AEP pour affecter des coordonnées.</div></div>';
    exit;
}

Cartographie::ensureTables();

$reseaux = Reseau::getAllByIdReseau($id_aep)->fetchAll(PDO::FETCH_ASSOC);
$csrf_token = Csrf::token();
?>
<style>
    .aff-page { --aff-border: #e3e8ee; --aff-muted: #6b7684; background: #f4f6f8; padding: 16px; }
    .aff-card { background: #fff; border: 1px solid var(--aff-border); border-radius: 12px; margin-bottom: 12px; }
    .aff-card-head { padding: 12px 16px; border-bottom: 1px solid var(--aff-border); display: flex; align-items: center; gap: 10px; }
    .aff-card-body { padding: 16px; }
    .aff-step {
        width: 24px; height: 24px; border-radius: 50%; background: #2c9D11; color: #fff;
        display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex: 0 0 auto;
    }
    .aff-step.inactif { background: #cbd2d9; }
    .aff-card-head h2 { font-size: .95rem; font-weight: 650; margin: 0; }
    .aff-liste { max-height: 420px; overflow-y: auto; border: 1px solid var(--aff-border); border-radius: 8px; }
    .aff-liste table { margin: 0; font-size: 13px; }
    .aff-liste thead th { position: sticky; top: 0; background: #f4f6f8; z-index: 1; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--aff-muted); }
    .aff-quota { font-variant-numeric: tabular-nums; }
    .aff-deja { background: #fff8e1; }
</style>

<div class="aff-page">
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="?page=home">Accueil</a></li>
            <li class="breadcrumb-item"><a href="?page=cartographie">Cartographie</a></li>
            <li class="breadcrumb-item active">Affectation de coordonnées</li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h5 mb-0">Affecter des coordonnées importées</h1>
            <p class="text-muted mb-0 small">AEP : <strong><?php echo $libele_aep; ?></strong></p>
        </div>
        <a href="?page=cartographie" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-map me-1"></i>Voir la carte
        </a>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-5">

            <div class="aff-card">
                <div class="aff-card-head">
                    <span class="aff-step">1</span>
                    <h2>Fichier de coordonnées</h2>
                </div>
                <div class="aff-card-body">
                    <input type="file" id="affFichier" accept=".csv,.txt" class="form-control form-control-sm mb-2">
                    <div class="small text-muted mb-2">
                        CSV avec une colonne de longitude et une de latitude — le format exporté par QGIS
                        (<code>X,Y,fid</code>) est reconnu automatiquement. Séparateur virgule, point-virgule ou tabulation.
                    </div>
                    <div id="affColonnes" style="display:none;">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small mb-1" for="affColLon">Colonne longitude</label>
                                <select id="affColLon" class="form-select form-select-sm"></select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1" for="affColLat">Colonne latitude</label>
                                <select id="affColLat" class="form-select form-select-sm"></select>
                            </div>
                        </div>
                    </div>
                    <div id="affFichierStatut" class="small mt-2"></div>
                </div>
            </div>

            <div class="aff-card">
                <div class="aff-card-head">
                    <span class="aff-step inactif" id="affStep2">2</span>
                    <h2>Réseau</h2>
                </div>
                <div class="aff-card-body">
                    <select id="affReseau" class="form-select form-select-sm">
                        <option value="">— Choisir un réseau —</option>
                        <?php foreach ($reseaux as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>"><?php echo htmlspecialchars($r['nom'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="affMasquerGeo" checked>
                        <label class="form-check-label small" for="affMasquerGeo">
                            Masquer les abonnés déjà géolocalisés
                        </label>
                    </div>
                </div>
            </div>

            <div class="aff-card">
                <div class="aff-card-head">
                    <span class="aff-step inactif" id="affStep4">4</span>
                    <h2>Enregistrement</h2>
                </div>
                <div class="aff-card-body">
                    <div class="small text-muted mb-2" id="affRecap">
                        Sélectionne d'abord un fichier, un réseau et des abonnés.
                    </div>
                    <button type="button" class="btn btn-success btn-sm w-100" id="affValider" disabled>
                        <i class="bi bi-check2-circle me-1"></i>Affecter les coordonnées
                    </button>
                    <div class="small mt-2" id="affResultat"></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="aff-card">
                <div class="aff-card-head">
                    <span class="aff-step inactif" id="affStep3">3</span>
                    <h2>Abonnés à géolocaliser</h2>
                    <span class="ms-auto badge bg-secondary aff-quota" id="affQuota">0 / 0</span>
                </div>
                <div class="aff-card-body">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <input type="text" id="affRecherche" class="form-control form-control-sm" style="max-width: 240px;" placeholder="Filtrer par nom ou n° compteur">
                        <button type="button" class="btn btn-light btn-sm border" id="affToutCocher">Cocher jusqu'au maximum</button>
                        <button type="button" class="btn btn-light btn-sm border" id="affToutDecocher">Tout décocher</button>
                    </div>
                    <div class="aff-liste">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 38px;"></th>
                                    <th>Abonné</th>
                                    <th>N° compteur</th>
                                    <th>État</th>
                                    <th>Coordonnée qui sera affectée</th>
                                </tr>
                            </thead>
                            <tbody id="affTbody">
                                <tr><td colspan="5" class="text-muted small p-3">Choisis un réseau pour afficher ses abonnés.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var ajaxUrl = 'traitement/cartographie_t.php';
    var csrf = <?php echo json_encode($csrf_token); ?>;

    var etat = {
        coords: [],      // [{lon, lat}]
        colonnes: [],    // en-têtes du CSV
        lignes: [],      // lignes brutes (tableaux de valeurs)
        abonnes: [],     // abonnés du réseau courant
        selection: []    // ids de compteur cochés, dans l'ordre de sélection
    };

    function el(id) { return document.getElementById(id); }
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

    /**
     * Rend lisible une reponse serveur qui n'est pas du JSON : en developpement,
     * Xdebug renvoie un rapport d'erreur en HTML, illisible tel quel. On en
     * extrait la ligne d'erreur PHP.
     */
    function messageErreurServeur(txt, statut) {
        var brut = String(txt).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        var m = brut.match(/(Fatal error|Parse error|Warning|Notice)[\s:].{0,250}/i);
        if (m) { return m[0].trim(); }
        return 'Réponse inattendue du serveur (HTTP ' + statut + ') : ' + brut.slice(0, 250);
    }

    function lireJson(reponse) {
        return reponse.text().then(function (txt) {
            try {
                return JSON.parse(txt);
            } catch (e) {
                throw new Error(messageErreurServeur(txt, reponse.status));
            }
        });
    }

    function majEtape(idEtape, actif) {
        el(idEtape).classList.toggle('inactif', !actif);
    }

    // ---------------------------------------------------------------
    // Étape 1 : lecture du CSV
    // ---------------------------------------------------------------

    function detecterSeparateur(ligne) {
        var candidats = [';', ',', '\t'];
        var meilleur = ',', max = 0;
        candidats.forEach(function (c) {
            var n = ligne.split(c).length - 1;
            if (n > max) { max = n; meilleur = c; }
        });
        return meilleur;
    }

    function estNombre(v) {
        return v !== '' && v !== null && !isNaN(parseFloat(String(v).replace(',', '.')));
    }

    function nombre(v) {
        return parseFloat(String(v).replace(',', '.'));
    }

    /** Devine quelle colonne est la longitude et laquelle est la latitude. */
    function devinerColonnes(colonnes, lignes) {
        var idxLon = -1, idxLat = -1;
        colonnes.forEach(function (nom, i) {
            var n = nom.toLowerCase().trim();
            if (idxLon === -1 && (n === 'x' || n.indexOf('lon') === 0 || n.indexOf('lng') === 0)) idxLon = i;
            if (idxLat === -1 && (n === 'y' || n.indexOf('lat') === 0)) idxLat = i;
        });
        if (idxLon !== -1 && idxLat !== -1) return { lon: idxLon, lat: idxLat };

        // Pas d'en-tête exploitable : on se fie aux ordres de grandeur du Cameroun
        // (latitude 1 à 14, longitude 7 à 17) sur la première ligne numérique.
        for (var l = 0; l < lignes.length; l++) {
            var num = [];
            lignes[l].forEach(function (v, i) { if (estNombre(v)) num.push({ i: i, v: nombre(v) }); });
            if (num.length >= 2) {
                var a = num[0], b = num[1];
                if (a.v >= 7 && a.v <= 17 && b.v >= 1 && b.v <= 14) return { lon: a.i, lat: b.i };
                if (b.v >= 7 && b.v <= 17 && a.v >= 1 && a.v <= 14) return { lon: b.i, lat: a.i };
                return { lon: a.i, lat: b.i };
            }
        }
        return { lon: 0, lat: 1 };
    }

    function remplirSelectColonnes(select, colonnes, choisi) {
        select.innerHTML = colonnes.map(function (c, i) {
            return '<option value="' + i + '"' + (i === choisi ? ' selected' : '') + '>' + esc(c) + '</option>';
        }).join('');
    }

    function extraireCoordonnees() {
        var iLon = parseInt(el('affColLon').value, 10);
        var iLat = parseInt(el('affColLat').value, 10);
        var coords = [], rejetees = 0;

        etat.lignes.forEach(function (ligne) {
            if (!estNombre(ligne[iLon]) || !estNombre(ligne[iLat])) { rejetees++; return; }
            var lon = nombre(ligne[iLon]);
            var lat = nombre(ligne[iLat]);
            // Même contrôle de plausibilité que le serveur : détecte notamment
            // une inversion latitude/longitude.
            if (lat < 1 || lat > 14 || lon < 7 || lon > 17) { rejetees++; return; }
            coords.push({ lon: lon, lat: lat });
        });

        etat.coords = coords;
        var msg = '<strong>' + coords.length + '</strong> coordonnée(s) exploitable(s).';
        if (rejetees > 0) {
            msg += ' <span class="text-warning">' + rejetees + ' ligne(s) ignorée(s)</span>'
                + ' — hors de la zone attendue (latitude 1–14, longitude 7–17) ou non numériques.'
                + ' Vérifie que les colonnes ne sont pas inversées.';
        }
        el('affFichierStatut').innerHTML = coords.length > 0
            ? '<span class="text-success">' + msg + '</span>'
            : '<span class="text-danger">' + msg + '</span>';

        majEtape('affStep2', coords.length > 0);
        rendreListe();
    }

    el('affFichier').addEventListener('change', function () {
        var fichier = this.files && this.files[0];
        if (!fichier) return;
        var lecteur = new FileReader();
        lecteur.onload = function (e) {
            var texte = String(e.target.result).replace(/\r/g, '');
            var lignes = texte.split('\n').filter(function (l) { return l.trim() !== ''; });
            if (!lignes.length) {
                el('affFichierStatut').innerHTML = '<span class="text-danger">Fichier vide.</span>';
                return;
            }
            var sep = detecterSeparateur(lignes[0]);
            var tableau = lignes.map(function (l) {
                return l.split(sep).map(function (v) { return v.trim().replace(/^"|"$/g, ''); });
            });

            // Première ligne = en-tête si elle n'est pas entièrement numérique
            var premiere = tableau[0];
            var aEntete = premiere.some(function (v) { return v !== '' && !estNombre(v); });
            etat.colonnes = aEntete ? premiere : premiere.map(function (_, i) { return 'Colonne ' + (i + 1); });
            etat.lignes = aEntete ? tableau.slice(1) : tableau;

            var devine = devinerColonnes(etat.colonnes, etat.lignes);
            remplirSelectColonnes(el('affColLon'), etat.colonnes, devine.lon);
            remplirSelectColonnes(el('affColLat'), etat.colonnes, devine.lat);
            el('affColonnes').style.display = '';
            extraireCoordonnees();
        };
        lecteur.readAsText(fichier, 'UTF-8');
    });

    el('affColLon').addEventListener('change', extraireCoordonnees);
    el('affColLat').addEventListener('change', extraireCoordonnees);

    // ---------------------------------------------------------------
    // Étape 2 : chargement des abonnés du réseau
    // ---------------------------------------------------------------

    el('affReseau').addEventListener('change', function () {
        var id = this.value;
        etat.selection = [];
        if (!id) {
            etat.abonnes = [];
            rendreListe();
            return;
        }
        el('affTbody').innerHTML = '<tr><td colspan="5" class="text-muted small p-3">Chargement…</td></tr>';
        fetch(ajaxUrl + '?action=get_abonnes_reseau&id_reseau=' + encodeURIComponent(id), { credentials: 'same-origin' })
            .then(lireJson)
            .then(function (data) {
                if (!data.ok) throw new Error(data.error || 'Chargement impossible.');
                etat.abonnes = data.abonnes || [];
                majEtape('affStep3', etat.abonnes.length > 0);
                rendreListe();
            })
            .catch(function (err) {
                el('affTbody').innerHTML = '<tr><td colspan="5" class="text-danger small p-3">' + esc(err.message) + '</td></tr>';
            });
    });

    el('affMasquerGeo').addEventListener('change', rendreListe);
    el('affRecherche').addEventListener('input', rendreListe);

    // ---------------------------------------------------------------
    // Étape 3 : sélection des abonnés
    // ---------------------------------------------------------------

    function estGeolocalise(a) {
        return a.latitude !== null && a.longitude !== null
            && !(parseFloat(a.latitude) === 0 && parseFloat(a.longitude) === 0);
    }

    function abonnesVisibles() {
        var q = el('affRecherche').value.trim().toLowerCase();
        var masquer = el('affMasquerGeo').checked;
        return etat.abonnes.filter(function (a) {
            if (masquer && estGeolocalise(a) && etat.selection.indexOf(a.id_compteur) === -1) return false;
            if (q === '') return true;
            return (a.nom_abone || '').toLowerCase().indexOf(q) !== -1
                || (a.numero_compteur || '').toLowerCase().indexOf(q) !== -1;
        });
    }

    function rendreListe() {
        var max = etat.coords.length;
        el('affQuota').textContent = etat.selection.length + ' / ' + max;
        el('affQuota').className = 'ms-auto badge aff-quota '
            + (etat.selection.length === 0 ? 'bg-secondary'
                : (etat.selection.length === max ? 'bg-success' : 'bg-primary'));

        var visibles = abonnesVisibles();
        if (!etat.abonnes.length) {
            el('affTbody').innerHTML = '<tr><td colspan="5" class="text-muted small p-3">Choisis un réseau pour afficher ses abonnés.</td></tr>';
            majRecap();
            return;
        }
        if (!visibles.length) {
            el('affTbody').innerHTML = '<tr><td colspan="5" class="text-muted small p-3">Aucun abonné ne correspond au filtre.</td></tr>';
            majRecap();
            return;
        }

        el('affTbody').innerHTML = visibles.map(function (a) {
            var rang = etat.selection.indexOf(a.id_compteur);
            var coche = rang !== -1;
            var plein = etat.selection.length >= max && !coche;
            var geo = estGeolocalise(a);
            var coord = coche && etat.coords[rang]
                ? etat.coords[rang].lat.toFixed(6) + ', ' + etat.coords[rang].lon.toFixed(6)
                : '<span class="text-muted">—</span>';

            return '<tr class="' + (geo ? 'aff-deja' : '') + '">'
                + '<td><input type="checkbox" class="form-check-input aff-check" data-id="' + a.id_compteur + '"'
                + (coche ? ' checked' : '') + (plein ? ' disabled' : '') + '></td>'
                + '<td>' + esc(a.nom_abone) + '</td>'
                + '<td class="text-muted">' + esc(a.numero_compteur || '—') + '</td>'
                + '<td>' + (geo
                    ? '<span class="badge bg-warning text-dark">déjà positionné</span>'
                    : '<span class="badge bg-light text-muted border">sans position</span>') + '</td>'
                + '<td class="small">' + coord + '</td>'
                + '</tr>';
        }).join('');

        Array.prototype.forEach.call(document.querySelectorAll('.aff-check'), function (c) {
            c.addEventListener('change', function () {
                var id = this.getAttribute('data-id');
                var pos = etat.selection.indexOf(id);
                if (this.checked) {
                    if (pos === -1 && etat.selection.length < etat.coords.length) etat.selection.push(id);
                } else if (pos !== -1) {
                    etat.selection.splice(pos, 1);
                }
                rendreListe();
            });
        });

        majRecap();
    }

    el('affToutCocher').addEventListener('click', function () {
        var max = etat.coords.length;
        abonnesVisibles().forEach(function (a) {
            if (etat.selection.length < max && etat.selection.indexOf(a.id_compteur) === -1) {
                etat.selection.push(a.id_compteur);
            }
        });
        rendreListe();
    });

    el('affToutDecocher').addEventListener('click', function () {
        etat.selection = [];
        rendreListe();
    });

    // ---------------------------------------------------------------
    // Étape 4 : enregistrement
    // ---------------------------------------------------------------

    function majRecap() {
        var n = etat.selection.length;
        var pret = n > 0 && n <= etat.coords.length;
        majEtape('affStep4', pret);
        el('affValider').disabled = !pret;
        el('affRecap').innerHTML = pret
            ? '<strong>' + n + '</strong> abonné(s) recevront les <strong>' + n + '</strong> première(s) coordonnée(s) du fichier, '
              + 'dans l\'ordre d\'affichage de la liste.'
            : (etat.coords.length === 0
                ? 'Charge d\'abord un fichier de coordonnées.'
                : 'Coche au moins un abonné (maximum ' + etat.coords.length + ').');
    }

    el('affValider').addEventListener('click', function () {
        var affectations = etat.selection.map(function (idCompteur, i) {
            return { id_compteur: idCompteur, latitude: etat.coords[i].lat, longitude: etat.coords[i].lon };
        });
        if (!affectations.length) return;

        var bouton = this;
        bouton.disabled = true;
        el('affResultat').innerHTML = '<span class="text-muted">Enregistrement…</span>';

        var fd = new FormData();
        fd.append('action', 'affecter_coordonnees');
        fd.append('_csrf', csrf);
        fd.append('affectations', JSON.stringify(affectations));

        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(lireJson)
            .then(function (data) {
                if (!data.ok) throw new Error(data.error || 'Échec de l\'enregistrement.');

                var html = '<span class="text-success"><strong>' + data.affectes + '</strong> compteur(s) géolocalisé(s).</span>';
                if (data.ignores && data.ignores.length) {
                    html += '<ul class="text-warning small mt-1 mb-0">'
                        + data.ignores.map(function (m) { return '<li>' + esc(m) + '</li>'; }).join('')
                        + '</ul>';
                }
                html += ' <a href="?page=cartographie">Voir sur la carte</a>';
                el('affResultat').innerHTML = html;

                // Recharger le réseau pour refléter les nouvelles positions
                etat.selection = [];
                el('affReseau').dispatchEvent(new Event('change'));
            })
            .catch(function (err) {
                el('affResultat').innerHTML = '<span class="text-danger">' + esc(err.message) + '</span>';
                bouton.disabled = false;
            });
    });
})();
</script>
