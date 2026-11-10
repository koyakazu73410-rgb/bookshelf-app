<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FortifyValidationMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_required_messages_use_japanese_attribute_names(): void
    {
        $this->post(route('register.store'), [])
            ->assertSessionHasErrors([
                'name' => 'ユーザー名は必須項目です。',
                'email' => 'メールアドレスは必須項目です。',
                'password' => 'パスワードは必須項目です。',
            ]);
    }

    public function test_registration_name_messages_are_japanese(): void
    {
        $this->post(route('register.store'), [
            'name' => ['invalid'],
            'email' => 'name-string@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors([
            'name' => 'ユーザー名は文字列で入力してください。',
        ]);

        $this->post(route('register.store'), [
            'name' => str_repeat('あ', 256),
            'email' => 'name-max@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors([
            'name' => 'ユーザー名は255文字以内で入力してください。',
        ]);
    }

    public function test_registration_email_string_message_is_japanese(): void
    {
        try {
            app(CreateNewUser::class)->create([
                'name' => 'テストユーザー',
                'email' => ['invalid'],
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);
            $this->fail('ValidationExceptionが発生しませんでした。');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'メールアドレスは文字列で入力してください。',
                $exception->errors()['email'][0]
            );
        }
    }

    public function test_registration_email_format_message_is_japanese(): void
    {
        $this->postRegistrationWithEmail('invalid-email')
            ->assertSessionHasErrors(['email' => 'メールアドレスは正しい形式で入力してください。']);
    }

    public function test_registration_email_max_message_is_japanese(): void
    {
        $this->postRegistrationWithEmail(str_repeat('a', 300).'@example.com')
            ->assertSessionHasErrors(['email' => 'メールアドレスは255文字以内で入力してください。']);
    }

    public function test_registration_email_unique_message_is_japanese(): void
    {
        User::factory()->create(['email' => 'used@example.com']);

        $this->postRegistrationWithEmail('used@example.com')
            ->assertSessionHasErrors(['email' => 'このメールアドレスはすでに登録されています。']);
    }

    public function test_registration_password_messages_are_japanese(): void
    {
        $this->post(route('register.store'), [
            'name' => 'テストユーザー',
            'email' => 'password-string@example.com',
            'password' => ['invalid'],
            'password_confirmation' => ['invalid'],
        ])->assertSessionHasErrors([
            'password' => 'パスワードは文字列で入力してください。',
        ]);

        $this->post(route('register.store'), [
            'name' => 'テストユーザー',
            'email' => 'password-rules@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $this->assertContains(
            'パスワードは8文字以上で入力してください。',
            session('errors')->get('password')
        );
        $this->assertContains(
            'パスワード（確認用）が一致しません。',
            session('errors')->get('password')
        );
    }

    public function test_login_required_messages_are_japanese(): void
    {
        $this->post(route('login.store'), [])
            ->assertSessionHasErrors([
                'email' => 'メールアドレスは必須項目です。',
                'password' => 'パスワードは必須項目です。',
            ]);
    }

    public function test_login_failure_message_is_japanese(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->post(route('login.store'), [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors([
            'email' => 'メールアドレスまたはパスワードが正しくありません。',
        ]);
    }

    private function postRegistrationWithEmail(mixed $email)
    {
        return $this->post(route('register.store'), [
            'name' => 'テストユーザー',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    }
}
