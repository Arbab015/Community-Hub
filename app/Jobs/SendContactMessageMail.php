<?php

namespace App\Jobs;

use App\Mail\ContactMessageMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendContactMessageMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = 30;

    public function __construct(
        public string $fullName,
        public string $email,
        public string $message
    ) {}

    public function handle(): void
    {
        Mail::to(config('mail.admin_notify_email'))
            ->send(new ContactMessageMail(
                $this->fullName,
                $this->email,
                $this->message
            ));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Contact mail job failed: '.$exception->getMessage());
    }
}
