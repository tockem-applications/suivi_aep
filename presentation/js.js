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

async function handleRecouvrement_pressed_enter(event, montant, id_facture) {
    if (event.key == 'Enter') {
        console.log(montant, id_facture, event.key);
        date_recouvrement = document.getElementById('date_releve_facture_' + id_facture).value;
        const data = {
            recouvrement: true,
            'id_facture': id_facture,
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

function handleRecouvrement(montant, id_facture) {

    //console.log(montant, id_facture, event.key);
    date_recouvrement = document.getElementById('date_releve_facture_' + id_facture).value;
    const data = {
        recouvrement: true,
        'id_facture': id_facture,
        'date_versement': date_recouvrement,
        'montant_verse': montant
    };
    sentDatat('facture_t.php?recouvrement_facture=true', data);
}

function handleReleve(index, id_facture, id_abone = 0) {

    //console.log(montant, id_facture, event.key);
    // date_recouvrement = document.getElementById('date_releve_facture_' + id_facture).value;
    const ancien_index = parseFloat(document.getElementById('ancien_index' + id_facture).innerText);
    console.log(ancien_index);
    // verifyIndex(index, ancien_index, 'ancien_index'+id_facture, true);
    console.log('tout va bien');
    const data = {recouvrement: true, 'id_facture': id_facture, 'nouvel_index': index, 'id_abone': id_abone};
    if (ancien_index < index) {
        sentDatat('facture_t.php?releve_manuelle=true', data);
        document.getElementById('nouvel_index' + id_facture).classList.add('is-valid');
        document.getElementById('nouvel_index' + id_facture).classList.remove('is-invalid');
    } else {
        document.getElementById('nouvel_index' + id_facture).value = document.getElementById('ex_nouvel_index' + id_facture).value;
        document.getElementById('nouvel_index' + id_facture).classList.add('is-invalid');
        document.getElementById('nouvel_index' + id_facture).classList.remove('is-valid');
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

async function handleReleve_pressed_enter(event, index, id_facture, id_abone = 0) {
    const ancien_index = document.getElementById('ancien_index' + id_facture).value;
    // verifyIndex(index, ancien_index, 'nouvel_index'+id_facture,false);
    console.log(index);
    if (event.key === 'Enter') {
        //verifyIndex(index, ancien_index, true);
        handleReleve(index, id_facture, id_abone);
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


/*var chart = new CanvasJS.Chart("container", {
//Chart Options - Check https://canvasjs.com/docs/charts/chart-options/
    title:{
        text: "Basic Column Chart in JavaScript"
    },
    data: [{
        type: "column",
        dataPoints: [
            { label: "apple",  y: 10  },
            { label: "orange", y: 15  },
            { label: "banana", y: 25  },
            { label: "mango",  y: 30  },
            { label: "grape",  y: 28  }
        ]
    }]
});
//Render Chart
chart.render()*/


//Create Chart
// ;