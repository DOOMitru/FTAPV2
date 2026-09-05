<?php

namespace Tests\Feature;

use App\Mail\ContactSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_send_a_general_message()
    {
        Mail::fake();

        $response = $this->post(route('contact.store'), [
            'name' => 'Mara Vasquez',
            'email' => 'mara@example.com',
            'topic' => 'general',
            'message' => 'When does Season 6 start?',
        ]);

        $response->assertSessionHas('status');

        Mail::assertSent(ContactSubmission::class, function (ContactSubmission $mail) {
            return $mail->senderEmail === 'mara@example.com'
                && $mail->topic === 'general'
                && $mail->hasTo(config('mail.league_contact'));
        });
    }

    public function test_a_sponsorship_enquiry_uses_its_own_subject_line()
    {
        Mail::fake();

        $this->post(route('contact.store'), [
            'name' => 'Joseph Okonkwo',
            'email' => 'joseph@example.com',
            'topic' => 'sponsorship',
            'message' => 'We would like to sponsor the season finale.',
        ]);

        Mail::assertSent(ContactSubmission::class, function (ContactSubmission $mail) {
            return $mail->envelope()->subject === 'Sponsorship enquiry from Joseph Okonkwo';
        });
    }

    public function test_the_form_requires_a_name_email_topic_and_message()
    {
        Mail::fake();

        $response = $this->post(route('contact.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'topic', 'message']);
        Mail::assertNothingSent();
    }

    public function test_an_unknown_topic_is_rejected()
    {
        Mail::fake();

        $response = $this->post(route('contact.store'), [
            'name' => 'Mara Vasquez',
            'email' => 'mara@example.com',
            'topic' => 'not-a-real-topic',
            'message' => 'Hello.',
        ]);

        $response->assertSessionHasErrors('topic');
        Mail::assertNothingSent();
    }

    public function test_a_filled_honeypot_is_silently_discarded()
    {
        Mail::fake();

        $response = $this->post(route('contact.store'), [
            'name' => 'Spam Bot',
            'email' => 'bot@example.com',
            'topic' => 'general',
            'message' => 'Buy things.',
            'company' => 'Bot Industries',
        ]);

        // The bot sees the same confirmation a person sees.
        $response->assertSessionHas('status');
        Mail::assertNothingSent();
    }
}
