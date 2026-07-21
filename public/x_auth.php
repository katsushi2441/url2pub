<?php
// X(Twitter) OAuth 2.0 + PKCE。ログイン確認(users.read)のみに使う。投稿はintentリンク+
// 自己申告方式(2026-07-21方針)のためtweet.write権限は要求しない。

function xa_pkce_verifier() {
    return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
}

function xa_pkce_challenge($verifier) {
    return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
}

function xa_authorize_url() {
    $verifier = xa_pkce_verifier();
    $state = bin2hex(random_bytes(16));
    $_SESSION['x_pkce_verifier'] = $verifier;
    $_SESSION['x_oauth_state'] = $state;
    $params = array(
        'response_type' => 'code',
        'client_id' => X_CLIENT_ID,
        'redirect_uri' => X_REDIRECT_URI,
        'scope' => 'users.read tweet.read',
        'state' => $state,
        'code_challenge' => xa_pkce_challenge($verifier),
        'code_challenge_method' => 'S256',
    );
    return 'https://x.com/i/oauth2/authorize?' . http_build_query($params);
}

function xa_exchange_code($code) {
    if (empty($_SESSION['x_pkce_verifier'])) {
        return array('ok' => false, 'error' => 'missing pkce verifier (session expired)');
    }
    $basicAuth = base64_encode(X_CLIENT_ID . ':' . X_CLIENT_SECRET);
    $ch = curl_init('https://api.x.com/2/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Basic ' . $basicAuth,
        'Content-Type: application/x-www-form-urlencoded',
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => X_REDIRECT_URI,
        'code_verifier' => $_SESSION['x_pkce_verifier'],
    )));
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string)$body, true);
    if ($status !== 200 || !is_array($decoded) || empty($decoded['access_token'])) {
        return array('ok' => false, 'error' => 'token exchange failed: ' . substr((string)$body, 0, 300));
    }
    return array('ok' => true, 'access_token' => $decoded['access_token']);
}

function xa_fetch_me($access_token) {
    $ch = curl_init('https://api.x.com/2/users/me');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $access_token));
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string)$body, true);
    if ($status !== 200 || !is_array($decoded) || empty($decoded['data']['username'])) {
        return array('ok' => false, 'error' => 'failed to fetch user info');
    }
    return array('ok' => true, 'username' => $decoded['data']['username'], 'name' => $decoded['data']['name'], 'id' => $decoded['data']['id']);
}
