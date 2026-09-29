<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\TeacherOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RegistersBoards;
use Tests\TestCase;

class TeacherAuthTest extends TestCase
{
    use RefreshDatabase, RegistersBoards;

    public function test_self_registration_waits_for_approval_before_otp(): void
    {
        $this->organization();

        $this->post('/ogretmen/kayit', [
            'official_code' => '12345678',
            'first_name' => 'Ayşe',
            'last_name' => 'Yılmaz',
            'phone' => '0532 111 22 33',
        ])->assertRedirect(route('home'));

        $teacher = Teacher::query()->where('phone', '905321112233')->first();
        $this->assertNotNull($teacher);
        $this->assertFalse($teacher->isApproved());

        $this->postJson('/api/ogretmen/otp', ['phone' => '05321112233'])
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'İdare onayı bekleniyor.');

        $this->actingAs(User::factory()->create())
            ->post('/panel/ogretmenler/'.$teacher->id.'/onay')
            ->assertRedirect(route('panel.teachers'));

        $this->postJson('/api/ogretmen/otp', ['phone' => '5321112233'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Kod gönderildi.');

        $code = $this->lastCode();
        $this->assertNotNull($code);

        $this->postJson('/api/ogretmen/login', [
            'phone' => '0532 111 22 33',
            'code' => $code,
            'remember' => true,
        ])->assertOk();

        $this->assertTrue(
            collect($this->app['cookie']->getQueuedCookies())
                ->contains(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_teacher_')),
        );

        $this->get('/ogretmen')->assertOk()->assertSee('Kayıtlı tahtalar');
    }

    public function test_admin_created_teacher_can_request_otp_immediately(): void
    {
        $this->organization();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/panel/ogretmenler', [
                'first_name' => 'Mehmet',
                'last_name' => 'Kaya',
                'phone' => '05441112233',
            ])
            ->assertRedirect(route('panel.teachers'));

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/ogretmen/otp', ['phone' => '05441112233'])
            ->assertOk()
            ->assertJsonPath('message', 'Kod gönderildi.');

        $this->assertNotNull($this->lastCode());
    }

    public function test_a_phone_cannot_join_a_second_organization(): void
    {
        $this->organization();
        Teacher::factory()->forOrganization($this->organization())->create([
            'phone' => '905551112233',
        ]);

        $this->post('/ogretmen/kayit', [
            'official_code' => '12345678',
            'first_name' => 'Tekrar',
            'last_name' => 'Kayıt',
            'phone' => '05551112233',
        ])->assertSessionHasErrors('phone');
    }

    public function test_unknown_phone_does_not_get_an_otp(): void
    {
        $this->postJson('/api/ogretmen/otp', ['phone' => '05001112233'])
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'Bu telefon kayıtlı değil.');
    }

    public function test_wrong_otp_code_is_rejected(): void
    {
        $this->organization();
        $teacher = Teacher::factory()->forOrganization($this->organization())->create([
            'phone' => '905321112233',
        ]);

        $this->postJson('/api/ogretmen/otp', ['phone' => $teacher->phone])->assertOk();

        $this->postJson('/api/ogretmen/login', [
            'phone' => $teacher->phone,
            'code' => '000000',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'Kod hatalı veya süresi doldu.');

        $this->postJson('/api/ogretmen/login', [
            'phone' => $teacher->phone,
            'code' => '123',
        ])->assertStatus(422);
    }

    public function test_valid_otp_logs_the_teacher_in(): void
    {
        $this->organization();
        $teacher = Teacher::factory()->forOrganization($this->organization())->create([
            'phone' => '905321112233',
        ]);

        $this->postJson('/api/ogretmen/otp', ['phone' => $teacher->phone])->assertOk();

        $this->postJson('/api/ogretmen/login', [
            'phone' => $teacher->phone,
            'code' => $this->lastCode(),
        ])->assertOk()->assertJsonPath('name', $teacher->name);

        $this->get('/ogretmen')->assertOk();
    }

    public function test_logout_keeps_the_remember_cookie_and_forget_removes_it(): void
    {
        $this->organization();
        $teacher = Teacher::factory()->forOrganization($this->organization())->create([
            'phone' => '905321112233',
        ]);

        $this->postJson('/api/ogretmen/otp', ['phone' => $teacher->phone])->assertOk();
        $login = $this->postJson('/api/ogretmen/login', [
            'phone' => $teacher->phone,
            'code' => $this->lastCode(),
            'remember' => true,
        ])->assertOk();

        $name = Auth::guard('teacher')->getRecallerName();
        $recaller = $login->getCookie($name)->getValue();
        $this->postJson('/api/ogretmen/logout')->assertOk()->assertCookieNotExpired($name);

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withCookie($name, $recaller)
            ->get('/ogretmen')
            ->assertOk()
            ->assertSee('topbar-forget', false)
            ->assertSee('data-url="'.url('/api/ogretmen/forget').'"', false);

        $this->withCredentials()->postJson('/api/ogretmen/forget')->assertOk()->assertCookieExpired($name);

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->defaultCookies = [];
        $this->get('/ogretmen')->assertRedirect(route('home'));
    }

    public function test_the_code_is_queued_for_whatsapp(): void
    {
        $this->organization();
        $teacher = Teacher::factory()->forOrganization($this->organization())->create([
            'phone' => '905321112233',
        ]);

        $this->postJson('/api/ogretmen/otp', ['phone' => $teacher->phone])
            ->assertOk()
            ->assertJsonPath('message', 'Kod gönderildi.');

        $row = DB::table('otp')->first();
        $this->assertNotNull($row);
        $this->assertSame('login', $row->type);
        $this->assertSame('905321112233', $row->sendto);
        $this->assertSame('pending', $row->status);
        $this->assertNull($row->sended_at);

        $code = preg_replace('/\D+/', '', (string) $row->message);
        $this->assertSame(6, strlen((string) $code));
        $this->assertSame(
            hash('sha256', (string) $code),
            TeacherOtp::query()->where('teacher_id', $teacher->id)->value('code_hash'),
        );
    }

    private function lastCode(): ?string
    {
        $message = (string) DB::table('otp')->where('type', 'login')->orderByDesc('id')->value('message');

        return preg_match('/(\d{6})/', $message, $matches) === 1 ? $matches[1] : null;
    }
}
