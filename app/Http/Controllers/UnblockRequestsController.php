<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Models\Post;
use App\Models\Society;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;
class UnblockRequestsController extends Controller
{
  public function index(Request $request, $uuid = null)
  {
    $login_user = auth()->user();
    $query = Post::latest()
      ->where('blocked', true)
      ->where('is_unblock_requested', true);
    if ($uuid) {
      $society = Society::where('uuid', $uuid)->firstOrFail();
      $query->where('society_id', $society->id);
    }
    else {
    $scope = SocietyAccessResolver::resolver($login_user);
    if ($scope['isSocietyScoped']) {
      $query->whereIn('society_id', $scope['ownedSocietyIds']);
    }
    }
    if ($request->ajax()) {
      return DataTables::of($query->get())
        ->addColumn('checkbox', function ($post) {
          return '<input type="checkbox" class="form-check-input checkbox" title="Select Record" value="' . $post->id . '">';
        })
        ->addColumn('post', function ($post)  use ($uuid) {
          $category = $post->category . 's';
          $url = route('posts.view', [
              'type' => $category,
              'slug' => $post->slug,
              'request_on' => "unblock_requested_post",
              'in_society' => $uuid ? true : false,
            ]);
          $short_title = Str::limit($post->title, 65, '...');
          return '<a href="' . $url . '" class="badge bg-label-info text-truncate d-inline-block" >'
            . $short_title .
            '</a>';
        })

        ->addColumn('actions', function ($post) use ($login_user) {
          $cancel = "";
          $accept = "";
          if ($login_user->can('un-block_post')) {
            $accept = '<form action="' . route('requests.approve', $post->uuid) . '" method="POST" style="display:inline;" >'
              . csrf_field()
              . '<button type="button" class="btn btn-sm btn-outline-success" onclick="confirmDelete(event, true)"  >
              <i class="ti tabler-check text-success me-1 " ></i> Approve
           </button>'
              . '</form>';
          }
          if ($login_user->can('cancel_unblock_request_post')) {
            $cancel = '<form action="' . route('requests.cancel', $post->uuid) . '" method="POST" style="display:inline;">'
              . csrf_field()
              . '<button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(event, false)">
            <i class="ti tabler-x text-danger me-1"></i> Cancel
          </button>'
              . '</form>';
          }
          return $cancel . '  ' . $accept;
        })
        ->rawColumns(['checkbox', 'post', 'actions'])
        ->make(true);
    }
    $show_actions = $login_user->can('cancel_unblock_request_post');
    return view('content.societies.requested_posts', compact('show_actions', 'uuid'));
  }


  public function requestCancel($uuid)
  {
    if (!Str::isUuid($uuid)) {
      return redirect()->back()->with('error', 'Invalid post identifier.');
    }
    try {
      $post = Post::where('uuid', $uuid)->firstOrFail();
      abort_if(!auth()->user()->can('un-block_post'), 403, 'Unauthorized.'); // user who unblock post cancel also reject request for a post
      $post->is_unblock_requested = false;
      $post->save();
      return back()->with('success', 'Request cancelled successfully.');
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
      return back()->with('error', 'Post not found.');
    } catch (\Exception $e) {
      return back()->with('error', $e->getMessage());
    }
  }

  public function requestApprove($uuid)
  {
    if (!Str::isUuid($uuid)) {
      return redirect()->back()->with('error', 'Invalid post identifier.');
    }
    try {
      $post = Post::where('uuid', $uuid)->firstOrFail();
      abort_if(!auth()->user()->can('un-block_post'), 403, 'Unauthorized.');
      if ($post->blocked && $post->is_unblock_requested ) {
        $post->blocked = false;
        $post->is_unblock_requested = false;
        $post->save();
        $message = "Request accepted successfully.";
      } elseif (!$post->blocked) {
        return back()->with('error', 'Post is already unblocked.');
      } else {
        $message = "Something went wrong while requesting to unblock this post.";
      }
      return back()->with('success', $message);
    }  catch (\Exception $e) {
      return back()->with('error', $e->getMessage());
    }
  }

}
