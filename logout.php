<?php
include "bootstrap/init.php";

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid security token.');
}

logoutUser();

header('Location: ' . BASE_URL . 'auth.php');
exit();
