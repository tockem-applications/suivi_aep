function imprimer(divname){
    var printcontents=document.getElementById(divname).innerHTML;
    var originalcontents=document.body.innerHTML;
    document.body.innerHTML=printcontents;
    window.print();
    document.body.innerHTML=originalcontents;
}