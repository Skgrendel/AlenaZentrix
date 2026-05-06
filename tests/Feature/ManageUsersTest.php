<?php

use App\Mail\WelcomeNewUser;
use Illuminate\Support\Facades\Mail;

test('welcome email is sent with correct data', function () {
    Mail::fake();

    $email = new WelcomeNewUser(
        'user@example.com',
        'Juan',
        'Pérez',
        'TempPassword123!',
        'http://localhost/login'
    );

    Mail::send($email);

    Mail::assertSent(WelcomeNewUser::class);
    expect($email->email)->toBe('user@example.com');
    expect($email->firstName)->toBe('Juan');
    expect($email->lastName)->toBe('Pérez');
    expect($email->password)->toBe('TempPassword123!');
});

test('welcome email has correct subject', function () {
    $email = new WelcomeNewUser(
        'test@example.com',
        'Test',
        'User',
        'password',
        'http://localhost/login'
    );

    $envelope = $email->envelope();
    expect($envelope->subject)->toContain(config('app.name'));
});

test('welcome email template is rendered correctly', function () {
    $email = new WelcomeNewUser(
        'user@example.com',
        'María',
        'García',
        'SecurePass456!',
        'http://localhost/login'
    );

    $content = $email->content();
    expect($content->view)->toBe('emails.welcome-new-user');
    expect($content->with)->toHaveKey('firstName', 'María');
    expect($content->with)->toHaveKey('lastName', 'García');
    expect($content->with)->toHaveKey('email', 'user@example.com');
    expect($content->with)->toHaveKey('password', 'SecurePass456!');
});
