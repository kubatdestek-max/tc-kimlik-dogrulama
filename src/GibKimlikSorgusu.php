<?php

declare(strict_types=1);

namespace KubatSoft\Kimlik;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** Tek süreçlik GİB sorgu örneği. Çok kullanıcılı entegrasyon için README'ye bakın. */
final class GibKimlikSorgusu
{
    private readonly string $base;
    private readonly Closure $transport;
    private ?string $token = null;

    /** @param null|Closure(string, array): array $transport Çevrimdışı test adaptörü. */
    public function __construct(
        private readonly string $kullanici,
        private readonly string $parola,
        private readonly bool $canli = false,
        ?Closure $transport = null,
    ) {
        if (trim($kullanici) === '' || $parola === '') {
            throw new InvalidArgumentException('GİB giriş bilgileri tanımlı değil.');
        }
        $this->base = $canli ? 'https://earsivportal.efatura.gov.tr' : 'https://earsivportaltest.efatura.gov.tr';
        $this->transport = $transport ?? self::post(...);
    }

    /** @return array{ad: string, soyad: string} */
    public function sorgula(string $numara): array
    {
        if (! TcKimlikNo::gecerliMi($numara)) {
            throw new InvalidArgumentException('T.C. kimlik numarasının biçimi veya kontrol basamakları geçersiz.');
        }
        // Başarısız bir çıkıştan sonra yeni oturum açmadan önce aynı token kapatılır.
        if ($this->token !== null) {
            $this->kapat();
        }

        $login = $this->istek('/earsiv-services/assos-login', [
            'assoscmd' => $this->canli ? 'anologin' : 'login',
            'userid' => $this->kullanici,
            'sifre' => $this->parola,
            'sifre2' => $this->parola,
            'parola' => $this->parola,
        ]);
        if (! is_string($login['token'] ?? null) || trim($login['token']) === '') {
            throw new RuntimeException('GİB oturumu açılamadı.');
        }
        $this->token = $login['token'];

        try {
            $response = $this->istek('/earsiv-services/dispatch', [
                'cmd' => 'SICIL_VEYA_MERNISTEN_BILGILERI_GETIR',
                'callid' => self::uuid(),
                'pageName' => 'RG_BASITFATURA',
                'token' => $this->token,
                'jp' => json_encode(['vknTcknn' => $numara], JSON_THROW_ON_ERROR),
            ]);
            $data = $response['data'] ?? null;
            $ad = is_array($data) ? ($data['ad'] ?? $data['adi'] ?? null) : null;
            $soyad = is_array($data) ? ($data['soyad'] ?? $data['soyadi'] ?? null) : null;
            if (! is_string($ad) || ! is_string($soyad) || trim($ad) === '' || trim($soyad) === ''
                || mb_strlen($ad) > 100 || mb_strlen($soyad) > 120) {
                throw new RuntimeException('Bu numara için kullanılabilir ad-soyad dönmedi.');
            }
            return ['ad' => trim($ad), 'soyad' => trim($soyad)];
        } finally {
            $this->kapat();
        }
    }

    public function kapat(): void
    {
        if ($this->token === null) return;
        for ($deneme = 0; $deneme < 2; $deneme++) {
            try {
                $this->istek('/earsiv-services/assos-login', ['assoscmd' => 'logout', 'token' => $this->token]);
                $this->token = null;
                return;
            } catch (Throwable) {
                // Token saklanır; yeni sorgu çıkış tamamlanmadan başlamaz.
            }
        }
        throw new RuntimeException('GİB oturumu kapatılamadı. Sonuç kabul edilmedi; kapat() ile yeniden deneyin.');
    }

    private function istek(string $path, array $form): array
    {
        try {
            $result = ($this->transport)($this->base.$path, $form);
            if (! empty($result['error']) || ! empty($result['data']['hata'])) {
                throw new RuntimeException();
            }
            return $result;
        } catch (Throwable) {
            // Harici hata gövdesi, numara, parola ve token hata mesajına taşınmaz.
            throw new RuntimeException('GİB isteği tamamlanamadı.');
        }
    }

    private static function post(string $url, array $form): array
    {
        $curl = curl_init($url);
        if ($curl === false) throw new RuntimeException();
        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($form),
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            ]);
            $body = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if (! is_string($body) || $status < 200 || $status >= 300) throw new RuntimeException();
            $result = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($result)) throw new RuntimeException();
            return $result;
        } finally {
            curl_close($curl);
        }
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
