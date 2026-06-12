<?php
require_once 'config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
$name = isset($data['name']) && is_string($data['name']) ? trim($data['name']) : '';
$email = isset($data['email']) && is_string($data['email']) ? trim($data['email']) : '';
$message = isset($data['message']) && is_string($data['message']) ? trim($data['message']) : '';
if ($name === '' || $email === '' || $message === '') { http_response_code(400); echo json_encode(['ok'=>false]); exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo json_encode(['ok'=>false]); exit; }
if (DISCORD_WEBHOOK_URL === '') { http_response_code(500); echo json_encode(['ok'=>false]); exit; }
$payload = [
    'content' => null,
    'embeds' => [[
        'title' => 'New Contact Form Message',
        'fields' => [
            ['name'=>'Name','value'=>$name,'inline'=>true],
            ['name'=>'Email','value'=>$email,'inline'=>true],
            ['name'=>'Message','value'=>$message,'inline'=>false]
        ]
    ]]
];
$ch = curl_init(DISCORD_WEBHOOK_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ch = null;
echo json_encode(['ok'=>($code>=200 && $code<300)]);
