<?php

namespace App\Services\Kisi;

use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use RuntimeException;

class TcKimlikNumarasiServisi
{
    /** @return array{tc_kimlik_no_sifreli: string, tc_kimlik_no_ozeti: string} */
    public function hazirla(string $tcKimlikNo): array
    {
        $duzeltilmis = preg_replace('/\s+/', '', $tcKimlikNo);
        if (! is_string($duzeltilmis) || ! $this->gecerliMi($duzeltilmis)) {
            throw new InvalidArgumentException('T.C. kimlik numarası doğrulanamadı.');
        }

        $anahtar = config('kimlik.tc_hmac_anahtari');
        if (! is_string($anahtar) || strlen($anahtar) < 32) {
            throw new RuntimeException('T.C. kimlik HMAC anahtarı güvenli yapılandırmada tanımlı değil.');
        }

        return [
            'tc_kimlik_no_sifreli' => Crypt::encryptString($duzeltilmis),
            'tc_kimlik_no_ozeti' => hash_hmac('sha256', $duzeltilmis, $anahtar),
        ];
    }

    public function gecerliMi(string $deger): bool
    {
        if (! preg_match('/^[1-9][0-9]{10}$/D', $deger)) {
            return false;
        }

        $rakamlar = array_map('intval', str_split($deger));
        $tekPozisyonlar = $rakamlar[0] + $rakamlar[2] + $rakamlar[4] + $rakamlar[6] + $rakamlar[8];
        $ciftPozisyonlar = $rakamlar[1] + $rakamlar[3] + $rakamlar[5] + $rakamlar[7];

        $onuncuRakam = (($tekPozisyonlar * 7 - $ciftPozisyonlar) % 10 + 10) % 10;

        return $onuncuRakam === $rakamlar[9]
            && (array_sum(array_slice($rakamlar, 0, 10)) % 10) === $rakamlar[10];
    }
}
