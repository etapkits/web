# Etakit Web

Etkileşimli tahta kilit sisteminin web sunucusu. Tahtalar kilitliyken ekranda kısa ömürlü bir karekod gösterir; yetkili kişi bu kodu sitede okutunca sunucu o tahtaya açma izni yazar. Tahta izni kendi bağlantısından alır. Aynı site öğretmen yoklamasını ve veli bildirimlerini de yönetir.

Laravel 13 ve PHP 8.3 ile yazılmıştır. Arayüz Blade, Bootstrap ve yalın JavaScript kullanır; derleme adımı yoktur.

## Kullanıcılar

| Rol | Giriş | Yaptıkları |
| --- | --- | --- |
| Süper yönetici | `/super/giris` (e-posta ve parola) | Kurum ekler, düzenler, siler, aktif veya pasif yapar. Tahta kayıt anahtarlarını görür. |
| Kurum yöneticisi | `/idare/giris` (e-posta ve parola) | Tahtaları onaylar, açar, kilitler, kapatır. Öğretmen, öğrenci ve kilit ayarlarını yönetir. Yoklamaları görür. |
| Öğretmen | `/` (telefon ve tek kullanımlık kod) | Kendi kurumunun tahtalarını açar, kilitler, kapatır. Yoklama alır. |

Pasif kurumun yöneticisi ve öğretmenleri giriş yapamaz, açık oturumları kapanır; yeni tahta ve öğretmen kaydı alınmaz.

## Özellikler

- **Tahtalar:** Tahta ilk kez bağlanınca listede onay bekler. Onaylı tahtalar ad, durum ve son görülme zamanıyla listelenir. Bir süre bildirim göndermeyen tahta çevrimdışı görünür.
- **Karekodla açma:** Mobilde alt menüdeki **QR okut** kamerayı açar. Okunan kod, tahtanın son bildirdiği kodla eşleşirse açma izni yazılır. Kamera yalnızca HTTPS adreste çalışır.
- **Kilitleme ve kapatma:** Listeden gönderilir. Komut, tahtanın açık tuttuğu bağlantıdan iner; tahtaya dışarıdan kapı açılmaz.
- **Kilit ayarları:** Boşta kilitlenme süresi, kilit geri sayımı, bağlantı kopunca bekleme, acil durum PIN'i ve süresi, oturum süresi.
- **Öğretmenler:** Öğretmen kurum koduyla kendini kaydeder, idare onaylar. İdare doğrudan da ekleyebilir.
- **Öğrenciler:** e-Okul "Sınıf Listesi" Excel raporundan içe aktarılır; tek tek eklenip düzenlenebilir, Excel olarak dışa aktarılır.
- **Yoklama:** Öğretmen sınıf ve ders seçip gelmeyen veya geç gelen öğrencileri işaretler. İdare günlük özet ve liste görür.
- **Veli bildirimi:** Açıksa yoklamadan sonra veliye şablonlu mesaj sıraya alınır. Şablonda `{ogrenci}`, `{sinif}`, `{ders}`, `{tarih}`, `{durum}`, `{kurum}` kullanılabilir.
- **Mesaj kuyruğu:** Öğretmen giriş kodları ve veli bildirimleri `otp` tablosuna yazılır. EtaOtp istemcisi (Chrome eklentisi veya masaüstü uygulaması) bunları `api/etaotp` üzerinden alıp WhatsApp ile gönderir.

## Kurulum

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

`db:seed`, `.env` içindeki bilgilerle süper yöneticiyi, ilk kurumu ve kurum yöneticisini oluşturur. Süper yönetici bilgisi boşsa atlanır; kurum yöneticisi parolası boşsa ilk kurum ve yönetici oluşturulmaz.

Yerelde WampServer ile `public` klasörü üzerinden çalışır (`APP_URL=http://localhost/etakit/web/public`). Canlıda web kökü `public` klasörü olmalıdır.

## Ortam değişkenleri

| Değişken | Açıklama | Varsayılan |
| --- | --- | --- |
| `SUPER_ADMIN_NAME`, `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD` | Seed ile oluşturulan süper yönetici | boş |
| `FIRST_ORG_NAME`, `FIRST_ORG_CODE` | Seed ile oluşturulan ilk kurum | `İlk kurum`, `00000000` |
| `PANEL_ADMIN_NAME`, `PANEL_ADMIN_EMAIL`, `PANEL_ADMIN_PASSWORD` | İlk kurumun yöneticisi | boş parola |
| `BOARD_ENROLLMENT_KEY` | Eski tek kurumlu kurulumdan ilk kuruma taşınan tahta kayıt anahtarı | boş |
| `BOARD_QR_TTL` | Karekodun geçerlilik süresi (sn) | `25` |
| `BOARD_OFFLINE_SECONDS` | Bu süre bildirim gelmezse tahta çevrimdışı görünür (sn) | `45` |
| `BOARD_COMMAND_WAIT` | Komut bağlantısının açık tutulma süresi (sn) | `20` |
| `BOARD_COMMAND_RETRY` | Onaylanmayan komutun yeniden verilme süresi (sn) | `25` |
| `BOARD_UNLOCK_TTL` | Açma izninin geçerlilik süresi (sn) | `90` |
| `BOARD_SHUTDOWN_TTL` | Onaylanmayan kapatma komutunun düşme süresi (sn) | `600` |
| `SCHOOL_TIMEZONE` | Yoklama günü ve saatleri için saat dilimi | `Europe/Istanbul` |
| `ATTENDANCE_LESSONS` | Günlük ders sayısı | `8` |
| `ETAOTP_TOKEN` | EtaOtp istemcisinin `api/etaotp` için kullandığı anahtar | boş |

Her kurumun tahta kayıt anahtarı kurum kaydında durur ve süper yönetici listesinde görünür. Tahta paketi bu anahtarla kaydolur.

## Tahta API'si

Tahta sunucuya dışarıdan bağlanır. Kayıttan sonraki istekler `Authorization: Bearer <device_token>` taşır.

| Yöntem | Adres | Amaç |
| --- | --- | --- |
| `POST` | `/api/device/register` | Kurum kayıt anahtarıyla kaydolur, `device_token` alır |
| `POST` | `/api/device/heartbeat` | Durum bildirir (kilitli, açık, kapanıyor) |
| `POST` | `/api/device/name` | Tahta adını bildirir |
| `POST` | `/api/device/qr` | Ekrandaki güncel karekodu bildirir |
| `GET` | `/api/device/settings` | Kurumun kilit ayarlarını alır |
| `GET` | `/api/device/commands?wait=20` | Komut bekler (uzun yoklama) |
| `POST` | `/api/device/commands/{id}/ack` | Komutu aldığını onaylar |

## EtaOtp API'si

`Authorization: Bearer <ETAOTP_TOKEN>` ister.

| Yöntem | Adres | Amaç |
| --- | --- | --- |
| `POST` | `/api/etaotp/claim` | Gönderilecek sıradaki mesajı alır |
| `POST` | `/api/etaotp/{id}/sent` | Gönderildi olarak işaretler |
| `POST` | `/api/etaotp/{id}/failed` | Başarısız olarak işaretler |
| `POST` | `/api/etaotp/{id}/release` | Mesajı kuyruğa geri bırakır |

## Test

```bash
php artisan test
```

## Depodaki diğer parçalar

- `../kilit/pardus`: Pardus ETAP tahta kilidi (`.deb` paketi)
- `../kilit/windows`: Windows tahta kilidi
- `../desktop/etaotp`: WhatsApp ile mesaj gönderen Chrome eklentisi
