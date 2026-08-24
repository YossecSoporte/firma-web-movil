<?php
header('Content-Type: text/html; charset=utf-8');
$html = file_get_contents(__DIR__ . '/../index.php');
if ($html === false) {
    http_response_code(500);
    echo 'Error cargando pagina';
    exit;
}
echo $html;
