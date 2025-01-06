

class Produit{
    id;
    libele;
    quantity;
    price;
    famille;

}
let BASE_URL=  "../traitement/";
let panier = new Array();
let prod;
1
function imprimer(divname){
    var printcontents=document.getElementById(divname).innerHTML;
    var originalcontents=document.body.innerHTML;
    document.body.innerHTML=printcontents;
    window.print();
    document.body.innerHTML=originalcontents;
}

function produit_modal(id, libele, price, quantity, nom_famille, image){
    document.getElementById("mod_image").src = image;
    document.getElementById("mod_libele").innerHTML = libele;
    document.getElementById("mod_quantite").innerHTML = quantity
    document.getElementById("mod_price").innerHTML = price;
    document.getElementById("mod_famille").innerHTML = nom_famille;
    

    document.getElementById("quantity_commande").value = "";
    document.getElementById("quantity_commande").style.borderColor = "black"; // = "rgb(20, 66, 104)";
    document.getElementById("ok_commande").disabled= false;

    

    $('#exampleModal').on('show.bs.modal', event => {
        var button = $(event.relatedTarget);
        var modal = $(this);
        // Use above variables to manipulate the DOM
        
    });
    // document.getElementById("").style.fontSize = "19rem";
    // document.getElementById("quantity_commande").addEventListener('keyup', (value))

}

function ajouterPanier( produit){
    panier.push(produit);
    console.table(panier);
    
}

function afficheVani(a){
    alert("Vani "+a);
}

function save(prod, id_commande){
    jQuery.ajax({
        type: "POST",
        url: BASE_URL + "produit.php",
        
        data:{
            "id":prod.id,
            "quantity":prod.quantity,
            "id_commande":id_commande,
            "new_line": true
        },
        success: function(message){
            
        },
        Cancel: function() {
            $( this ).dialog( "close" );
        }
        }); 
       }


    function ajouterPanier( produit){
        panier.push(produit);
        document.getElementById("nombre_produit").innerHTML = panier.length;
        console.table(panier);
        save(produit, 5);
    }

    function commander(){
        if(panier.length > 0 ){
            alert(document.getElementById("modal_form"));
            document.getElementById("modal_form").submit();
            
        }
    }

    
      


 