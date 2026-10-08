<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_login_validation_errors_follow_locale(): void
    {
        $this->withSession(['locale' => 'id'])
            ->post('/login', [])
            ->assertSessionHasErrors('email')
            ->assertSessionHasErrors('password');

        $this->withSession(['locale' => 'en'])
            ->post('/login', [])
            ->assertSessionHasErrors('email');
    }

    public function test_auth_failure_message_follows_locale(): void
    {
        $responseId = $this->withSession(['locale' => 'id'])
            ->post('/login', ['email' => 'nobody@example.test', 'password' => 'secret-secret']);

        $responseId->assertSessionHasErrors('email');

        $errors = session('errors');
        $this->assertStringContainsString('Kredensial', $errors->getBag('default')->first('email'));
    }

    public function test_pagination_labels_follow_locale(): void
    {
        $this->assertSame('&laquo; Sebelumnya', trans('pagination.previous', [], 'id'));
        $this->assertSame('&laquo; Previous', trans('pagination.previous', [], 'en'));
        $this->assertSame('Menampilkan', __('Showing', [], 'id'));
        $this->assertSame('Showing', __('Showing', [], 'en'));
    }

    public function test_password_reset_sent_message_follows_locale(): void
    {
        $this->assertSame(
            'Kami telah mengirimkan tautan reset password ke email Anda.',
            trans('passwords.sent', [], 'id')
        );
    }
}
