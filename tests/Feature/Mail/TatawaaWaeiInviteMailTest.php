<?php

namespace Tests\Feature\Mail;

use App\Mail\TatawaaWaeiInviteMail;
use App\Support\TatawaaWaeiInviteMail as Invite;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TatawaaWaeiInviteMailTest extends TestCase
{
    public function test_invite_mail_uses_expected_subject_body_button_and_attachment(): void
    {
        $this->assertFileExists(public_path(Invite::HERO_PUBLIC_PATH));

        $mailable = new TatawaaWaeiInviteMail('لمى فؤاد سليمان المشيقح');

        $mailable->assertHasSubject(Invite::SUBJECT);
        $mailable->assertSeeInHtml('لمى اخترناك بين الكثير ..');
        $mailable->assertSeeInHtml(Invite::BUTTON_LABEL);
        $mailable->assertSeeInHtml('مبادرة «تطوع واعي»');
        $mailable->assertDontSeeInHtml('برنامج «تطوع واعي»');
        $mailable->assertSeeInHtml(Invite::registerUrl());
        $mailable->assertSeeInHtml(config('brand.logos.kafaat_mail'));
        $html = $mailable->render();
        $this->assertStringContainsString(Invite::heroPublicUrl(), $html);
        $this->assertStringNotContainsString('data:image/', $html);
        $this->assertStringNotContainsString('ولأن رحلتنا معكم لا تنتهي', $html);
        $this->assertStringNotContainsString('منصات التواصل الاجتماعي', $html);
        $mailable->assertHasAttachment(public_path(Invite::HERO_PUBLIC_PATH), [
            'as' => 'tatawaa-waei-invite.jpg',
            'mime' => 'image/jpeg',
        ]);

        $this->assertSame('لمى', Invite::firstName('لمى فؤاد سليمان المشيقح'));
        $this->assertSame('إيمان', Invite::firstName('إيمان أحمد'));
        $this->assertSame('إيمان اخترناك بين الكثير ..', Invite::openingLine('إيمان أحمد'));
        $this->assertSame('اخترناك بين الكثير ..', Invite::openingLine(null));
        $this->assertSame('إيمان تذكير: اخترناك بين الكثير ..', Invite::openingLine('إيمان أحمد', reminder: true));
        $this->assertSame(Invite::REMINDER_SUBJECT, Invite::subject(reminder: true));
    }

    public function test_reminder_mail_uses_reminder_subject_and_opening(): void
    {
        $mailable = new TatawaaWaeiInviteMail('لمى فؤاد سليمان المشيقح', reminder: true);

        $mailable->assertHasSubject(Invite::REMINDER_SUBJECT);
        $mailable->assertSeeInHtml('لمى تذكير: اخترناك بين الكثير ..');
        $mailable->assertSeeInHtml(Invite::BUTTON_LABEL);
        $mailable->assertSeeInHtml(Invite::registerUrl());
    }

    public function test_preview_can_be_sent_to_an_address(): void
    {
        Mail::fake();

        Mail::to('lamaalmushyqih@gmail.com')->send(new TatawaaWaeiInviteMail('لمى'));

        Mail::assertSent(TatawaaWaeiInviteMail::class, function (TatawaaWaeiInviteMail $mail): bool {
            return $mail->hasTo('lamaalmushyqih@gmail.com')
                && $mail->recipientName === 'لمى';
        });
    }
}
