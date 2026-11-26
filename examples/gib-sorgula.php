<?php

declare(strict_types=1);

require __DIR__.'/../src/TcKimlikNo.php';
require __DIR__.'/../src/GibKimlikSorgusu.php';

use KubatSoft\Kimlik\GibKimlikSorgusu;

// Kimlik numarası ve sırlar komut argümanlarına veya kaynak koduna yazılmaz.
if (getenv('GIB_SORGU_ONAYI') !== 'evet') {
    fwrite(STDERR, "GİB sorgusu çalıştırılmadı. Önce README'deki kullanım adımlarını inceleyin.\n");
    exit(1);
}

try {
    $client = new GibKimlikSorgusu(
        getenv('GIB_KULLANICI') ?: '',
        getenv('GIB_PAROLA') ?: '',
        getenv('GIB_ORTAM') === 'canli',
    );
    $sonuc = $client->sorgula(getenv('TC_KIMLIK_NO') ?: '');
    // Ad-soyad kişisel veridir; çıktıyı herkese açık loglara yönlendirmeyin.
    echo json_encode($sonuc, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
