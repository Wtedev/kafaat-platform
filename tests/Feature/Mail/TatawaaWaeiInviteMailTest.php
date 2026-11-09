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

        $mailable = new TatawaaWaeiInviteMail;

        $mailable->assertHasSubject(Invite::SUBJECT);
        $mailable->assertSeeInHtml('اخترناك من بين الكثير');
        $mailable->assertSeeInHtml('التسجيل في برنامج تطوع واعي');
        $mailable->assertSeeInHtml(Invite::registerUrl());
        $mailable->assertSeeInHtml(config('brand.logos.kafaat_mail'));
        $mailable->assertHasAttachment(public_path(Invite::HERO_PUBLIC_PATH), [
            'as' => 'tatawaa-waei-invite.jpg',
            'mime' => 'image/jpeg',
        ]);
    }

    public function test_preview_can_be_sent_to_an_address(): void
    {
        Mail::fake();

        Mail::to('lamaalmushyqih@gmail.com')->send(new TatawaaWaeiInviteMail);

        Mail::assertSent(TatawaaWaeiInviteMail::class, function (TatawaaWaeiInviteMail $mail): bool {
            return $mail->hasTo('lamaalmushyqih@gmail.com');
        });
    }
}
