

//alert('erick Tsafack');
const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
const tooltpList = [...tooltipTriggerList].map(tooltipTriggerElement=>new bootstrap.Tooltip(tooltipTriggerElement));



function imprimer() {
    var contenu = document.getElementById("a_imprimer").innerHTML;
    var header = document.getElementsByTagName("head")[0].innerHTML;
    var fenetre_impression = window.open('', '', 'height=900, width=1000');
    fenetre_impression.document.write("<html lang='fr'>" +header+contenu+ "</html>" + "");
    fenetre_impression.focus();
    fenetre_impression.print();
    fenetre_impression.focus();

}


function recherche(le_input) {
    console.log('je veux savoir  5555 ' + le_input);
    if(!document.getElementById(le_input)) {
        return;
    }

    document.getElementById(le_input).addEventListener('input', (event) => {
        let val = event.target.value;
        // console.log('bonjour');
        const a = document.getElementsByClassName('table table-striped');
        if (a.length > 0) {
            const tab = a[0];
            const bal = tab.getElementsByTagName('tr');
            let ok = false;
            for (let i = 1; i < bal.length; i++) {
                const tr = bal[i];
                let y = 0
                ok = false;
                let tabTh = tr.getElementsByTagName(('td'))
                for (; y < tabTh.length; y++) {
                    let tex = tabTh[y].innerText;
                    if (tex.toUpperCase().includes(val.toUpperCase())) {
                        ok = true;
                        tr.style.display = '';
                        break;
                    }
                }
                if (!ok) {
                    tr.style.display = 'none';
                    ok = false;
                }
            }
        }
    })
}
recherche('le_input');
// animerCard();
//recherche('le_input_cle');








