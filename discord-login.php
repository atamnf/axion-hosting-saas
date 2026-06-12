<?php
require_once 'config.php';

$next = $_GET['next'] ?? '';
if (is_string($next) && $next !== '') {
    if (strpos($next, '://') === false && $next[0] === '/') {
        $_SESSION['post_login_redirect'] = $next;
    }
}

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$params = [
    'client_id' => DISCORD_CLIENT_ID,
    'redirect_uri' => DISCORD_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'identify email',
    'state' => $state
];

$authUrl = 'https://discord.com/api/oauth2/authorize?' . http_build_query($params);

header('Location: ' . $authUrl);
exit;
?>
