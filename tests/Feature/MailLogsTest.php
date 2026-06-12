<?php

use App\Models\MailLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('guests are redirected to the login page from mail logs page', function () {
    $response = $this->get(route('admin.mail_logs'));
    $response->assertRedirect(route('login'));
});

test('authenticated administrators can visit the mail logs page', function () {
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->schools()->attach($schoolId, ['roles' => json_encode(['administrador'])]);

    $this->actingAs($user);

    $response = $this->get(route('admin.mail_logs'));
    $response->assertOk();
});

test('sending an email creates a mail log entry via the listener', function () {
    // Clean any old logs
    MailLog::truncate();

    // Send a test mail using Laravel Mailer
    Mail::raw('Este es el contenido de prueba', function ($message) {
        $message->to('test-recipient@example.com')
            ->subject('Asunto de Prueba');
    });

    // Verify database entry
    $this->assertDatabaseHas('mail_logs', [
        'to' => 'test-recipient@example.com',
        'subject' => 'Asunto de Prueba',
        'status' => 'sent',
    ]);

    expect(MailLog::count())->toBe(1);
    $log = MailLog::first();
    expect($log->body)->toContain('Este es el contenido de prueba');
    expect($log->mail_id)->not->toBeNull();
});

test('livewire search and status filters work correctly', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->schools()->attach($schoolId, ['roles' => json_encode(['administrador'])]);
    $this->actingAs($user);

    MailLog::truncate();
    MailLog::create([
        'mail_id' => 'id-123456',
        'to' => 'filter-to@example.com',
        'subject' => 'Filtrame por Destinatario',
        'body' => 'Hola',
        'status' => 'bounced',
        'sent_at' => now(),
    ]);

    // Livewire test search
    $component = Livewire::test('pages::admin.mail_logs')
        ->set('search', 'filter-to');

    expect($component->get('mailLogs')->count())->toBe(1);
    expect($component->get('mailLogs')->first()->to)->toBe('filter-to@example.com');

    $component->set('search', 'non-existent');
    expect($component->get('mailLogs')->count())->toBe(0);

    // Livewire test status filter
    $component = Livewire::test('pages::admin.mail_logs')
        ->set('filtroStatus', 'bounced');
    expect($component->get('mailLogs')->count())->toBe(1);

    $component->set('filtroStatus', 'delivered');
    expect($component->get('mailLogs')->count())->toBe(0);
});

test('webhook endpoint successfully updates mail log status on bounce', function () {
    MailLog::truncate();
    $log = MailLog::create([
        'mail_id' => 'resend-msg-999',
        'to' => 'bounce@example.com',
        'subject' => 'Prueba Rebote',
        'body' => 'Rebote',
        'status' => 'sent',
        'sent_at' => now(),
    ]);

    // Simulate Resend bounce webhook payload
    $payload = [
        'type' => 'email.bounced',
        'data' => [
            'email_id' => 'resend-msg-999',
            'bounce' => [
                'description' => 'Mailbox not found.',
            ],
        ],
    ];

    $response = $this->postJson(route('webhooks.mail'), $payload);
    $response->assertOk();

    // Check database state
    $log->refresh();
    expect($log->status)->toBe('bounced');
    expect($log->error_message)->toBe('Mailbox not found.');
});
