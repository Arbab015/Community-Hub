<?php

namespace App\Http\Controllers;

use App\Jobs\DeletePostIfNoComments;
use App\Jobs\NewPostJob;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\Tag;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PostController extends Controller
{
    private function getPostCounts($user)
    {
        $societyIds = $user->memberSocieties()
            ->pluck('societies.id')
            ->toArray();
        return [
            'discussionsCount' => Post::whereIn('society_id', $societyIds)
                ->where('category', 'discussion')
                ->count(),
            'suggestionsCount' => Post::whereIn('society_id', $societyIds)
                ->where('category', 'suggestion')
                ->count(),
            'issuesCount' => Post::whereIn('society_id', $societyIds)
                ->where('category', 'issue')
                ->count(),
        ];
    }


    public function index($type, $uuid = null)
    {
        $user = auth()->user();
        $societyIds = $user->memberSocieties()->pluck('societies.id')->toArray();
        $query = Post::with(['user', 'tags'])
            ->whereIn('society_id', $societyIds);
        // Check if this is "My Posts" view
        if ($uuid && (Auth()->user()->uuid == $uuid)) {
            $query->where('user_id', auth()->id());
        }
        if ($type) {
            $category = rtrim($type, 's');
            $query->where('category', $category);
        }
        if (request()->has('search') && request('search') != '') {
            $searchTerm = request('search');
            // Search in title
            $query->where('title', 'like', '%' . $searchTerm . '%')
                // Search in related tags (pivot)
                ->orWhereHas('tags', function ($tagQuery) use ($searchTerm) {
                    $tagQuery->where('name', 'like', '%' . $searchTerm . '%');
                });
        }
        if (request('sort') == 'oldest') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }
        $posts = $query->paginate(10);
        $counts = $this->getPostCounts($user);
        $admin_tags = Tag::all()->pluck('color', 'name');
        $reportedIds = Report::where('user_id', auth()->id())
            ->where('reportable_type', \App\Models\Post::class)
            ->pluck('reportable_id')
            ->toArray();
        return view('content.forum.index', compact(
            'posts',
            'category',
            'type',
            'admin_tags',
            'counts',
            'reportedIds'
        ));
    }

    public function create($type)
    {
        $user = Auth::user();
        $user->load('memberSocieties');
        $tags = Tag::all();
        $counts = $this->getPostCounts($user);
        $society_id = $user->memberSocieties->pluck('id')->first();
        return view('content.forum.create_or_edit', compact('type', 'society_id', 'tags', 'counts'));
    }

    public function storeOrUpdate(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'title'       => 'required|string|max:300',
            'description' => 'required',
            'society_id'  => 'nullable|exists:societies,id',
            'user_id'     => 'nullable|exists:users,id',
            'category'    => 'required|in:discussion,suggestion,issue',
            'tags'        => 'required|array',
            'tags.*'      => 'exists:tags,id',
        ]);
        try {
            if ($request->has('post_uuid') && $request->post_uuid) {
                $post = Post::where('uuid', $request->post_uuid)->firstOrFail();
                if ($post->user_id !== auth()->id()) {
                    return redirect()->route('posts.index', $request->category)
                        ->with('error', 'Unauthorized access.');
                }
                $post->update([
                    'title'       => $request->title,
                    'description' => $request->description,
                    'category'    => $request->category,
                    'society_id'  => $request->society_id,
                ]);
                $post->tags()->sync($request->tags);
                return redirect()->route('posts.view', [$request->type, $post->slug])
                    ->with('success', 'Post has been updated successfully.');
            }
            $request->validate([
                'slug' => 'required|string|unique:posts,slug',
            ]);
            $post = Post::create([
                'title'       => $request->title,
                'slug'        => $request->slug,
                'description' => $request->description,
                'category'    => $request->category,
                'user_id'     => auth()->id(),
                'society_id'  => $request->society_id,
            ]);
            $post->tags()->sync($request->tags);
            DeletePostIfNoComments::dispatch($post->id)->delay(now()->addDays(3));
            NewPostJob::dispatch($post->id, auth()->id());
            return redirect()->route('posts.index', $request->type)
                ->with('success', 'Post has been created successfully.');
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }


    public function postView(Request $request, $type, $slug, $report = null)
    {
        $user = Auth::user();
        $counts = $this->getPostCounts($user);
        if ($request->ajax() && $request->has('comment_id')) {
            $skip = (int) $request->skip;
            $replies = Comment::where('parent_id', $request->comment_id)
                ->with(['user', 'attachment', 'reactions', 'userReaction', 'parent'])
                ->withCount('replies')
                ->orderBy('created_at', 'asc')
                ->skip($skip)
                ->take(3)
                ->get();
            return response()->json([
                'replies' => $replies,
                'count' => $replies->count()
            ]);
        }

        $post = Post::where('slug', $slug)->first();
        $comments = Comment::where('post_id', $post->id)
            ->whereNull('parent_id')
            ->with(['user', 'attachment', 'reactions', 'userReaction', 'replies' => function ($query) {
                $query->with(['user', 'attachment', 'reactions', 'userReaction'])
                    ->withCount('replies')
                    ->orderBy('created_at', 'asc')
                    ->take(3);
            }])
            ->withCount('replies')
            ->orderBy('created_at', request('sort') === 'oldest' ? 'asc' : 'desc')
            ->paginate(8);
        $admin_tags = Tag::pluck('color', 'name')->toArray();
        $reportedIds = Report::where('user_id', auth()->id())
            ->where('reportable_type', Comment::class)
            ->pluck('reportable_id')
            ->toArray();
        return view('content.forum.post', compact('type', 'post', 'admin_tags', 'comments', 'counts', 'reportedIds', 'report'));
    }

    public function edit($type, $slug)
    {
        $post = Post::where('slug', $slug)->first();
        // Check if user owns this post
        if ($post->user_id !== auth()->id()) {
            return redirect()->route('posts.index', $type)->with('error', 'Unauthorized access.');
        }
        $user = Auth::user();
        $counts = $this->getPostCounts($user);
        $user->load('memberSocieties');
        $tags = Tag::all();
        $society_id = $user->memberSocieties->pluck('id')->first();
        return view('content.forum.create_or_edit', compact('type', 'society_id', 'tags', 'post', 'counts'));
    }

    public function destroy($uuid)
    {
        if (!Str::isUuid($uuid)) {
            return redirect()->back()->with('error', 'Invalid post identifier.');
        }
        try {
            $post = Post::where('uuid', $uuid)->firstOrFail();
            if (auth()->id() !== $post->user_id) {
                return redirect()->back()->with('error', 'You are not authorized to delete this post.');
            }
            $post->delete();
            return redirect()->back()->with('success', 'Post deleted successfully.');
        } catch (ModelNotFoundException $e) {
            return redirect()->back()->with('error', 'Post not found.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}