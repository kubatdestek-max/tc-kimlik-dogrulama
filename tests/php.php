<?php

declare(strict_types=1);

require __DIR__.'/../src/TcKimlikNo.php';
require __DIR__.'/../src/GibKimlikSorgusu.php';

use KubatSoft\Kimlik\GibKimlikSorgusu;
use KubatSoft\Kimlik\TcKimlikNo;

$count = 0;
function check(bool $condition, string $message): void
{
    global $count;
    if (! $condition) throw new RuntimeException($message);
    $count++;
}
function fails(callable $operation, string $message): Throwable
{
    try { $operation(); } catch (Throwable $error) {
        check(str_contains($error->getMessage(), $message), 'Beklenmeyen hata türü veya mesajı.');
        return $error;
    }
    throw new RuntimeException('Hata beklenirken işlem başarılı oldu.');
}

// Yalnız sentetik kontrol basamağı örnekleri; bir kişiye ait olduğu ileri sürülmez.
foreach (['10000000146', '19000000088'] as $number) check(TcKimlikNo::gecerliMi($number), 'Geçerli kontrol basamakları reddedildi.');
foreach (['', '10000000145', '00000000000', '01234567890', '1000000014', '100000001460', '1000000014a', ' 10000000146', "10000000146\n", '１０００００００１４６', '100 00000146'] as $number) {
    check(! TcKimlikNo::gecerliMi($number), 'Geçersiz girdi kabul edildi.');
}

fails(fn () => new GibKimlikSorgusu('', 'synthetic-password'), 'giriş bilgileri');
fails(fn () => new GibKimlikSorgusu('synthetic-user', ''), 'giriş bilgileri');

$events = [];
$fake = function (string $url, array $form) use (&$events): array {
    $events[] = $form['assoscmd'] ?? $form['cmd'];
    check(str_starts_with($url, 'https://earsivportaltest.efatura.gov.tr/'), 'Varsayılan ortam test değil.');
    if (($form['assoscmd'] ?? '') === 'login') {
        check($form['userid'] === 'synthetic-user', 'Kullanıcı aktarılmadı.');
        return ['token' => 'synthetic-session'];
    }
    if (($form['assoscmd'] ?? '') === 'logout') return ['data' => true];
    check($form['token'] === 'synthetic-session', 'Oturum tokenı aktarılmadı.');
    check(json_decode($form['jp'], true)['vknTcknn'] === '10000000146', 'Sorgu numarası aktarılmadı.');
    check((bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $form['callid']), 'Çağrı kimliği UUID v4 değil.');
    return ['data' => ['ad' => ' Örnek ', 'soyad' => ' Kişi ']];
};
$client = new GibKimlikSorgusu('synthetic-user', 'synthetic-password', transport: $fake);
check($client->sorgula('10000000146') === ['ad' => 'Örnek', 'soyad' => 'Kişi'], 'Ad-soyad sonucu farklı.');
check($events === ['login', 'SICIL_VEYA_MERNISTEN_BILGILERI_GETIR', 'logout'], 'Giriş/sorgu/çıkış sırası farklı.');
$client->kapat();
check(count($events) === 3, 'Kapalı oturumda gereksiz istek yapıldı.');
$events = [];
fails(fn () => $client->sorgula('10000000145'), 'kontrol basamakları');
check($events === [], 'Geçersiz numarayla dış istek yapıldı.');

foreach ([['data' => []], ['data' => ['ad' => ['malformed'], 'soyad' => 'Örnek']], ['data' => ['ad' => str_repeat('a', 101), 'soyad' => 'Örnek']]] as $payload) {
    $closed = 0;
    $client = new GibKimlikSorgusu('synthetic-user', 'synthetic-password', transport: function ($url, $form) use ($payload, &$closed) {
        if (($form['assoscmd'] ?? '') === 'login') return ['token' => 'synthetic-session'];
        if (($form['assoscmd'] ?? '') === 'logout') { $closed++; return ['data' => true]; }
        return $payload;
    });
    fails(fn () => $client->sorgula('10000000146'), 'ad-soyad');
    check($closed === 1, 'Geçersiz sonuçtan sonra oturum kapanmadı.');
}

foreach (['portal_error', 'connection_error'] as $failure) {
    $closed = 0;
    $client = new GibKimlikSorgusu('synthetic-user', 'synthetic-password', transport: function ($url, $form) use ($failure, &$closed) {
        if (($form['assoscmd'] ?? '') === 'login') return ['token' => 'synthetic-session'];
        if (($form['assoscmd'] ?? '') === 'logout') { $closed++; return ['data' => true]; }
        if ($failure === 'connection_error') throw new RuntimeException('synthetic-session synthetic-password 10000000146');
        return ['error' => 'synthetic-session synthetic-password 10000000146'];
    });
    $error = fails(fn () => $client->sorgula('10000000146'), 'isteği tamamlanamadı');
    check($closed === 1, 'Sorgu hatasında çıkış yapılmadı.');
    check($error->getPrevious() === null && ! str_contains($error->getMessage(), 'synthetic-') && ! str_contains($error->getMessage(), '10000000146'), 'Hata mesajında özel bilgi var.');
}

$logoutFails = true;
$events = [];
$client = new GibKimlikSorgusu('synthetic-user', 'synthetic-password', canli: true, transport: function ($url, $form) use (&$logoutFails, &$events) {
    check(str_starts_with($url, 'https://earsivportal.efatura.gov.tr/'), 'Canlı ortam URL hatası.');
    $events[] = $form['assoscmd'] ?? $form['cmd'];
    if (($form['assoscmd'] ?? '') === 'anologin') return ['token' => 'synthetic-session'];
    if (($form['assoscmd'] ?? '') === 'logout') return $logoutFails ? ['data' => ['hata' => 'synthetic']] : ['data' => true];
    return ['data' => ['adi' => 'Örnek', 'soyadi' => 'Kişi']];
});
fails(fn () => $client->sorgula('10000000146'), 'kapatılamadı');
check(count(array_filter($events, fn ($v) => $v === 'logout')) === 2, 'Çıkış iki kez denenmedi.');
fails(fn () => $client->sorgula('10000000146'), 'kapatılamadı');
check(count(array_filter($events, fn ($v) => $v === 'anologin')) === 1, 'Eski oturum kapanmadan yeni giriş yapıldı.');
$logoutFails = false;
check($client->sorgula('10000000146') === ['ad' => 'Örnek', 'soyad' => 'Kişi'], 'Bekleyen çıkış kurtarılamadı.');
check(count(array_filter($events, fn ($v) => $v === 'anologin')) === 2, 'Kurtarma sonrası oturum açılmadı.');

$requests = 0;
$client = new GibKimlikSorgusu('synthetic-user', 'synthetic-password', transport: function () use (&$requests) { $requests++; return ['error' => 'synthetic login failure']; });
fails(fn () => $client->sorgula('10000000146'), 'isteği tamamlanamadı');
check($requests === 1, 'Başarısız girişten sonra sorgu yapıldı.');

echo 'PHP: ', $count, ' doğrulama geçti. Harici GİB isteği yapılmadı.', PHP_EOL;
