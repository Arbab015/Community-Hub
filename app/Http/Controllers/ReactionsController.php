<?php

namespace App\Http\Controllers;

use App\Models\Comment as ModelsComment;
use App\Models\Post;
use Illuminate\Http\Request;

class ReactionsController extends Controller
{

    public function react(Request $request)
    {
        $request->validate([
            'reactionable_type' => 'required|in:post,comment',
            'reactionable_id'   => 'required|integer',
            'type'              => 'required|in:like,dislike',
        ]);

        // Map short type to model
        $modelClass = match ($request->reactionable_type) {
            'post' => Post::class,
            'comment' => ModelsComment::class,
        };

        $model = $modelClass::findOrFail($request->reactionable_id);
        $reaction = $model->reactions()
            ->where('user_id', auth()->id())
            ->first();

        if ($reaction) {
            if ($reaction->type === $request->type) {
                $reaction->delete();
                return response()->json(['status' => 'removed']);
            }

            $reaction->update(['type' => $request->type]);
            return response()->json(['status' => 'updated']);
        }

        $model->reactions()->create([
            'user_id' => auth()->id(),
            'type' => $request->type,
        ]);

        return response()->json(['status' => 'added']);
    }
}