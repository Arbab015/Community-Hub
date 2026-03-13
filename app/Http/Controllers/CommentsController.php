<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Services\FileServices;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CommentsController extends Controller
{
    protected $compressor;

    public function __construct(FileServices $compressor)
    {
        $this->compressor = $compressor;
    }
    public function store(Request $request)
    {
        try {
            // dd($request->all());
          $request->validate([
            'message' => 'required_without:image|nullable|string',
            'image'   => 'required_without:message|nullable|image|max:5120',
            'post_id' => 'required|integer|exists:posts,id',
            'parent_id' => 'nullable|integer|exists:comments,id',
            'edit_id' => 'nullable|integer|exists:comments,id',
          ]);

            // Edit mode
            if ($request->edit_id) {
                $comment = Comment::findOrFail($request->edit_id);

                if ($comment->user_id !== auth()->id()) {
                    return redirect()->back()->with('error', 'Unauthorized action!');
                }
                $comment->message = $request->message;
                if ($request->hasFile('image')) {
                    app(FileServices::class)
                        ->compressAndStore($request->file('image'), $comment, true, true);
                } elseif (!$request->hasFile('image')) {
                    $old = $comment->attachment()->where('is_main', true)->first();
                    if ($old) {
                        Storage::disk('public')->delete($old->link);
                        $old->delete();
                    }
                }

                $comment->save();
                return redirect()->back()->with('success', 'Comment updated successfully!');
            }
            // Create new comment
            $comment = Comment::create([
                'message' => $request->message,
                'post_id' => $request->post_id,
                'parent_id' => $request->parent_id,
                'user_id' => auth()->id(),
            ]);
            if ($request->hasFile('image')) {
                app(FileServices::class)
                    ->compressAndStore($request->file('image'), $comment, true, true);
            }
            return redirect()->back()->with('success', 'Comment posted successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }



    public function destroy($slug, Comment $comment)
    {
        if (auth()->id() !== $comment->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            DB::transaction(function () use ($comment) {
                $this->deleteCommentWithNested($comment);
            });

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delete failed'
            ], 500);
        }
    }

    private function deleteCommentWithNested($comment)
    {
        $replies = Comment::where('parent_id', $comment->id)->get();
        foreach ($replies as $reply) {
            $this->deleteCommentWithNested($reply);
        }
        if ($comment->attachment && Storage::disk('public')->exists($comment->attachment->link)) {
            Storage::disk('public')->delete($comment->attachment->link);
        }
        $comment->attachment()?->delete();
        $comment->reactions()->delete();
        $comment->delete();
    }
}
