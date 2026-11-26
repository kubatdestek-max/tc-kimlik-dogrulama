/** Biçim ve kontrol basamaklarını sınar; kişinin kimliğini doğrulamaz. */
export function tcKimlikNoGecerli(deger) {
  if (typeof deger !== 'string' || !/^[1-9][0-9]{10}$/.test(deger) || deger.length !== 11) return false
  const rakamlar = [...deger].map(Number)
  const tek = rakamlar[0] + rakamlar[2] + rakamlar[4] + rakamlar[6] + rakamlar[8]
  const cift = rakamlar[1] + rakamlar[3] + rakamlar[5] + rakamlar[7]
  const onuncu = ((tek * 7 - cift) % 10 + 10) % 10
  return onuncu === rakamlar[9]
    && rakamlar.slice(0, 10).reduce((toplam, rakam) => toplam + rakam, 0) % 10 === rakamlar[10]
}
