<?php
session_start();
require_once 'vendor/autoload.php';

// Path ke file kredensial dan file token
$credentials_path = 'credentials/credential_cai2025_btp1.json';
$token_path = 'credentials/token.json';

$client = new Google\Client();
$client->setAuthConfig($credentials_path);
$client->setRedirectUri('http://' . $_SERVER['HTTP_HOST'] . '/cai25/oauth2callback.php'); // Sesuaikan path jika perlu
$client->setAccessType('offline'); // Wajib untuk mendapatkan refresh token
$client->setPrompt('select_account consent'); // Memaksa persetujuan untuk refresh token

// Jika ada parameter 'code' di URL, berarti pengguna baru saja login
if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token);

    // Simpan token ke file
    if (array_key_exists('error', $token)) {
        throw new Exception(join(', ', $token));
    }

    // Simpan seluruh token, termasuk refresh token
    file_put_contents($token_path, json_encode($client->getAccessToken()));

    // Arahkan kembali ke halaman utama pembuatan surat
    header('Location: admin/admin?page=master/surat_perizinan');
    exit();
} else {
    echo "Kode otorisasi tidak ditemukan.";
}
