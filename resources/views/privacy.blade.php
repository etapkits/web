@extends('layouts.app')

@section('title', 'Gizlilik politikası · Etakit')
@section('robots', 'index,follow')

@section('content')
    <main class="px-3 py-4 py-md-5">
        <article class="privacy-wrap mx-auto">
            <p class="eyebrow mb-2">Etakit</p>
            <h1 class="h3 mb-2">Gizlilik politikası</h1>
            <p class="text-secondary mb-4">Son güncelleme: 22 Eylül 2026</p>

            <div class="privacy-body">
                <p>
                    Bu metin, Teknovip Yazilim Etakit etkileşimli tahta kilidi hizmetinin kişisel verileri nasıl işlediğini açıklar.
                    Hizmeti kullanarak bu politikayı okuduğunuzu kabul etmiş sayılırsınız.
                </p>

                <h2 class="h5 mt-4 mb-2">1. Veri sorumlusu</h2>
                <p>
                    Etakit, okulların ve eğitim kurumlarının etkileşimli tahtalarını uzaktan kilitlemesi,
                    açması ve yönetmesi için bir yazılım hizmetidir. Kişisel verileriniz, kurumunuzun
                    Etakit kullanımına bağlı olarak hizmet sunumu amacıyla işlenir.
                </p>

                <h2 class="h5 mt-4 mb-2">2. İşlenen veriler</h2>
                <p>Hizmet kapsamında şu kategorilerde veriler işlenebilir:</p>
                <ul>
                    <li><strong>Kurum yöneticisi:</strong> ad, e-posta adresi, parola (şifreli), kurum bilgisi</li>
                    <li><strong>Öğretmen:</strong> ad, soyad, telefon numarası, onay durumu</li>
                    <li><strong>Giriş doğrulama:</strong> SMS/OTP kodları ve ilgili oturum kayıtları</li>
                    <li><strong>Cihaz / tahta:</strong> tahta adı, cihaz tanımlayıcıları, bağlantı ve kilit durumu, karekod oturumları</li>
                    <li><strong>Teknik günlükler:</strong> güvenlik, hata ayıklama ve hizmet sürekliliği için sınırlı erişim/istemci kayıtları</li>
                </ul>
                <p class="mb-0">Öğrenci kişisel verisi toplanmaz.</p>

                <h2 class="h5 mt-4 mb-2">3. İşleme amaçları</h2>
                <ul>
                    <li>Kimlik doğrulama ve yetkilendirme (e-posta/parola veya telefon OTP)</li>
                    <li>Tahta kilidi, açma, kapatma ve kurum paneli yönetimi</li>
                    <li>Kurum izolasyonu ve yetkisiz erişimin önlenmesi</li>
                    <li>Hizmet güvenliği, kötüye kullanımın tespiti ve destek</li>
                    <li>Yasal yükümlülüklerin yerine getirilmesi</li>
                </ul>

                <h2 class="h5 mt-4 mb-2">4. Hukuki sebep</h2>
                <p>
                    Veriler, 6698 sayılı Kişisel Verilerin Korunması Kanunu (KVKK) kapsamında;
                    sözleşmenin ifası, meşru menfaat (güvenlik ve hizmet işletimi) ve ilgili
                    mevzuattan doğan yükümlülükler çerçevesinde işlenir.
                </p>

                <h2 class="h5 mt-4 mb-2">5. Paylaşım</h2>
                <p>
                    Verileriniz, kurumunuzun paneli içinde yalnızca yetkili yöneticiler ve onaylı
                    öğretmenlerle sınırlı olarak görünür. SMS doğrulama için gerekli telefon numarası,
                    OTP iletimini sağlayan hizmet sağlayıcıya iletilebilir. Yasal zorunluluk olmadıkça
                    veriler üçüncü taraflara satılmaz veya pazarlama amacıyla paylaşılmaz.
                </p>

                <h2 class="h5 mt-4 mb-2">6. Saklama süresi</h2>
                <p>
                    Hesap ve kurum verileri, kurumunuz Etakit’i kullandığı sürece saklanır.
                    OTP kodları kısa süreli tutulur. Kurum hesabı kapatıldığında veya silme talebi
                    üzerine, yasal saklama zorunlulukları saklı kalmak kaydıyla veriler silinir
                    veya anonimleştirilir.
                </p>

                <h2 class="h5 mt-4 mb-2">7. Güvenlik</h2>
                <p>
                    Parolalar hash’lenerek saklanır. Oturumlar kimlik doğrulamalıdır; paneller
                    kurum bazında ayrılır. İletişimde mümkün olduğunca güvenli bağlantı (HTTPS/TLS)
                    kullanılır. Yine de hiçbir sistemin mutlak güvenlik garantisi yoktur.
                </p>

                <h2 class="h5 mt-4 mb-2">8. Haklarınız</h2>
                <p>KVKK md. 11 kapsamında başvurarak:</p>
                <ul>
                    <li>kişisel verilerinizin işlenip işlenmediğini öğrenme,</li>
                    <li>işlenmişse buna ilişkin bilgi talep etme,</li>
                    <li>amacına uygun kullanılıp kullanılmadığını öğrenme,</li>
                    <li>düzeltme, silme veya yok edilmesini isteme,</li>
                    <li>işlemeye itiraz etme</li>
                </ul>
                <p class="mb-0">haklarına sahipsiniz. Taleplerinizi kurum yöneticiniz veya Etakit destek kanalı üzerinden iletebilirsiniz.</p>

                <h2 class="h5 mt-4 mb-2">9. Çerezler</h2>
                <p>
                    Oturum güvenliği için zorunlu oturum çerezleri kullanılır. Reklam veya
                    izleme amaçlı üçüncü taraf çerezleri kullanılmaz.
                </p>

                <h2 class="h5 mt-4 mb-2">10. Değişiklikler</h2>
                <p>
                    Bu politika güncellenebilir. Önemli değişikliklerde bu sayfadaki tarih yenilenir.
                    Güncel metin her zaman <a href="{{ route('privacy') }}">{{ url('/privacy') }}</a> adresinde yayınlanır.
                </p>
            </div>

            <p class="fine small mt-4 mb-0">
                <a href="{{ route('home') }}">Ana sayfa</a>
                ·
                <a href="{{ route('terms') }}">Kullanım koşulları</a>
            </p>
        </article>
    </main>
@endsection
