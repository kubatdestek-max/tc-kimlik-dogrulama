<?php

declare(strict_types=1);

namespace KubatSoft\Kimlik;

/** Biçim ve kontrol basamaklarını sınar; kişinin kimliğini doğrulamaz. */
final class TcKimlikNo
{
    public static function gecerliMi(string $deger): bool
    {
        if (! preg_match('/^[1-9][0-9]{10}$/D', $deger)) {
            return false;
        }

        $rakamlar = array_map('intval', str_split($deger));
        $tek = $rakamlar[0] + $rakamlar[2] + $rakamlar[4] + $rakamlar[6] + $rakamlar[8];
        $cift = $rakamlar[1] + $rakamlar[3] + $rakamlar[5] + $rakamlar[7];
        $onuncu = (($tek * 7 - $cift) % 10 + 10) % 10;

        return $onuncu === $rakamlar[9]
            && (array_sum(array_slice($rakamlar, 0, 10)) % 10) === $rakamlar[10];
    }
}
