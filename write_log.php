<?php
// write_log.php

// Vérifier si la requête est une requête POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer le message depuis le corps de la requête
    $logMessage = json_decode(file_get_contents('php://input'), true)['message'];

    // Chemin du fichier de logs
    $logFile = 'logout.log';

    // Écrire le message dans le fichier de logs
    file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);

    // Renvoyer une réponse HTTP 200 OK
    http_response_code(200);
} else {
    // Renvoyer une réponse HTTP 405 Method Not Allowed
    http_response_code(405);
    echo 'Method not allowed';
}
?>