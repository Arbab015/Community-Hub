<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Models\Report;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Society;
use App\Models\Tag;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReportsController extends Controller
{

  public function index(Request $request)
  {
    $login_user = Auth::user();

    if ($request->ajax()) {
      $query = Report::with([
        'user',
        'reportable' => function (MorphTo $morphTo) {
          $morphTo->morphWith([
            Comment::class => ['post'],
          ]);
        },
      ])
        ->whereIn('id', function ($q) {
          $q->selectRaw('MAX(reports.id)')
            ->from('reports')
            ->leftJoin('comments', function ($join) {
              $join->on('reports.reportable_id', '=', 'comments.id')
                ->where('reports.reportable_type', Comment::class);
            })
            ->groupByRaw("COALESCE(comments.post_id, reports.reportable_id)");
        });

      $scope = SocietyAccessResolver::resolver($login_user);
      if ($scope['isSocietyScoped']) {
        $ownedSocietyIds = $scope['ownedSocietyIds'];

        $query->where(function ($q) use ($ownedSocietyIds) {
          $q->where(function ($q2) use ($ownedSocietyIds) {
            $q2->where('reportable_type', Post::class)
              ->whereIn('reportable_id', function ($sub) use ($ownedSocietyIds) {
                $sub->select('id')
                  ->from('posts')
                  ->whereIn('society_id', $ownedSocietyIds);
              });
          })
            ->orWhere(function ($q2) use ($ownedSocietyIds) {
              $q2->where('reportable_type', Comment::class)
                ->whereIn('reportable_id', function ($sub) use ($ownedSocietyIds) {
                  $sub->select('comments.id')
                    ->from('comments')
                    ->join('posts', 'comments.post_id', '=', 'posts.id')
                    ->whereIn('posts.society_id', $ownedSocietyIds);
                });
            });
        });
      }
      // else: no filter — show all reports

      return DataTables::of($query)
        ->addColumn('checkbox', function ($report) {
          $postId = $report->reportable->post
            ? $report->reportable->post->id
            : $report->reportable->id;
          return '<input type="checkbox" class="form-check-input checkbox" value="' . $postId . '">';
        })
        ->addColumn('post', function ($report) {
          if ($report->reportable) {
            $title      = $report->reportable->title ?? $report->reportable->post->title;
            $postId     = $report->reportable->post
              ? $report->reportable->post->id
              : $report->reportable->id;
            $url        = route('reports.show', $postId);
            $shortTitle = Str::limit($title, 55, '...');
            return '<a href="' . $url . '" title="' . $title . '" class="badge bg-label-info text-truncate d-inline-block">'
              . $shortTitle . '</a>';
          }
          return '';
        })
        ->addColumn('no_of_reports', function ($report) {
          if ($report->reportable) {
            $post           = $report->reportable->post ?? $report->reportable;
            $postReports    = $post->reports()->count();
            $commentReports = Report::where('reportable_type', Comment::class)
              ->whereIn('reportable_id', $post->comments()->pluck('id'))
              ->count();
            return $postReports + $commentReports;
          }
        })
        ->addColumn('actions', function ($report) use ($login_user) {
          if (!$report->reportable) return '';
          $url = route(
            'reports.show',
            $report->reportable->post ? $report->reportable->post->id : $report->reportable->id
          );
          $view = '';
          if ($login_user->can('view_post_reports')) {
            $view = '<a href="' . $url . '" class="pe-3">
                        <i class="fa-solid fa-eye text-primary" role="button" title="View details"></i>
                    </a>';
          }
          return $view;
        })
        ->rawColumns(['checkbox', 'actions', 'post'])
        ->make(true);
    }

    $can_edit     = $login_user->can('edit_user');
    $show_actions = $can_edit;
    return view("content.reports.index", compact('show_actions'));
  }

    public function store(Request $request)
    {
      $related_comments_ids = "";
      if ($request->reportable_type == Comment::class) {
        $comment = Comment::findOrFail($request->reportable_id);
        $related_comments_ids = $comment->replies->pluck('id');
      }
        $request->validate([
            'reportable_id'   => 'required|integer',
            'reportable_type' => 'required|in:post,comment',
            'reason'     => 'required|max:100',
        ]);
        $modelMap = [
            'post'    => Post::class,
            'comment' => Comment::class,
        ];
        $modelClass = $modelMap[$request->reportable_type];
        $model = $modelClass::findOrfail($request->reportable_id);

        // Check if already reported
        $alreadyReported = Report::where('user_id', auth()->id())
            ->where('reportable_id', $request->reportable_id)
            ->where('reportable_type', $modelClass)
            ->exists();

        if ($alreadyReported) {
          return response()->json(['message' => 'You have already reported this.'], 422);
        }

        Report::create([
            'user_id'         => auth()->id(),
            'reportable_id'   => $model->id,
            'reportable_type' => $modelClass,
            'reason'          => $request->reason,
        ]);
        return response()->json(['message' => 'Report submitted successfully.', "ids" => $related_comments_ids ]);
    }


    public function show($id)
    {
        // dd($type);
        $post  = Post::with(['reports.user', 'user'])->findOrFail($id);
        // reported comments of this post
        $reportedComments = $post->comments()
            ->whereHas('reports')
            ->with(['reports.user', 'user'])
            ->latest()
            ->get();

        $reports = $post->reports()->with('user')->latest()->get();
        return view('content.reports.view', compact('post', 'reports', 'reportedComments'));
    }

    public function dismissReport($id)
    {
        try {
            // dd($id);
            $report = Report::findOrFail($id);
            $report->delete();
            return redirect()->back()->with('success', 'Report Delete successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function takeAction(Request $request, $type, $id)
    {
      try {
        $item = $type == 'post' ? Post::findOrFail($id) : Comment::findOrFail($id);
        $item->reports()->delete();
        if ($type == 'post') {
          $item->blocked = true;
          $item->save();
          $message = "Post blocked successfully.";
        } else {
          $item->delete();
          $message = "Message deleted successfully.";
        }
        return redirect()->route('reports.index')->with('success', $message);
      } catch (Exception $e) {
        return back()->with('error', $e->getMessage());
      }
    }


  public function bulkDelete(Request $request)
  {
    try {
      foreach ($request->ids as $id) {
        $post = Post::findOrFail($id);
        $post->reports()->delete();
        foreach ($post->comments as $comment) {
          $comment->reports()->delete();
        }
      }
      return response()->json(['success' => true]);
    } catch (Exception $e) {
      return redirect()->back()->withInput()->with('error', $e->getMessage());
    }
  }
}
