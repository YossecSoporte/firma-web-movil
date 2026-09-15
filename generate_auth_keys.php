<?php
$path = __DIR__ . '/storage/auth_keys.json';
if (!file_exists($path)) {
    $kp = sodium_crypto_sign_keypair();
    $sec = sodium_crypto_sign_secretkey($kp);
    $pub = sodium_crypto_sign_publickey($kp);
    $out = [
        'secret_key' => sodium_bin2base64($sec, SODIUM_BASE64_VARIANT_ORIGINAL),
        'public_key' => sodium_bin2base64($pub, SODIUM_BASE64_VARIANT_ORIGINAL),
        'created_at' => date('c')
    ];
    file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT));
    echo "generated\n";
} else {
    echo "exists\n";
}
