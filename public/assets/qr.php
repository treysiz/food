<?php
$text = "http://".$_SERVER["HTTP_HOST"]."/index.php";
echo "<img src='https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=$text'>";
