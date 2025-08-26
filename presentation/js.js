// import CanvasJS from '';
//let server_address = 'localhost://fokoue/suivi_reseau/traitement';
let server_address = 'http://localhost/fokoue/suivi_reseau/traitement';

function sentDatat(uri, data, method = 'POST') {
    // console.log(data);
    // console.log(server_address+'/'+uri);
    return fetch(server_address + '/' + uri,
        {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        }).then((value) => {
        console.log(value);
    }).catch((error) => {
        console.log(error)
    })

    //console.log(response);
}

async function handleRecouvrement_pressed_enter(event, montant, id_indexes) {
    if (!validerExpressionAlgebrique(this.id))
        return false;
    montant = parseInt(eval(montant));
    if (event.key === 'Enter') {
        console.log(montant, id_facture, event.key);
        date_recouvrement = document.getElementById('date_releve_facture_' + id_indexes).value;
        const data = {
            recouvrement: true,
            'id_indexes': id_indexes,
            'date_versement': date_recouvrement,
            'montant_verse': montant
        }
        return sentDatat('facture_t.php?recouvrement_facture=true', data);
    }

}


async function HandleAboneUpdateKeyPressedEnter(event, id_abone, key, value) {
    if (event.key == 'Enter') {
        HandleAboneUpdate(id_abone, key, value);
    }

}

async function handleAboneDelete(id_abone) {
    sentDatat('facture_t.php?abone_deleting=true', {id_abone: id_abone})

}

function handleRecouvrement(montant, id_indexes, id_elemenent="") {
    if (!validerExpressionAlgebrique(id_elemenent))
        return false;
    montant = parseInt(eval(montant));
    //console.log(montant, id_facture, event.key);
    date_recouvrement = document.getElementById('date_releve_facture_' + id_indexes).value;
    const data = {
        recouvrement: true,
        'id_indexes': id_indexes,
        'date_versement': date_recouvrement,
        'montant_verse': montant
    };
    sentDatat('facture_t.php?recouvrement_facture=true', data);
}

function handleReleve(index, id_indexes, id_compteur = 0) {

    //console.log(montant, id_facture, event.key);
    // date_recouvrement = document.getElementById('date_releve_facture_' + id_facture).value;
    const ancien_index = parseFloat(document.getElementById('ancien_index' + id_indexes).innerText);
    console.log(ancien_index);
    // verifyIndex(index, ancien_index, 'ancien_index'+id_facture, true);
    console.log('tout va bien');
    const data = {recouvrement: true, 'id_indexes': id_indexes, 'nouvel_index': index, 'id_compteur': id_compteur};
    if (ancien_index <= index) {
        sentDatat('facture_t.php?releve_manuelle=true', data);
        document.getElementById('nouvel_index' + id_indexes).classList.add('is-valid');
        document.getElementById('nouvel_index' + id_indexes).classList.remove('is-invalid');
    } else {
        document.getElementById('nouvel_index' + id_indexes).value = document.getElementById('ex_nouvel_index' + id_indexes).value;
        document.getElementById('nouvel_index' + id_indexes).classList.add('is-invalid');
        document.getElementById('nouvel_index' + id_indexes).classList.remove('is-valid');
    }
    // location.reload();
}

/*function  verifyIndex(index, ancien_index, id_element,  validated = false ){
    if(validated) {
        if (index === 0.0) {
            document.getElementById(id_element).classList.remove('is-invalid');
            return '';

        }
        else if(index < ancien_index ){
            document.getElementById(id_element).classList.add('is-invalid');
            return  'is-invalid';
        }
        else{
            console.log('tout a cuit');
        }
    }
    // else{
    //     if(index === 0.0){
    //         return '';
    //     }
    // }
    return '';
}*/

async function handleReleve_pressed_enter(event, index, id_indexes, id_compteur = 0) {
    const ancien_index = document.getElementById('ancien_index' + id_indexes).value;
    // verifyIndex(index, ancien_index, 'nouvel_index'+id_facture,false);
    console.log(index);
    if (event.key === 'Enter') {
        //verifyIndex(index, ancien_index, true);
        handleReleve(index, id_indexes, id_compteur);
    }
}

async function HandleAboneUpdate(id_abone, key, value) {
    //alert('m//erci');
    const data = {'id_abone': id_abone, 'key': key, 'value': value};
    await sentDatat('abone_t.php?single_update_abone=true', data);
    location.reload();
}

