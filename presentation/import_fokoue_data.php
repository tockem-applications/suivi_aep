<div class="container mt-5">
    <h1 class="text-center mb-4">Importer un fichier CSV</h1>
    <div class="card p-4 shadow-sm">
        <form action="traitement/fokoue_data_t.php" method="post" enctype="multipart/form-data" id="uploadForm">
            <div class="mb-3">
                <label for="fileInput" class="form-label">Choisir un fichier CSV</label>
                <input type="file" class="form-control" id="fileInput" name="file" accept=".csv" required>
                <small class="form-text text-muted">Fichier contenant les données de consommation.</small>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="moisSocle" class="form-label">Mois socle</label>
                    <input type="month" class="form-control" id="moisSocle" name="moisSocle" value="2020-01" required>
                    <small class="form-text text-muted">Mois de référence pour les données de base.</small>
                </div>
                <div class="col-md-6">
                    <label for="moisActuel" class="form-label">Mois actuel</label>
                    <input type="month" class="form-control" id="moisActuel" name="moisActuel" value="2025-08" required>
                    <small class="form-text text-muted">Mois courant pour les données actuelles.</small>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <h5>Tarifs socle</h5>
                    <div class="mb-2">
                        <label for="prixM3Socle" class="form-label">Prix m³ d'eau (FCFA)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="prixM3Socle" name="prixM3Socle" value="300" required>
                    </div>
                    <div class="mb-2">
                        <label for="entretienSocle" class="form-label">Entretien compteur (FCFA)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="entretienSocle" name="entretienSocle" value="0" required>
                    </div>
                    <div>
                        <label for="tvaSocle" class="form-label">TVA (%)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="tvaSocle" name="tvaSocle" value="0" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <h5>Tarifs actuels</h5>
                    <div class="mb-2">
                        <label for="prixM3Actuel" class="form-label">Prix m³ d'eau (FCFA)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="prixM3Actuel" name="prixM3Actuel" value="300" required>
                    </div>
                    <div class="mb-2">
                        <label for="entretienActuel" class="form-label">Entretien compteur (FCFA)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="entretienActuel" name="entretienActuel" value="500" required>
                    </div>
                    <div>
                        <label for="tvaActuel" class="form-label">TVA (%)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="strator="tvaActuel" name="tvaActuel" value="0" required>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Envoyer</button>
        </form>
    </div>
</div>
<script>
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        const moisSocle = document.getElementById('moisSocle').value;
        const moisActuel = document.getElementById('moisActuel').value;
        if (moisSocle >= moisActuel) {
            e.preventDefault();
            alert('Le mois socle doit être antérieur au mois actuel.');
        }
    });
</script>