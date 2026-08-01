<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Checks the mail configuration without having to drive the forgot password
 * flow, and says plainly when a message was only written to the log.
 */
class SendTestMail extends Command
{
    protected $signature = 'mail:test {email : The address to send the test message to}';

    protected $description = 'Send a test email to verify the mail configuration';

    public function handle(): int
    {
        $recipient = trim((string) $this->argument('email'));
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');
        $username = (string) config('mail.mailers.smtp.username');

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Mailer</>', $mailer);
        $this->components->twoColumnDetail('<fg=gray>Host</>', (string) config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));
        $this->components->twoColumnDetail('<fg=gray>From</>', $from);
        $this->components->twoColumnDetail('<fg=gray>To</>', $recipient);
        $this->newLine();

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->components->error("\"{$recipient}\" is not a valid email address.");

            return self::FAILURE;
        }

        // Gmail rejects a From address that is not the authenticated account,
        // and the resulting error is opaque, so it is worth saying up front.
        if ($mailer === 'smtp' && filled($username) && $username !== $from) {
            $this->components->warn(
                "MAIL_FROM_ADDRESS ({$from}) does not match MAIL_USERNAME ({$username}). "
                .'Gmail will usually refuse this. Set both to the same address.'
            );
        }

        try {
            Mail::raw(
                'This is a test message from '.config('app.name').'. If you can read it, sending works.',
                fn (Message $message) => $message
                    ->to($recipient)
                    ->subject(config('app.name').' test email'),
            );
        } catch (Throwable $exception) {
            $this->components->error('Sending failed: '.$exception->getMessage());
            $this->components->bulletList([
                'Check MAIL_USERNAME is the full Gmail address.',
                'Check MAIL_PASSWORD is a 16 character App Password with no spaces.',
                'Check 2-Step Verification is on for that Google account.',
            ]);

            return self::FAILURE;
        }

        if ($mailer === 'log') {
            $this->components->warn(
                'Nothing was actually sent. MAIL_MAILER is "log", so the message went to '
                .'storage/logs/laravel.log. Set MAIL_MAILER=smtp in .env to deliver for real.'
            );

            return self::SUCCESS;
        }

        $this->components->info('The mail server accepted the message. Check the inbox, and the spam folder.');

        return self::SUCCESS;
    }
}
