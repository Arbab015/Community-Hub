<?php

namespace App\Http\Controllers;

use App\Jobs\DeletePostIfNoComments;
use App\Jobs\NewPostJob;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\Society;
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
    $activeSocietyId = session('active_society_id');
    return [
      'discussionsCount' => Post::where('society_id', $activeSocietyId)
        ->where('category', 'discussion')->count(),
      'suggestionsCount' => Post::where('society_id', $activeSocietyId)
        ->where('category', 'suggestion')->count(),
      'issuesCount' => Post::where('society_id', $activeSocietyId)
        ->where('category', 'issue')->count(),
    ];
  }


    public function index($type, $uuid = null)
    {
      $user = auth()->user();
      $activeSocietyId = session('active_society_id');
      $query = Post::orderByDesc('is_pinned')->with(['user', 'tags'])
        ->where('society_id', $activeSocietyId);
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
            ->where('reportable_type', Post::class)
            ->pluck('reportable_id')
            ->toArray();
        $society = Society::findOrFail(session('active_society_id'));
        return view('content.forum.index', compact(
            'posts',
            'category',
            'type',
            'admin_tags',
            'counts',
            'reportedIds',
          'society'
        ));
    }

    public function createAdminView($user_type, $uuid , $type){
        return $this->createView($type, $uuid , $user_type);
    }
  public function create($type)
  {
     return $this->createView($type, null, null);
  }

    public function createView($type , $uuid = null, $user_type = null)
    {
      try{
        $society = null;
      if ($uuid) {
        $society = Society::where('uuid', $uuid)->firstOrFail();
        $society_id = $society->id;
      } else {
        $society_id = session('active_society_id');

      }
      $user = Auth::user();
//        $user->load('memberSocieties');
      $tags = Tag::all();
      $counts = $this->getPostCounts($user);
//        $society_id = $user->memberSocieties->pluck('id')->first();
      return view('content.forum.create_or_edit', compact('society_id', 'type', 'tags', 'counts', 'user_type', 'uuid'));
    } catch (Exception $e) {
     return redirect()->back()->with('error', $e->getMessage());
     }
    }

    public function storeOrUpdate(Request $request)
    {
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

              $route = $request->user_type ? 'society_posts.view' : 'posts.view';
              $params = $request->user_type ? [
                'user_type' => $request->user_type,
                'uuid' => $request->uuid,
                'type' => $request->type,
                'slug' => $post->slug
              ] : [
                'type' => $request->type,
                'slug' => $post->slug
              ];
              return redirect()->route($route, $params)->with('success', 'Post has been updated successfully.');
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
            DeletePostIfNoComments::dispatch($post->id)->delay(now()->addDays(10));
            NewPostJob::dispatch($post->id, auth()->id());

          $route = $request->user_type ? 'society_posts.view' : 'posts.view';
          $params = $request->user_type ? [
              'user_type' => $request->user_type,
              'uuid' => $request->uuid,
              'type' => $request->type,
              'slug' => $post->slug
            ] : [
              'type' => $request->type,
              'slug' => $post->slug
            ];
          return redirect()->route($route, $params)->with('success', 'Post has been created successfully.');
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }


  public function societyPostView(Request $request, $user_type, $uuid, $type, $slug, $report = null)
  {
    return $this->viewPostDetails($request, $type, $slug, $uuid, $user_type, $report);
  }

  public function postView(Request $request, $type, $slug, $report = null)
  {
    return $this->viewPostDetails($request, $type, $slug, null, null, $report);
  }

  private function viewPostDetails(Request $request, $type, $slug, $uuid = null, $user_type = null, $report = null)
  {
    $user = Auth::user();
    $counts = $this->getPostCounts($user);

    $reportedIds = Report::where('user_id', auth()->id())
      ->where('reportable_type', Comment::class)
      ->pluck('reportable_id')
      ->toArray();

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
        'count' => $replies->count(),
        'reportedIds' => $reportedIds
      ]);
    }

    $post = Post::where('slug', $slug)->firstOrFail();
    $comments = Comment::where('post_id', $post->id)
      ->whereNull('parent_id')
      ->with([
        'user',
        'attachment',
        'reactions',
        'userReaction',
        'replies' => function ($query) {
          $query->with(['user', 'attachment', 'reactions', 'userReaction'])
            ->withCount('replies')
            ->orderBy('created_at', 'asc')
            ->take(3);
        }
      ])
      ->withCount('replies')
      ->orderBy('created_at', request('sort') === 'oldest' ? 'asc' : 'desc')
      ->paginate(8);

    $admin_tags = Tag::pluck('color', 'name')->toArray();

    return view('content.forum.post', [
      'type'        => $type,
      'post'        => $post,
      'admin_tags'  => $admin_tags,
      'comments'    => $comments,
      'counts'      => $counts,
      'reportedIds' => $reportedIds,
      'report'      => $report,
      'society'     => $uuid ? Society::where('uuid', $uuid)->first() : Society::findOrFail(session('active_society_id')),
      'user_type'   => $user_type,
    ]);
  }


  public function editInAdmin($user_type, $uuid, $type, $slug)
  {
    return $this->editView($type, $slug , $user_type, $uuid);
  }
  public function edit($type, $slug){
    return $this->editView($type, $slug,  null , null);
  }

    public function editView($type, $slug, $user_type = null , $uuid = null )
    {
        $post = Post::where('slug', $slug)->first();
        $society_id = $post->society_id;
        // Check if user owns this post
        if ($post->user_id !== auth()->id()) {
            return redirect()->route('posts.index', $type)->with('error', 'Unauthorized access.');
        }
        $user = Auth::user();
        $counts = $this->getPostCounts($user);
        $user->load('memberSocieties');
        $tags = Tag::all();
        return view('content.forum.create_or_edit', compact('type', 'society_id', 'tags', 'post', 'counts', 'user_type', 'uuid'));
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


    public function  postPin($uuid){
      if (!Str::isUuid($uuid)) {
        return redirect()->back()->with('error', 'Invalid post identifier.');
      }
      try {
        $post = Post::where('uuid', $uuid)->firstOrFail();
        abort_if(!auth()->user()->can('can_pin'), 403, 'Unauthorized.'); // un-authorized person not pin this post
        if($post->is_pinned == true){
          $post->is_pinned = false;
          $message = "Post un-pinned successfully";
        }
        else{
          $post->is_pinned = true;
          $message = "Post pinned successfully";

        }
        $post->save();
        return redirect()->back()->with('success', $message);
      } catch (ModelNotFoundException $e) {
        return redirect()->back()->with('error', 'Post not found.');
      } catch (Exception $e) {
        return redirect()->back()->with('error', $e->getMessage());
      }
    }
}
