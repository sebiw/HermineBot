<?php

return [
    // General...
    'baseUrl' => "https://url.de",
    'version' => "x.xx.x",
    'appNamePattern' => "browser-Chrome:103.0.0.0-{version}",
    'deviceId' => '12345678901234567890123456789012',

    // Login...
    'userEmail' => "a@b.de",
    'userPassword' => "userPassword",
    'userPassphrase' => "userPassphrase",

    // Cipher data
    'cipherAlgo' => "aes-256-cbc",
    'cipherAlgoPaddingBlockSize' => 16,
    'rsaOaepPadding' => OPENSSL_PKCS1_OAEP_PADDING,
    'aesPadding' => OPENSSL_ZERO_PADDING | OPENSSL_RAW_DATA
];