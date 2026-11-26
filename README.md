# T.C. Kimlik Doğrulama

Kubat Soft okul uygulamasındaki T.C. kimlik numarası kontrolünden ayrılmış PHP ve JavaScript kodu; ayrıca mevcut GİB ad-soyad sorgu akışının bağımsız, tek süreçlik PHP örneği.

## Kapsam

- **Numara kontrolü:** 11 ASCII rakam, ilk rakamın sıfır olmaması, 10. ve 11. kontrol basamakları.
- **GİB sorgu örneği:** giriş → `SICIL_VEYA_MERNISTEN_BILGILERI_GETIR` → ad/soyad → çıkış. Varsayılan ortam testtir. Fatura oluşturmaz veya göndermez.
- **Laravel saklama örneği:** numaranın şifrelenmesi ve ayrı bir HMAC anahtarıyla aranabilir özetinin hazırlanması.

Kontrol basamaklarının tutması, numaranın gerçek bir kişiye ait olduğunu veya girilen ad-soyadın doğru olduğunu kanıtlamaz. GİB örneği portalın döndürdüğü ad-soyadı okur; NVI/KPS entegrasyonu, doğum tarihi doğrulaması veya resmî kimlik doğrulama belgesi değildir. GİB ad-soyad döndürmezse bu, kişinin bulunmadığı anlamına gelmez.

Bu paylaşımda gerçek kimlik bilgisi, okul veritabanı, GİB hesabı, parola, token veya sunucu yapılandırması bulunmaz. Testlerdeki numaralar sentetik kontrol basamağı örnekleridir; bir kişiye ait oldukları ileri sürülmez.

## Gereksinimler ve test

PHP 8.2+; GİB örneği için `curl`, `json`, `mbstring`. JavaScript ES modülüdür; testleri Node.js 18+ ile çalışır. Testler ek bağımlılık kurmadan çalışır:

```bash
php tests/php.php
node --test tests/tc-kimlik-no.test.mjs
```

Testler sahte HTTP adaptörü kullanır; GİB'e giriş yapmaz ve gerçek kişileri sorgulamaz. PHP sözdizimi, numara biçimi, kontrol basamakları, giriş/sorgu/çıkış sırası, ad-soyad yanıtları, bağlantı/sorgu hataları, gizli bilgilerin hata mesajına taşınmaması ve çıkış başarısızken yeni girişin engellenmesi sınanır.

## PHP: yerel numara kontrolü

```php
require 'src/TcKimlikNo.php';

use KubatSoft\Kimlik\TcKimlikNo;

$gecerli = TcKimlikNo::gecerliMi($formdanGelenNumara);
```

İşlev string bekler; boşlukları sessizce kaldırmaz. Numara matematiksel olarak geçerli olsa bile kişiye ait olduğuna dair sonuç üretmez. Composer kullanan uygulamalar `composer.json` içindeki PSR-4 eşlemesini kullanabilir.

## JavaScript: yerel numara kontrolü

```javascript
import { tcKimlikNoGecerli } from './javascript/tc-kimlik-no.mjs'

const gecerli = tcKimlikNoGecerli(formdanGelenNumara)
```

Tarayıcıdaki kontrol sunucu doğrulamasının yerine geçmez. GİB kullanıcı adı/parolası tarayıcıya gönderilmez.

## PHP: GİB ad-soyad sorgu örneği

```php
require 'src/TcKimlikNo.php';
require 'src/GibKimlikSorgusu.php';

use KubatSoft\Kimlik\GibKimlikSorgusu;

// Değerleri sunucudaki güvenli yapılandırmadan okuyun.
$gib = new GibKimlikSorgusu($kullanici, $parola); // test ortamı
$adSoyad = $gib->sorgula($formdanGelenNumara);
// ['ad' => '...', 'soyad' => '...']
```

Canlı ortam yalnız `new GibKimlikSorgusu($kullanici, $parola, canli: true)` ile açıkça seçilir. TLS sertifikası doğrulanır, yönlendirmeler izlenmez; bağlantı zaman aşımı 5, istek zaman aşımı 20 saniyedir. Başarılı girişten sonraki sorgu başarısız olsa da çıkış denenir. Çıkış iki denemede tamamlanamazsa sonuç kabul edilmez; aynı nesne yeni girişten önce eski tokenın çıkışını tekrar dener. `kapat()` ile de yeniden denenebilir.

Komut satırı örneği `examples/gib-sorgula.php` dosyasındadır. Güvenli süreç ortamında `GIB_KULLANICI`, `GIB_PAROLA`, `TC_KIMLIK_NO`, `GIB_ORTAM=test` ve işlemi gerçekten başlatmak için `GIB_SORGU_ONAYI=evet` tanımlanır. Sonra `php examples/gib-sorgula.php` çalıştırılır. `.env` dosyası kendiliğinden yüklenmez. Ad-soyad çıktısını herkese açık loglara yazmayın.

### Entegrasyon sınırları

Bu, okul uygulamasının tüm kimlik altyapısının kopyası değildir. Okuldaki kurum/rol yetkilendirmesi, hesap bazlı dağıtık kilit, şifreli kalıcı oturum günlüğü, toplu sorgu, hız sınırı ve onaylı kayıt güncellemesi bu bağımsız örnekte bulunmaz. Aynı GİB hesabını birden fazla işçi/uygulama kullanacaksa bütün kullanıcılar arasında ortak hesap kilidi ve süreç çökmesine dayanıklı şifreli oturum kurtarması entegrasyonu yapan uygulamada sağlanmalıdır. Örnek nesnesindeki token yalnız süreç belleğindedir; süreç sonlanınca kurtarma bilgisi korunmaz.

Yetkilendirilmiş bir sunucu akışında kullanın; internete açık anonim T.C. sorgu uç noktası oluşturmayın. Dönen bilgiyi mevcut öğrenci/veli kayıtlarının üzerine kendiliğinden yazmayın. Kimlik numarası ve ad-soyadın saklanması/paylaşılması bu paketin işi değildir.

GİB portal komutları sürümle değişebilir. Bu paylaşım hazırlanırken gerçek hesapla canlı sorgu kabulü yapılmamıştır; çevrimdışı test sonucu canlı portal uyumluluğu garantisi değildir. Portal: [GİB E-Arşiv](https://earsivportal.efatura.gov.tr/), [test portalı](https://earsivportaltest.efatura.gov.tr/).

## Laravel: şifreli numara ve HMAC

`examples/laravel/TcKimlikNumarasiServisi.php` ve `examples/laravel/kimlik.php`, mevcut uygulamadaki ilgili hizmet/yapılandırma örneğidir. Hizmeti `app/Services/Kisi/`, yapılandırmayı `config/kimlik.php` altına yerleştirin. Laravel'in güvenli `APP_KEY` ayarı ve ayrıca en az 32 karakterli rastgele `TC_KIMLIK_HMAC_ANAHTARI` gerekir. Bunların hiçbirini depoya eklemeyin. Bu iki örnek Composer paketi tarafından otomatik yüklenmez.

`hazirla()` şifreli numara ile HMAC-SHA256 özetini döndürür; düz numarayı sonuç dizisine eklemez. Mevcut verisi olan bir uygulamada APP_KEY veya HMAC anahtarı, kontrollü veri taşıma planı olmadan değiştirilmez.

## Paylaşım sürümü

26.11.2026 · R1. Okul uygulamasının canlı kodu, kayıtları, parolaları veya ayarları bu kaynak paylaşımı için değiştirilmemiştir.
