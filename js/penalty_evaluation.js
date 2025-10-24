// Script pour l'analyse avant penalite
function loadPenaltyAnalysis(compteurId) {
    const content = document.getElementById("penaltyAnalysisContent" + compteurId);
    
    if (content) {
        // Charger l'analyse de la penalite
        loadPenaltyAnalysisData(compteurId, content);
    }
    
    function loadPenaltyAnalysisData(compteurId, contentDiv) {
        // Afficher le spinner
        contentDiv.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-info" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p class="mt-2">Analyse de l'historique de paiement...</p>
            </div>
        `;
        
        // Faire une requete AJAX pour recuperer les donnees d'analyse
        fetch("traitement/abone_t.php?action=get_penalty_evaluation&id_compteur=" + compteurId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        displayPenaltyAnalysis(data, contentDiv);
                    } else {
                        contentDiv.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                Erreur lors du chargement des donnees: ${data.message}
                            </div>
                        `;
                    }
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    contentDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            Erreur de format de reponse: ${parseError.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                contentDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Erreur de connexion: ${error.message}
                    </div>
                `;
            });
    }
    
    function displayPenaltyAnalysis(data, contentDiv) {
        // Calculer les données pour les graphiques
        const monthsData = data.chart_labels || [];
        const facturedData = data.chart_factured || [];
        const paidData = data.chart_paid || [];
        const remainingData = data.chart_remaining || [];
        
        // Utiliser la valeur calculée par l'API SQL
        const consecutiveUnpaidMonths = data.consecutive_unpaid || 0;
        
        // Calculer la dette totale
        const totalDebt = remainingData.reduce((sum, debt) => sum + (debt || 0), 0);
        const penaltyAmount = 2500;
        
        contentDiv.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-calendar-alt"></i> 
                                Analyse des mois non payés
                            </h6>
                        </div>
                        <div class="card-body" style="height: 280px; overflow: hidden;">
                            <div style="height: 200px;">
                                <canvas id="monthsChart${compteurId}"></canvas>
                            </div>
                               <div class="mt-2 text-center">
                                   <span class="badge bg-info fs-6" id="unpaidMonthsBadge${compteurId}">
                                       ${consecutiveUnpaidMonths} mois non payés consécutifs
                                   </span>
                               </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0">
                                <i class="fas fa-balance-scale"></i> 
                                Comparatif Dette vs Pénalité
                            </h6>
                        </div>
                        <div class="card-body" style="height: 280px; overflow: hidden;">
                            <div style="height: 200px;">
                                <canvas id="debtChart${compteurId}"></canvas>
                            </div>
                            <div class="mt-2 text-center">
                                <span class="badge ${totalDebt > penaltyAmount ? 'bg-danger' : 'bg-success'} fs-6">
                                    Dette: ${new Intl.NumberFormat('fr-FR').format(totalDebt)} FCFA
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-lightbulb"></i> 
                                Recommandation
                            </h6>
                        </div>
                        <div class="card-body">
                            ${getRecommendation(consecutiveUnpaidMonths, totalDebt, penaltyAmount)}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Creer les graphiques
        createMonthsChart(monthsData, facturedData, paidData, consecutiveUnpaidMonths, 'monthsChart' + compteurId);
        createDebtChart(totalDebt, penaltyAmount, 'debtChart' + compteurId);
    }
    
    function getRecommendation(consecutiveUnpaid, totalDebt, penaltyAmount) {
        if (consecutiveUnpaid === 0) {
            return `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <strong>Excellent !</strong> L'abonné est à jour dans ses paiements. 
                    Aucune pénalité n'est justifiée.
                </div>
            `;
        } else if (consecutiveUnpaid === 1) {
            return `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Attention :</strong> 1 mois de retard. 
                    ${totalDebt > penaltyAmount ? 
                        'La dette (' + new Intl.NumberFormat('fr-FR').format(totalDebt) + ' FCFA) dépasse la pénalité (' + penaltyAmount + ' FCFA). Une pénalité peut être justifiée.' :
                        'La dette (' + new Intl.NumberFormat('fr-FR').format(totalDebt) + ' FCFA) est inférieure à la pénalité (' + penaltyAmount + ' FCFA). Éviter la pénalité dans ce contexte rural.'
                    }
                </div>
            `;
        } else if (consecutiveUnpaid <= 3) {
            return `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Modéré :</strong> ${consecutiveUnpaid} mois de retard. 
                    ${totalDebt > penaltyAmount ? 
                        'La dette (' + new Intl.NumberFormat('fr-FR').format(totalDebt) + ' FCFA) dépasse la pénalité (' + penaltyAmount + ' FCFA). Une pénalité peut être appliquée.' :
                        'La dette (' + new Intl.NumberFormat('fr-FR').format(totalDebt) + ' FCFA) est inférieure à la pénalité (' + penaltyAmount + ' FCFA). Considérer le contexte local avant de pénaliser.'
                    }
                </div>
            `;
        } else {
            return `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>Préoccupant :</strong> ${consecutiveUnpaid} mois de retard. 
                    ${totalDebt > penaltyAmount ? 
                        'La dette (' + new Intl.NumberFormat('fr-FR').format(totalDebt) + ' FCFA) dépasse la pénalité (' + penaltyAmount + ' FCFA). Une pénalité est recommandée.' :
                        'Même si la dette (' + new Intl.NumberFormat('fr-FR').format(totalDebt) + ' FCFA) est inférieure à la pénalité (' + penaltyAmount + ' FCFA), le nombre de mois de retard justifie une action.'
                    }
                </div>
            `;
        }
    }
    
    function createMonthsChart(monthsData, facturedData, paidData, consecutiveUnpaid, canvasId) {
        const ctx = document.getElementById(canvasId).getContext("2d");
        
        // Créer un graphique avec :
        // - Barre horizontale à hauteur 1 (seuil de tolérance)
        // - Une seule barre verticale de hauteur = nombre de mois non payés
        
        // Créer les données pour les barres
        const thresholdData = [1]; // Barre horizontale à hauteur 1
        const unpaidMonthsData = [consecutiveUnpaid]; // Barre verticale de hauteur = nombre de mois non payés
        const labels = ["Analyse des mois non payés"];
        
        new Chart(ctx, {
            type: "bar",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: "Seuil de tolérance (1 mois)",
                        data: thresholdData,
                        backgroundColor: "rgba(255, 193, 7, 0.8)",
                        borderColor: "rgba(255, 193, 7, 1)",
                        borderWidth: 3,
                        type: "bar"
                    },
                    {
                        label: "Mois non payés consécutifs",
                        data: unpaidMonthsData,
                        backgroundColor: "rgba(220, 53, 69, 0.9)",
                        borderColor: "rgba(220, 53, 69, 1)",
                        borderWidth: 2,
                        type: "bar"
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: Math.max(2, consecutiveUnpaid + 1),
                        ticks: {
                            stepSize: 1,
                            callback: function(value) {
                                return value === 1 ? "Seuil (1 mois)" : value + " mois";
                            }
                        },
                        grid: {
                            color: function(context) {
                                if (context.tick.value === 1) {
                                    return 'rgba(255, 193, 7, 0.8)'; // Ligne de grille jaune à hauteur 1
                                }
                                return 'rgba(0, 0, 0, 0.1)';
                            },
                            lineWidth: function(context) {
                                if (context.tick.value === 1) {
                                    return 3; // Ligne plus épaisse à hauteur 1
                                }
                                return 1;
                            }
                        }
                    },
                    x: {
                        display: false // Masquer l'axe X car on n'a qu'une seule barre
                    }
                },
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            usePointStyle: true,
                            padding: 10
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return "Seuil de tolérance: 1 mois";
                                } else {
                                    return context.parsed.y > 0 ? 
                                        `${context.parsed.y} mois non payés consécutifs` : 
                                        "Aucun mois non payé";
                                }
                            }
                        }
                    }
                }
            },
            plugins: [{
                id: 'updateBadge',
                afterDatasetsDraw: function(chart) {
                    // Mettre à jour le badge avec le nombre réel de mois non payés
                    const badgeId = 'unpaidMonthsBadge' + canvasId.replace('monthsChart', '');
                    const badge = document.getElementById(badgeId);
                    if (badge) {
                        badge.textContent = consecutiveUnpaid + ' mois non payés consécutifs';
                        badge.className = consecutiveUnpaid > 0 ? 'badge bg-danger fs-6' : 'badge bg-success fs-6';
                    }
                }
            }]
        });
    }
    
    function createDebtChart(totalDebt, penaltyAmount, canvasId) {
        const ctx = document.getElementById(canvasId).getContext("2d");
        
        new Chart(ctx, {
            type: "bar",
            data: {
                labels: ["Dette totale", "Montant pénalité"],
                datasets: [{
                    label: "Montant (FCFA)",
                    data: [totalDebt, penaltyAmount],
                    backgroundColor: [
                        totalDebt > penaltyAmount ? "rgba(220, 53, 69, 0.8)" : "rgba(40, 167, 69, 0.8)",
                        "rgba(255, 193, 7, 0.8)"
                    ],
                    borderColor: [
                        totalDebt > penaltyAmount ? "rgba(220, 53, 69, 1)" : "rgba(40, 167, 69, 1)",
                        "rgba(255, 193, 7, 1)"
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat("fr-FR").format(value) + " FCFA";
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ": " + new Intl.NumberFormat("fr-FR").format(context.parsed.y) + " FCFA";
                            }
                        }
                    }
                }
            }
        });
    }
    
    function applyPenalty(compteurId, moisActifId) {
        const montant = prompt("Montant de la penalite (FCFA):", "2500");
        if (montant && !isNaN(montant) && montant > 0) {
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "traitement/abone_t.php";
            
            const actionInput = document.createElement("input");
            actionInput.type = "hidden";
            actionInput.name = "action";
            actionInput.value = "apply_penalite";
            
            const compteurInput = document.createElement("input");
            compteurInput.type = "hidden";
            compteurInput.name = "id_compteur";
            compteurInput.value = compteurId;
            
            const moisInput = document.createElement("input");
            moisInput.type = "hidden";
            moisInput.name = "id_mois";
            moisInput.value = moisActifId;
            
            const montantInput = document.createElement("input");
            montantInput.type = "hidden";
            montantInput.name = "penalite_montant";
            montantInput.value = montant;
            
            form.appendChild(actionInput);
            form.appendChild(compteurInput);
            form.appendChild(moisInput);
            form.appendChild(montantInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
}
