<?php
header('Content-Type: application/json');
echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'raw_input' => file_get_contents('php://input'),
    'decoded' => json_decode(file_get_contents('php://input'), true),
    'post' => $_POST,
    'get' => $_GET
]);
