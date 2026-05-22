<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Services\FileServices;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAttachment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tempPath,
        public string $filename,
        public string $mimeType,
        public string $extension,
        public int $attachmentId
    ) {}

    public function handle(): void
    {
        $attachment = Attachment::find($this->attachmentId);
        if (! $attachment) {
            return;
        }

        app(FileServices::class)->processAttachment(
            $this->tempPath,
            $this->filename,
            $this->extension,
            $attachment
        );
    }
}
