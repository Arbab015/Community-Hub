<?php

namespace App\Http\Controllers;

use App\Mail\SocietyBlockedMail;
use App\Models\Post;
use App\Models\Society;
use App\Models\User;
use App\Services\FileServices;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SocietiesController extends Controller
{
    protected $compressor;
    public function __construct(FileServices $compressor)
    {
        $this->compressor = $compressor;
    }

    public function create($slug)
    {
        $user = Auth::user();
        return view('content.societies.create',  compact('slug'));
    }

    public function index(Request $request, $slug)
    {
        $skip = $request->input('skip', 0);
        $total_skip = 6 + $skip;
        $take = 6;
        $query = Society::with(['attachment', 'owner'])->latest();
        if ($slug == "owner_societies") {
            $query->where('owner_id', auth()->id());
        }
        $query->when($request->name, fn($q) => $q->where('name', $request->name));
        $query->when($request->city, fn($q) => $q->where('city', $request->city));
        $query->when($request->status, fn($q) => $q->where('status', $request->status));
        $query->when($request->owner, fn($q) => $q->where('owner_id', $request->owner));
        $total_societies = $query->count();
        $societies = $query->skip($skip)->take($take)->get();
        $dropdownQuery = Society::query();
        if ($slug == "owner_societies") {
            $dropdownQuery->where('owner_id', auth()->id());
        }
        $societyNames = $dropdownQuery->distinct('id')->pluck('name');
        $cities = $dropdownQuery->distinct('id')->pluck('city');
        $owners = $slug == "owner_societies"
            ? collect()
            : User::whereHas('roles', fn($q) => $q->where('name', 'Society Owner'))->get();

        if ($request->ajax()) {
            return response()->json([
                'societies' => $societies,
                'total_societies' => $total_societies,
                'total_skip' => $total_skip,
            ]);
        }
        return view('content.societies.index', compact(
            'societies',
            'total_societies',
            'total_skip',
            'slug',
            'societyNames',
            'cities',
            'owners'
        ));
    }


    public function storeOrUpdate(Request $request, $slug, $uuid = null)
    {
        $type = $type = strtolower($request->input('type', ''));
        $is_picture   = $request->hasFile('main_pic');
        $is_documents = $request->hasFile('documents');
        $is_basic     = !$is_picture && !$is_documents;
        $rules = match (true) {
            $is_picture => [
                'main_pic' => 'required|image|max:20000',
            ],
            $is_documents => [
                'documents' => 'required|array',
                'documents.*' => match ($type) {
                    'media' => [
                        'file',
                        'mimes:jpg,jpeg,png,gif,svg,mp4,mov,avi,webm,mkv',
                        'max:20000',
                    ],
                    'document' => [
                        'file',
                        'mimes:pdf,doc,docx,xls,xlsx',
                        'max:20000',
                    ],
                    default => [
                        'file',
                        'mimes:jpg,jpeg,png,gif,svg,mp4,mov,avi,webm,mkv,pdf,doc,docx,xls,xlsx',
                        'max:20000',
                    ],
                },
            ],
            default => [
                'name' => 'required|string|min:6',
                'country' => 'required|string|max:50',
                'city' => 'required|string|max:100',
                'address' => 'required|string|max:255',
            ],
        };
        // Creation requires basic info even if files come first
        if (!$uuid) {
            $rules += [
                'name' => 'required|string|min:6',
                'country' => 'required|string|max:50',
                'city' => 'required|string|max:100',
                'address' => 'required|string|max:255',
            ];
        }
        $request->validate($rules);
        try {
            DB::beginTransaction();
            $society = $uuid ? Society::where('uuid', $uuid)->firstOrFail() : new Society(['owner_id' => Auth::id()]);
            if ($is_basic || !$uuid) {
                $society->fill($request->only(['name', 'country', 'city', 'address']))->save();
            }
            if ($is_picture) {
                if ($society->attachment && Storage::disk('public')->exists($society->attachment->link)) {
                    //delete form disk
                    Storage::disk('public')->delete($society->attachment->link);
                }
                app(FileServices::class)->compressAndStore(
                    $request->file('main_pic'),
                    $society,
                    true,
                    true
                );
            }
            if ($is_documents) {
                app(FileServices::class)->compressAndStore(
                    $request->file('documents'),
                    $society,
                    false
                );
            }
            DB::commit();
            if ($uuid) {
                return back()->with('Society updated successfully!');
            } else {
                return redirect()->route('societies.index', $slug)->with('success', 'Society created successfully!');
            }
        } catch (Exception $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', $e->getMessage());
        }
    }


    public function show($slug, $uuid)
    {
        $society = Society::where('uuid', $uuid)->with(['attachment', 'attachments'])->first();
        $documents = $society->attachments->filter(function ($attachment) {
            return in_array($attachment->extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx']);
        });
        // dd($society->posts);
        $counts = [
            'discussionsCount' => $society->posts()
                ->where('category', 'discussion')
                ->count(),

            'suggestionsCount' => $society->posts()
                ->where('category', 'suggestion')
                ->count(),

            'issuesCount' => $society->posts()
                ->where('category', 'issue')
                ->count(),
        ];
        $discussions = Post::with(['user', 'tags', 'likes', 'dislikes', 'comments'])
            ->where('society_id', $society->id)
            ->where('category', 'discussion')
            ->latest()
            ->paginate(10, ['*'], 'discussion_page');
        $suggestions = \App\Models\Post::with(['user', 'tags', 'likes', 'dislikes', 'comments'])
            ->where('society_id', $society->id)
            ->where('category', 'suggestion')
            ->latest()
            ->paginate(10, ['*'], 'suggestion_page');
        $issues = \App\Models\Post::with(['user', 'tags', 'likes', 'dislikes', 'comments'])
            ->where('society_id', $society->id)
            ->where('category', 'issue')
            ->latest()
            ->paginate(10, ['*'], 'issue_page');
        $admin_tags = \App\Models\Tag::all()->pluck('color', 'name');
        $reportedIds = \App\Models\Report::where('user_id', auth()->id())
            ->where('reportable_type', \App\Models\Post::class)
            ->pluck('reportable_id')
            ->toArray();
        $user = Auth::user();
        return view('content.societies.show', compact(
            'slug',
            'uuid',
            'society',
            'discussions',
            'suggestions',
            'issues',
            'admin_tags',
            'reportedIds',
            'counts',
            'documents'
        ));
    }

    public function destroy($id)
    {
        try {
            app(FileServices::class)->deleteByIds([$id]);
            return redirect()
                ->back()
                ->with('success', 'File deleted successfully');
        } catch (\Throwable $e) {
            report($e);
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }


    public function bulkDelete(Request $request)
    {
        try {
            app(FileServices::class)->deleteByIds($request->ids);
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteSociety($slug, $uuid)
    {
        try {
            $requested_society = Society::where('uuid', $uuid)->first();
            $requested_society->delete();
            return redirect()->route('societies.index', compact('slug'))->with('success', "Society has been deleted successfully.");
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }


    public function blockSociety($slug, $uuid)
    {
        try {
            $requested_society = Society::with('owner')->where('uuid', $uuid)->first();
            if ($requested_society) {
                $requested_society->update(['status' => 'in-active']);
            }

            Mail::to($requested_society->owner->email)
                ->send(new SocietyBlockedMail($requested_society));
            return redirect()->route('societies.index', compact('slug'))->with('success', "Society has been blocked successfully.");
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }
}
