<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\FileServices;
use Illuminate\Http\Request;

class AttachmentController extends Controller
{
    public function bulkDelete(Request $request)
    {
        try {
            app(FileServices::class)->deleteByIds($request->ids);

            return response()->json(['success' => true, 'message' => 'Selected files deleted successfully.']);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function fileUploadProgress(Request $request)
    {
        $ids = explode(',', $request->ids);

        return Attachment::whereIn('id', $ids)
            ->get()
            ->keyBy('id')
            ->map(fn ($item) => [
                'progress' => $item->progress,
            ]);
    }
}
