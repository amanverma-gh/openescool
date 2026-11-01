<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * What the sign-up form sends: a human took a few seconds and left the honeypot empty.
     *
     * @return array<string, string>
     */
    protected function form(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'website' => '',
            'form_token' => Crypt::encryptString((string) now()->subSeconds(20)->timestamp),
        ], $overrides);
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post('/register', $this->form());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_bots_are_rejected()
    {
        // Filled the hidden honeypot field.
        $this->post('/register', $this->form(['website' => 'http://spam.example']))->assertSessionHasErrors('email');
        // Submitted instantly.
        $this->post('/register', $this->form(['form_token' => Crypt::encryptString((string) now()->timestamp)]))->assertSessionHasErrors('email');
        // Posted without loading the form.
        $this->post('/register', $this->form(['form_token' => 'forged']))->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
