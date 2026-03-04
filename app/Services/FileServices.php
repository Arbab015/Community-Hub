<?php

namespace App\Services;

use App\Models\Attachment;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use FFMpeg\Format\Video\X264;
use FFMpeg\Format\Audio\Mp3;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Mostafaznv\PdfOptimizer\Laravel\Facade\PdfOptimizer;
use Blaspsoft\Doxswap\Facades\Doxswap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FileServices
{
    /**
     * Handle file upload & compression
     *
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model|null $model
     * @param bool $isSingle Whether to store only a single attachment
     * @param bool $isMain Whether the single attachment is main
     * @return void
     * @throws Exception
     */
    public function compressAndStore($files, $model = null, bool $isSingle = false, bool $isMain = false)
    {
        // If single file, wrap in array
        if ($isSingle && $files instanceof \Illuminate\Http\UploadedFile) {
            $files = [$files];
        }
        $files = $files ?? [];
        foreach ($files as $file) {
            if (!$file) continue;
            $extension = strtolower($file->getClientOriginalExtension());
            $filename = time() . '_' . $file->getClientOriginalName();
            $originalPath = $file->storeAs('files/original', $filename, 'public');
            $compressedPath = 'files/compressed/' . $filename;

            try {
                if (in_array($extension, ['mp4', 'mov', 'avi', 'webm', 'mkv'])) {
                    $format = new X264();
                    $format->setKiloBitrate(600);
                    FFMpeg::fromDisk('public')->open($originalPath)->export()->toDisk('public')->inFormat($format)->save($compressedPath);
                } elseif (in_array($extension, ['mp3', 'wav', 'flac', 'aiff', 'wma'])) {
                    $audio = new Mp3();
                    $audio->setAudioKiloBitrate(28);
                    FFMpeg::fromDisk('public')->open($originalPath)->export()->inFormat($audio)->toDisk('public')->save($compressedPath);
                } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg'])) {
                    FFMpeg::fromDisk('public')->open($originalPath)->addFilter('-qscale:v', '50')->export()->toDisk('public')->save($compressedPath);
                } else {
                    Storage::disk('public')->putFileAs('files/compressed', $file, $filename);
                    $compressedPath = 'files/compressed/' . $filename;
                }

                $sizeBytes = Storage::disk('public')->size($compressedPath);
                $size = $sizeBytes <= 1048576
                    ? round($sizeBytes / 1024, 2) . ' KB'
                    : round($sizeBytes / 1048576, 2) . ' MB';


                $original_file = 'files/original/' . $filename;
                if (Storage::disk('public')->exists($original_file)) {
                    Storage::disk('public')->delete($original_file);
                }
                if ($model) {
                    if ($isSingle) {
                        if ($isMain) {
                            $model->attachment()->where('is_main', true)->delete();
                        }
                        $model->attachment()->create([
                            'name' => $filename,
                            'link' => $compressedPath,
                            'type' => $file->getClientMimeType(),
                            'extension' => $extension,
                            'size' => $size,
                            'is_main' => $isMain,
                        ]);
                    } else {
                        $model->attachments()->create([
                            'name' => $filename,
                            'link' => $compressedPath,
                            'type' => $file->getClientMimeType(),
                            'extension' => $extension,
                            'size' => $size,
                            'is_main' => false,
                        ]);
                    }
                }
            } catch (Exception $e) {
                throw new Exception("Failed to store $filename: " . $e->getMessage());
            }
        }
    }

    /**
     * Delete attachments from disk and database
     *
     * @param  array<int>  $ids
     * @return void
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
