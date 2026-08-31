

<div class="alert alert-success position-fixed container col-auto mb-3" id="succes" style="z-index:2;display: none; justify-content: center; left: 30%; top: 7%; width: 40%; margin-top: 10px" role="alert">
    <div style="text-align: center;">
        L'operation a reussi <span style="font-weight: bold;"><?php echo isset($_GET['message']) ? htmlspecialchars($_GET['message'], ENT_QUOTES, 'UTF-8') : ''; ?></span>
    </div>
</div>
<div class="alert alert-danger position-fixed container col-auto  mb-3" id="error" style="z-index:2;display: none; justify-content: center; left: 30%; top: 7%; width: 40%; margin-top: 10px" role="alert">
    <div style="text-align: center;">
        L'opperation a echoué: <span style="font-weight: bold;"><?php echo isset($_GET['message'])? $_GET['message']:'' ?></span>
    </div>
</div>
<div id="rien"></div>