function displayAllFactureChart(data, type_graphique) {
    const tab = [];
    // console.log(data);
    // alert(data);
    data.reverse().map(ligne => {
        console.log(ligne.conso);
        return {'y': parseInt(ligne.conso), 'label': ligne.mois};
    });

    displayChart(data.map(ligne => {
        return {'y': parseInt(ligne.conso), 'label': ligne.mois};
    }), 'consommation par mois', 'container1', type_graphique);

    displayChart(data.map(ligne => {
        return {'y': parseInt(ligne.nombre), 'label': ligne.mois};
    }), 'Nombre de facture par mois', 'container2', type_graphique);

    displayChart(data.map(ligne => {
        return {'y': parseInt(ligne.montant_verse), 'label': ligne.mois};
    }), 'versement par mois', 'container3', type_graphique);
}

function displayChart(data, titre, id_contenaire, chart_type = 'column') {
    const chart = new CanvasJS.Chart(id_contenaire, {
        title: {
            text: titre
        },
        data: [{
            type: chart_type,
            dataPoints: data
        }]
    });
    chart.render()
}


/*function displayDoubleLineChart(data, titre, id_contenaire, chart_type = 'column') {
    const chart = new CanvasJS.Chart(id_contenaire, {
        title: {
            text: titre
        },
        data: [{
            type: chart_type,
            dataPoints: data
        }]
    });
    chart.render()
}*/


function displayDoubleLineChart(id_container, title, serie1_data, serie2_data, chart_type1, chart_type2, graph1_name, graph2_name) {
    // alert('kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk')
    var chart = new CanvasJS.Chart(id_container);

    // chart.options.axisY = { prefix: suffix_x, suffix: suffix_y };
    chart.options.title = {text: title};

    var series1 = { //dataSeries - first quarter
        type: chart_type1,
        name: graph1_name,
        showInLegend: true
    };

    var series2 = { //dataSeries - second quarter
        type: chart_type2,
        name: graph2_name,
        showInLegend: true
    };

    chart.options.data = [];
    chart.options.data.push(series1);
    chart.options.data.push(series2);


    series1.dataPoints = JSON.parse(serie1_data);

    series2.dataPoints = JSON.parse(serie2_data);

    chart.render();
    console.log(serie2_data, serie1_data)
}

/**
 * Valide le contenu d'un input HTML comme expression algébrique
 * @param {string} inputId - L'ID de l'élément input HTML
 * @returns {boolean} - true si l'expression est valide, false sinon
 */
function validerExpressionAlgebrique(inputId) {
    const inputElement = document.getElementById(inputId);

    if (!inputElement) {
        console.error(`Element avec l'ID "${inputId}" non trouvé`);
        return false;
    }

    const expression = inputElement.value.trim();

    // Vérification que l'input n'est pas vide
    if (!expression) {
        afficherFeedback(inputElement, "Veuillez entrer une expression", false);
        return false;
    }

    // Liste blanche des caractères autorisés
    const caracteresAutorises = /^[\d+\-*/().\s]+$/;

    if (!caracteresAutorises.test(expression)) {
        afficherFeedback(inputElement, "Caractères non autorisés détectés", false);
        return false;
    }

    // Vérification de la structure syntaxique
    const structureValide = /^([-+]?(\d+|\(\s*[-+]?\d+\s*\))(\s*[-+*/]\s*[-+]?(\d+|\(\s*[-+]?\d+\s*\)))*)$/;

    if (!structureValide.test(expression)) {
        afficherFeedback(inputElement, "Structure d'expression invalide", false);
        return false;
    }

    // Vérification des parenthèses équilibrées
    let compteurParentheses = 0;

    for (const char of expression) {
        if (char === '(') compteurParentheses++;
        if (char === ')') compteurParentheses--;

        if (compteurParentheses < 0) {
            afficherFeedback(inputElement, "Parenthèses déséquilibrées", false);
            return false;
        }
    }

    if (compteurParentheses !== 0) {
        afficherFeedback(inputElement, "Parenthèses déséquilibrées", false);
        return false;
    }

    // Si tout est valide
    // afficherFeedback(inputElement, "Expression valide", true);
    afficherFeedback(inputElement, "", true);
    return true;
}

/**
 * Affiche un feedback visuel à l'utilisateur
 * @param {HTMLElement} element - L'élément input
 * @param {string} message - Message à afficher
 * @param {boolean} estValide - Si la validation a réussi
 */
function afficherFeedback(element, message, estValide) {
    // Supprime les anciens feedbacks
    const ancienFeedback = element.nextElementSibling;
    if (ancienFeedback && ancienFeedback.classList.contains('feedback-validation')) {
        ancienFeedback.remove();
    }

    // Crée un élément pour le feedback
    const feedback = document.createElement('div');
    feedback.className = `feedback-validation ${estValide ? 'valide' : 'invalide'}`;
    feedback.textContent = message;

    // Ajoute le feedback après l'input
    if(message != "")
    element.insertAdjacentElement('afterend', feedback);

    // Change le style de l'input
    element.style.borderColor = estValide ? 'green' : 'red';
}