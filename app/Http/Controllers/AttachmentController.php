<?php

namespace App\Http\Controllers;

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
}
