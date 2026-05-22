<?php

namespace App\Services;

use App\Jobs\ProcessAttachment;
use App\Models\Attachment;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FileServices
{
    /**
     * Create DB record immediately, save file to temp, dispatch background job for compression.
     * Works for both AJAX (property) and legacy form (society) flows.
     *
     * @param  \Illuminate\Http\UploadedFile|array  $files
     * @param  \Illuminate\Database\Eloquent\Model|null  $model
     * @return array New attachment IDs
     */
    public function compressAndStore($files, $model = null, bool $isSingle = false, bool $isMain = false): array
    {
        if ($isSingle && $files instanceof \Illuminate\Http\UploadedFile) {
            $files = [$files];
        }

        $files = $files ?? [];
        $attachmentIds = [];

        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $filename = time().'_'.$file->getClientOriginalName();

            $attachment = null;

            if ($model) {
                if ($isSingle && $isMain) {
                    $model->attachment()->where('is_main', true)->delete();
                }

                $attachment = $isSingle
                  ? $model->attachment()->create([
                      'name' => $filename,
                      'link' => 'files/original/'.$filename,
                      'type' => $file->getClientMimeType(),
                      'extension' => $extension,
                      'size' => '0 KB',
                      'is_main' => $isMain,
                      'progress' => 10,
                  ])
                  : $model->attachments()->create([
                      'name' => $filename,
                      'link' => 'files/original/'.$filename,
                      'type' => $file->getClientMimeType(),
                      'extension' => $extension,
                      'size' => '0 KB',
                      'is_main' => false,
                      'progress' => 10,
                  ]);
            }

            // Save file to temp storage
            $tempPath = $file->storeAs('files/original', $filename, 'public');

            if ($attachment) {
                // Dispatch job — runs in background, updates progress 10→25→40→75→100
                ProcessAttachment::dispatch(
                    $tempPath,
                    $filename,
                    $file->getClientMimeType(),
                    $extension,
                    $attachment->id
                );
                $attachmentIds[] = $attachment->id;
            }
        }

        return $attachmentIds;
    }

    /**
     * Called by ProcessAttachment job.
     * Runs compression and updates progress in DB.
     */
    public function processAttachment(string $tempPath, string $filename, string $extension, Attachment $attachment): void
    {
        try {
            //            // STEP 2 — move temp → original
            //            $originalPath = 'files/original/'.$filename;
            //            Storage::disk('public')->move($tempPath, $originalPath);

            $attachment->update(['progress' => 25]);
            logger('25');

            $compressedPath = 'files/compressed/'.$filename;

            // STEP 3 — compression start
            $attachment->update(['progress' => 40]);
            logger('40');

            if (in_array($extension, ['mp4', 'mov', 'avi', 'webm', 'mkv'])) {
                $format = new \FFMpeg\Format\Video\X264;
                $format->setKiloBitrate(600);
                \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg::fromDisk('public')
                    ->open($tempPath)
                    ->export()
                    ->toDisk('public')
                    ->inFormat($format)
                    ->save($compressedPath);

            } elseif (in_array($extension, ['mp3', 'wav', 'flac', 'aiff', 'wma'])) {
                $audio = new \FFMpeg\Format\Audio\Mp3;
                $audio->setAudioKiloBitrate(28);
                \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg::fromDisk('public')
                    ->open($tempPath)
                    ->export()
                    ->inFormat($audio)
                    ->toDisk('public')
                    ->save($compressedPath);

            } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg'])) {
                \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg::fromDisk('public')
                    ->open($tempPath)
                    ->addFilter('-qscale:v', '50')
                    ->export()
                    ->toDisk('public')
                    ->save($compressedPath);

            } else {
                // Non-media files (pdf, doc, xlsx etc) — just move, no compression
                Storage::disk('public')->move($tempPath, $compressedPath);
            }

            // STEP 4 — compression complete
            $attachment->update(['progress' => 75]);
            logger('75');

            $sizeBytes = Storage::disk('public')->size($compressedPath);
            $size = $sizeBytes <= 1048576
              ? round($sizeBytes / 1024, 2).' KB'
              : round($sizeBytes / 1048576, 2).' MB';

            // STEP 5 — delete original
            if (Storage::disk('public')->exists($tempPath)) {
                Storage::disk('public')->delete($tempPath);
            }

            // STEP 6 — final update
            $attachment->update([
                'link' => $compressedPath,
                'size' => $size,
                'progress' => 100,
            ]);
            logger('100');

        } catch (Exception $e) {
            $attachment->update(['progress' => -1]);
            logger('-1');
            throw new Exception("Failed to store $filename: ".$e->getMessage());
        }
    }

    /**
     * Delete attachments from disk and database.
     *
     * @param  array<int>  $ids
     */
    public function deleteByIds(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $attachments = Attachment::whereIn('id', $ids)->get();
            foreach ($attachments as $attachment) {
                if ($attachment->link && Storage::disk('public')->exists($attachment->link)) {
                    Storage::disk('public')->delete($attachment->link);
                }
                $attachment->delete();
            }
        });
    }
}
