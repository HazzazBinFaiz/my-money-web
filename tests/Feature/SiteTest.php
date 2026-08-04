<?php

use App\Mail\ContactMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('the landing page renders with its sections and calls to action', function () {
    $this->get(route('site.home'))
        ->assertOk()
        ->assertSee('Up and running in three steps')
        ->assertSee('Questions people ask first')
        ->assertSee('Start tracking this month')
        ->assertSee('screenshot-transactions.png', false)
        ->assertSee(config('app.name'))
        ->assertSee('Get started')
        ->assertSee('id="features"', false)
        ->assertSee('id="contact"', false)
        ->assertSee(config('site.contact_mail_address'))
        ->assertSee(route('privacy'))
        ->assertSee(route('terms'));
});

test('the header points a signed in visitor at the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('site.home'))
        ->assertOk()
        ->assertSee('Open your dashboard')
        ->assertSee(route('dashboard'));
});

test('the legal pages render', function () {
    $this->get(route('privacy'))->assertOk()->assertSee('Privacy policy');
    $this->get(route('terms'))->assertOk()->assertSee('Terms of use');
});

test('the contact form mails the configured address', function () {
    Mail::fake();

    config(['site.contact_mail_address' => 'inbox@example.com']);

    $this->post(route('site.contact'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'message' => 'This is a long enough enquiry to pass validation.',
    ])->assertRedirect(route('site.home').'#contact')
        ->assertSessionHas('contact-sent');

    Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) {
        return $mail->hasTo('inbox@example.com')
            && $mail->hasReplyTo('ada@example.com')
            && $mail->senderName === 'Ada Lovelace'
            && str_contains($mail->body, 'long enough enquiry');
    });
});

test('the contact form validates what it is given', function () {
    Mail::fake();

    $this->post(route('site.contact'), ['name' => '', 'email' => 'nope', 'message' => 'short'])
        ->assertSessionHasErrors(['name', 'email', 'message']);

    Mail::assertNothingSent();
});

test('a filled honeypot is rejected', function () {
    Mail::fake();

    $this->post(route('site.contact'), [
        'name' => 'Spam Bot',
        'email' => 'bot@example.com',
        'message' => 'Buy these followers right now, cheap.',
        'website' => 'http://spam.example',
    ])->assertSessionHasErrors('website');

    Mail::assertNothingSent();
});

test('contact submissions are rate limited', function () {
    Mail::fake();

    $payload = [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'message' => 'This is a long enough enquiry to pass validation.',
    ];

    foreach (range(1, 5) as $ignored) {
        $this->post(route('site.contact'), $payload)->assertRedirect();
    }

    $this->post(route('site.contact'), $payload)->assertStatus(429);
});
