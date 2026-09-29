<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_old_teacher_login_url_points_to_the_home_page(): void
    {
        $this->get('/ogretmen/giris')->assertStatus(301)->assertRedirect('/');
        $this->get('/ogretmen')->assertRedirect(route('home'));
        $this->getJson('/api/ogretmen/boards')->assertUnauthorized();
    }

    public function test_privacy_page_is_available(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertSee('Gizlilik politikası', false);
    }

    public function test_terms_page_is_available(): void
    {
        $response = $this->get('/terms');

        $response->assertOk();
        $response->assertSee('Kullanım koşulları', false);
    }
}
