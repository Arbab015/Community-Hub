<?php

namespace App\Http\Controllers;

use App\Mail\SocietyBlockedMail;
use App\Mail\SocietyUnBlockMail;
use App\Models\Attachment;
use App\Models\Post;
use App\Models\Report;
use App\Models\Rule;
use App\Models\Society;
use App\Models\Tag;
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
    public function create($slug, $uuid = null)
    {
        $society = $uuid ? Society::where('uuid', $uuid)->first() : null;
        $tab = request('tab', 'basic');

        return view('content.societies.create', compact('slug', 'society', 'tab'));
    }

    public function index(Request $request, $slug)
    {
        $user_type = $slug;
        $skip = $request->input('skip', 0);
        $total_skip = 6 + $skip;
        $take = 6;
        $query = Society::with(['attachment', 'owner'])->latest();
        $user_role = auth()->user()->roles()->first();
        if ($user_type == 'owner_societies' && $user_role->name == 'Society Owner') {
            $query->where('owner_id', auth()->id());
        }
        $query->when($request->name, function ($q) use ($request) {
            $search = $request->name;
            $q->where(function ($subQ) use ($search) {
                $subQ->where('name', 'LIKE', "%{$search}%");
            });
        });
        $query->when($request->city, fn ($q) => $q->where('city', $request->city));
        $query->when($request->status, fn ($q) => $q->where('status', $request->status));
        $query->when($request->owner, fn ($q) => $q->where('owner_id', $request->owner));
        $total_societies = $query->count();
        $societies = $query->skip($skip)->take($take)->get();
        $dropdownQuery = Society::query();
        if ($user_type == 'owner_societies') {
            $dropdownQuery->where('owner_id', auth()->id());
        }
        $cities = $dropdownQuery->distinct('id')->pluck('city');
        $owners = $user_type == 'owner_societies'
          ? collect()
          : User::whereHas('roles', fn ($q) => $q->where('name', 'Society Owner'))->get();

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
            'user_type',
            'cities',
            'owners'
        ));
    }

    public function storeOrUpdate(Request $request, $slug, $uuid = null)
    {
        //        dd($request->all());
        if (! $uuid && $request->society_id) {
            $uuid = Society::find($request->society_id)->uuid;
        }
        //        dd($uuid);
        $type = strtolower($request->input('type', ''));
        $is_picture = $request->hasFile('main_pic');
        $is_documents = $request->hasFile('documents');
        $is_basic = ! $is_picture && ! $is_documents;
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
                'postal_code' => 'required|numeric',
                'marla_size' => ['required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],
            ],
        };
        // Creation requires basic info even if files come first
        if (! $uuid) {
            $rules += [
                'name' => 'required|string|min:6',
                'country' => 'required|string|max:50',
                'city' => 'required|string|max:100',
                'address' => 'required|string|max:255',
                'postal_code' => 'required|numeric',
                'marla_size' => ['required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],
            ];
        }
        $request->validate($rules);
        try {
            DB::beginTransaction();
            $society = $uuid ? Society::where('uuid', $uuid)->firstOrFail() : new Society(['owner_id' => Auth::id()]);

            if ($is_basic || ! $uuid) {
                $society->fill($request->only(['name', 'country', 'city', 'address', 'postal_code', 'marla_size']))->save();
            }
            if ($request->deleted_files) {
                $ids = json_decode($request->deleted_files, true);
                Attachment::whereIn('id', $ids)->delete();
            }
            $newAttachmentIds = [];
            $mainPicId = null;

            if ($is_picture) {
                if ($society->attachment && Storage::disk('public')->exists($society->attachment->link)) {
                    Storage::disk('public')->delete($society->attachment->link);
                }
                $ids = app(FileServices::class)->compressAndStore(
                    $request->file('main_pic'),
                    $society,
                    true,
                    true
                );
                $mainPicId = $ids[0] ?? null;
                $newAttachmentIds = is_array($ids) ? $ids : [];
            }

            if ($is_documents) {
                $ids = app(FileServices::class)->compressAndStore(
                    $request->file('documents'),
                    $society,
                    false
                );
                $newAttachmentIds = is_array($ids) ? $ids : [];
            }

            DB::commit();

            // AJAX response — same shape as PropertiesController
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Files uploaded successfully.',
                    'attachment_ids' => $newAttachmentIds,
                    'main_pic_id' => $mainPicId,
                ]);
            }

            // In storeOrUpdate, replace the non-ajax success redirect for new society:
            if ($uuid && ! $request->society_id) {
                return back()->with('success', 'Society updated successfully!');
            } else {
                // ← CHANGE THIS: redirect to documents tab instead of index
                return redirect()->route('society.create', [$slug, $society->uuid, 'tab' => 'documents'])
                    ->with('success', 'Society created. Now add documents.');
            }

        } catch (Exception $e) {
            DB::rollBack();
            report($e);

            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function show($user_type, $uuid)
    {
        $society = Society::where('uuid', $uuid)->with(['attachment', 'attachments'])->first();
        $documents = $society->attachments->filter(function ($attachment) {
            return in_array($attachment->extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx']);
        });
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
        $discussions = Post::orderByDesc('is_pinned')->with(['user', 'tags', 'likes', 'dislikes', 'comments'])
            ->where('society_id', $society->id)
            ->where('category', 'discussion')
            ->latest()
            ->paginate(10, ['*'], 'discussion_page');
        $suggestions = Post::orderByDesc('is_pinned')->with(['user', 'tags', 'likes', 'dislikes', 'comments'])
            ->where('society_id', $society->id)
            ->where('category', 'suggestion')
            ->latest()
            ->paginate(10, ['*'], 'suggestion_page');
        $issues = Post::orderByDesc('is_pinned')->with(['user', 'tags', 'likes', 'dislikes', 'comments'])
            ->where('society_id', $society->id)
            ->where('category', 'issue')
            ->latest()
            ->paginate(10, ['*'], 'issue_page');
        $admin_tags = Tag::all()->pluck('color', 'name');
        $reportedIds = Report::where('user_id', auth()->id())
            ->where('reportable_type', \App\Models\Post::class)
            ->pluck('reportable_id')
            ->toArray();
        $user = Auth::user();
        $rules = Rule::where('society_owner_id', $society->owner_id)->get();

        //

        return view('content.societies.show', compact(
            'user_type',
            'uuid',
            'society',
            'rules',
            'discussions',
            'suggestions',
            'issues',
            'admin_tags',
            'reportedIds',
            'counts',
            'documents',
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

    public function deleteSociety($slug, $uuid)
    {
        try {
            $requested_society = Society::where('uuid', $uuid)->first();
            $requested_society->delete();

            return redirect()->route('societies.index', compact('slug'))->with('success', 'Society has been deleted successfully.');
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
            if ($requested_society->status == 'active') {
                $requested_society->update(['status' => 'in-active']);
                $message = 'Society has been blocked successfully.';
                Mail::to($requested_society->owner->email)
                    ->send(new SocietyBlockedMail($requested_society));
            } else {
                $requested_society->update(['status' => 'active']);
                $message = 'Society has been unblocked successfully.';
                Mail::to($requested_society->owner->email)
                    ->send(new SocietyUnBlockMail($requested_society));
            }

            return redirect()->route('societies.index', compact('slug'))->with('success', $message);
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function renderPosts($user_type, $uuid, $type, $slug)
    {
        $society = Society::where('uuid', $uuid)->first();
        $type = rtrim($type, 's');
        $counts = [
            'discussionsCount' => $society->posts()->where('category', 'discussion')->count(),
            'suggestionsCount' => $society->posts()->where('category', 'suggestion')->count(),
            'issuesCount' => $society->posts()->where('category', 'issue')->count(),
        ];
        $query = Post::with(['user', 'tags', 'likes', 'dislikes', 'comments'])
            ->where('society_id', $society->id)
            ->where('category', $type)
            ->orderByDesc('is_pinned')
            ->latest();
        if ($slug === 'my_posts') {
            $query->where('user_id', auth()->id());
        } elseif ($slug === 'blocked_posts') {
            $query->where('blocked', 1)->where('is_unblock_requested', 0);
        } elseif ($slug === 'requested_posts') {
            $query->where('blocked', 1)->where('is_unblock_requested', 1);
        }
        $posts = $query->paginate(10);
        $reportedIds = [];
        $rules = Rule::where('society_owner_id', $society->owner_id)->get();
        logger($type);
        $type = $type.'s';
        logger($type);

        return view('content.societies.me', compact(
            'user_type', 'uuid', 'type', 'posts', 'society', 'counts', 'rules', 'reportedIds', 'slug'
        ));
    }
}
