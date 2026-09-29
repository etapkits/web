<?php

namespace Tests\Feature;

use App\Models\SuperAdmin;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RegistersBoards;
use Tests\TestCase;

class TopbarTest extends TestCase
{
    use RefreshDatabase, RegistersBoards;

    public function test_admin_pages_share_the_mobile_menu_and_mark_the_active_link(): void
    {
        $this->actingAs(User::factory()->create(['organization_id' => $this->organization()->id]), 'web');

        $pages = [
            '/panel' => route('panel'),
            '/panel/ogretmenler' => route('panel.teachers'),
            '/panel/ogrenciler' => route('panel.students'),
            '/panel/yoklamalar' => route('panel.attendance'),
            '/panel/yoklamalar/liste?tarih=2026-09-01' => route('panel.attendance', ['date' => '2026-09-01']),
            '/panel/ayarlar' => route('panel.settings'),
        ];

        foreach ($pages as $url => $active) {
            $response = $this->get($url);

            $this->assertSame(1, substr_count($response->getContent(), 'font-awesome'), $url);

            $response
                ->assertOk()
                ->assertSeeInOrder(['fa-display', 'fa-chalkboard-user', 'fa-user-graduate', 'fa-clipboard-check', 'fa-lock', 'fa-right-from-bracket'], false)
                ->assertSee('aria-controls="topbar-menu"', false)
                ->assertSee('js/topbar.js', false)
                ->assertSeeInOrder(['Tahtalar', 'Öğretmenler', 'Öğrenciler', 'Yoklamalar', 'Kilit ayarları'])
                ->assertSee('aria-controls="topbar-user-menu" aria-expanded="false"', false)
                ->assertSee('id="topbar-user-menu" class="topbar-user-menu" role="menu" hidden', false)
                ->assertSeeInOrder(['topbar-user-toggle', 'topbar-user-menu', 'topbar-logout'], false)
                ->assertSee('topbar-link active"', false)
                ->assertSee('href="'.e($active).'" aria-current="page"', false)
                ->assertSee('data-url="'.url('/api/panel/logout').'" data-login="'.route('login').'"', false)
                ->assertSee('class="mobile-nav"', false)
                ->assertSeeInOrder(['class="mobile-nav"', 'Tahtalar', 'QR okut', 'Çıkış'], false)
                ->assertSee('id="qr-dialog"', false)
                ->assertSee('data-unlock-url="'.url('/api/panel/unlock').'"', false)
                ->assertSee('js/qr-scan.js', false)
                ->assertDontSee('topbar-forget', false);
        }
    }

    public function test_logout_falls_back_to_a_signed_out_page_with_the_right_login_link(): void
    {
        $this->actingAs(User::factory()->create(['organization_id' => $this->organization()->id]), 'web')
            ->get('/panel')
            ->assertSee('data-closed="'.e(route('signed-out', ['giris' => 'panel'])).'"', false);

        $this->get('/cikis?giris=panel')->assertOk()->assertSee('Çıkışı tamamlamak için pencereyi kapatınız')->assertSee('href="'.route('login').'"', false);
        $this->get('/cikis?giris=super')->assertOk()->assertSee('href="'.route('super.login').'"', false);
        $this->get('/cikis?giris=teacher')->assertOk()->assertSee('Çıkışı tamamlamak için pencereyi kapatınız')->assertDontSee('btn-outline-secondary', false);
    }

    public function test_teacher_and_super_pages_get_their_own_menus(): void
    {
        $teacher = Teacher::factory()->forOrganization($this->organization())->create();

        $this->actingAs($teacher, 'teacher')
            ->get('/ogretmen/yoklama')
            ->assertOk()
            ->assertSee('href="'.route('teacher.panel').'"', false)
            ->assertSee('data-url="'.url('/api/ogretmen/logout').'" data-login="'.route('home').'"', false)
            ->assertSee('data-unlock-url="'.url('/api/ogretmen/unlock').'"', false)
            ->assertSee('>QR okut<', false)
            ->assertDontSee('Kilit ayarları');

        $this->get('/')->assertRedirect(route('teacher.panel'));

        $this->get('/ogretmen')
            ->assertOk()
            ->assertSee('data-url="'.url('/api/ogretmen/logout').'"', false);

        $this->actingAs(SuperAdmin::factory()->create(), 'super');

        $this->get('/super')->assertSee('href="'.route('super.organizations.create').'"', false);

        foreach (['/super', '/super/kurumlar/yeni'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('href="'.route('super.organizations').'" aria-current="page"', false)
                ->assertDontSee('fa-solid fa-plus" aria-hidden="true"></i>Kurum ekle</a>', false)
                ->assertSee('data-url="'.url('/api/super/logout').'" data-login="'.route('super.login').'"', false)
                ->assertDontSee('QR okut', false)
                ->assertDontSee('mobile-nav', false);
        }
    }
}
