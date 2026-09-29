@extends('layouts.app')

@section('title', 'Kullanım koşulları · Etakit')
@section('robots', 'index,follow')

@section('content')
    <main class="px-3 py-4 py-md-5">
        <article class="privacy-wrap mx-auto">
            <p class="eyebrow mb-2">Etakit</p>
            <h1 class="h3 mb-2">Kullanım koşulları</h1>
            <p class="text-secondary mb-4">Son güncelleme: 22 Eylül 2026</p>

            <div class="privacy-body">
                <p>
                    Bu koşullar, Teknovip Yazilim Etakit etkileşimli tahta kilidi hizmetinin kullanımını düzenler.
                    Hizmete erişerek bu koşulları kabul etmiş sayılırsınız.
                </p>

                <h2 class="h5 mt-4 mb-2">1. Hizmetin kapsamı</h2>
                <p>
                    Etakit; eğitim kurumlarının etkileşimli tahtalarını kilitleme, açma ve yönetme
                    amacıyla sunulan bir yazılım hizmetidir. Hizmet kurum hesapları, öğretmen
                    hesapları ve tahta cihaz yazılımları üzerinden kullanılır.
                </p>

                <h2 class="h5 mt-4 mb-2">2. Hesaplar ve yetkiler</h2>
                <ul>
                    <li>Kurum yöneticileri e-posta ile giriş yapar; öğretmenler telefon doğrulaması ile giriş yapar.</li>
                    <li>Öğretmen kayıtları kurum onayı olmadan tahta işlemleri yapamaz.</li>
                    <li>Hesap bilgilerinin gizliliği ve doğru kullanımı hesap sahibinin sorumluluğundadır.</li>
                    <li>Yetkisiz erişim, paylaşım veya kötüye kullanım hesabın askıya alınmasına yol açabilir.</li>
                </ul>

                <h2 class="h5 mt-4 mb-2">3. Kabul edilebilir kullanım</h2>
                <p>Hizmet yalnızca eğitim ve kurum içi yönetim amaçlarıyla kullanılabilir. Şunlar yasaktır:</p>
                <ul>
                    <li>sistemi bozmaya, aşırı yüklemeye veya güvenlik açıklarını istismar etmeye yönelik işlemler</li>
                    <li>başkasına ait hesap, telefon veya kurum kodu ile yetkisiz giriş</li>
                    <li>tahta cihazlarının eğitim dışı zararlı biçimde kontrol edilmesi</li>
                </ul>

                <h2 class="h5 mt-4 mb-2">4. Veriler ve gizlilik</h2>
                <p>
                    Kişisel verilerin işlenmesi
                    <a href="{{ route('privacy') }}">gizlilik politikası</a>
                    kapsamında açıklanır. Hizmeti kullanarak bu politikayı da kabul etmiş olursunuz.
                </p>

                <h2 class="h5 mt-4 mb-2">5. Hizmet sürekliliği</h2>
                <p>
                    Etakit mümkün olduğunca kesintisiz hizmet sunmayı hedefler; ancak bakım,
                    altyapı veya üçüncü taraf (ör. SMS doğrulama) kaynaklı kesintiler olabilir.
                    Bu durumlardan doğabilecek dolaylı zararlardan sorumluluk kabul edilmez.
                </p>

                <h2 class="h5 mt-4 mb-2">6. Değişiklikler</h2>
                <p>
                    Bu koşullar güncellenebilir. Güncel metin
                    <a href="{{ route('terms') }}">{{ url('/terms') }}</a>
                    adresinde yayınlanır. Önemli değişikliklerde tarih alanı yenilenir.
                </p>
            </div>

            <p class="fine small mt-4 mb-0">
                <a href="{{ route('home') }}">Ana sayfa</a>
                ·
                <a href="{{ route('privacy') }}">Gizlilik</a>
            </p>
        </article>
    </main>
@endsection
