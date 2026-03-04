<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Post;
use App\Models\Comment;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $login_user = Auth::user();
        if ($request->ajax()) {
            return DataTables::of(
                Report::with([
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
                            ->groupByRaw("COALESCE( comments.post_id,reports.reportable_id)");
                    })
            )
                ->addColumn('checkbox', function ($report) {
                    return '<input type="checkbox" class="form-check-input checkbox"
                value="' . $report->id . '">';
                })

                ->addColumn('post', function ($report) {
                    if ($report->reportable) {
                        return $report->reportable->title ?? $report->reportable->post->title;
                    }
                    return '';
                })

                ->addColumn('no_of_reports', function ($report) {
                    if ($report->reportable) {
                        $post = $report->reportable->post ?? $report->reportable;
                        $postReports = $post->reports()->count();
                        $commentReports = Report::where('reportable_type', Comment::class)
                            ->whereIn('reportable_id', $post->comments()->pluck('id'))
                            ->count();
                        return $postReports + $commentReports;
                    }
                    return 0;
                })

                ->addColumn('actions', function ($report) use ($login_user) {
                    $view = "";
                    $delete = "";
                    if (!$report->reportable) return '';
                    $url  = route(
                        'reports.show',
                        $report->reportable->post ? $report->reportable->post->id : $report->reportable->id
                    );
                    if ($login_user->can('view_post_reports')) {
                        $view = '<a href="' . $url . '" class="pe-3">
                    <i class="fa-solid fa-eye text-primary"
                       role="button"
                       title="View details"></i>
                 </a>';
                    }
                    if ($login_user->can('delete_reports')) {
                        $delete =  '<form action="' . route('reports.dismiss', [$report->id]) . '" method="POST" style="display:inline;">'
                            . csrf_field()
                            . method_field('DELETE')
                            . '<i class="fa-solid fa-trash-can text-danger" role="button" title="Delete" onclick="confirmDelete(event)">
                                </i>'
                            . '</form>';
                        return $view . ' ' . $delete;
                    }
                })
                ->rawColumns(['checkbox', 'actions'])
                ->make(true);
        }
        $can_edit = $login_user->can('edit_user');
        $can_delete = $login_user->can('delete_user');
        $show_actions = $can_edit || $can_delete;
        return view("content.reports.index", compact('show_actions'));
    }


    public function store(Request $request)
    {
        $request->validate([
            'reportable_id'   => 'required|integer',
            'reportable_type' => 'required|in:post,comment',
            'type' =>  'required|in:spam,misleading,hate_speech,harassment,violence,adult_content,scam,copyright,illegal_activity,off_topic,other',
            'reason'     => 'required|max:500',
        ]);
        $modelMap = [
            'post'    => Post::class,
            'comment' => Comment::class,
        ];
        $modelClass = $modelMap[$request->reportable_type];
        $model = $modelClass::findOrFail($request->reportable_id);

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
            'type' => $request->type,
        ]);
        return response()->json(['message' => 'Report submitted successfully.']);
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

        dd($reportedComments);
        $reports = $post->reports()->with('user')->latest()->get();
        return view('content.reports.view', compact('post', 'reports',));
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
            if ($type === 'post') {
                $post = Post::findOrFail($id);
                $post->reports()->delete();
                $post->blocked = "blocked";
            } else {
                $comment = Comment::findOrFail($id)->delete();
                $comment->reports()->delete();
                $comment->delete();
            }
            return redirect()->route('reports.index')->with('success', ucfirst($type) . ' deleted successfully.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}