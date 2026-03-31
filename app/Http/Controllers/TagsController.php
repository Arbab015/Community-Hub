<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class TagsController extends Controller
{
    public function index(Request $request)
    {
        $login_user = Auth::user();
        if ($request->ajax()) {
            return DataTables::of(Tag::orderBy("id", "desc"))
                ->addColumn('checkbox', function ($tag) {
                    return '<input type="checkbox" class="form-check-input checkbox" title="Select Record" value="' . $tag->id . '">';
                })
                ->addColumn('name', function ($tag) {
                    $color = normalize_color($tag->color);
                    return '<span class="badge" style="background-color:' . $color . '; color:#fff;">'
                        . e($tag->name) .
                        '</span>';
                })
                ->addColumn('actions', function ($tag) use ($login_user) {
                    $edit = "";
                    $delete = "";
                    if ($login_user->can('edit_tag')) {
                        $edit = '<i class="fa-solid fa-pen-to-square text-primary editTagBtn"
                         role= "button"
                         title="Edit"
                          data-id="' . ($tag->id) . '"
                         data-name="' . ($tag->name) . '"
                          data-color="' . ($tag->color) . '">
                        </i>';
                    }
                    if ($login_user->can('delete_tag')) {
                        $delete =  '<form action="' . route('tags.destroy', $tag->id) . '" method="POST" style="display:inline;">'
                            . csrf_field()
                            . method_field('DELETE')
                            . '<i class="fa-solid fa-trash-can text-danger" role="button" title="Delete" onclick="confirmDelete(event)">
                                </i>'
                            . '</form>';
                    }
                   return $edit . ' ' . $delete;
                })
                ->rawColumns(['actions', 'checkbox', 'name'])
                ->make(true);
        }
        $can_edit = $login_user->can('edit_tag');
        $can_delete = $login_user->can('delete_tag');
        $show_actions = $can_edit || $can_delete;
        return view("content.tags.index", compact('show_actions'));
    }

    public function storeOrUpdate(Request $request)
    {
        try {
            $request->validate([
                'id'   => 'nullable|exists:tags,id',
                'name' => [
                    'required',
                    Rule::unique('tags', 'name')->ignore($request->id),
                ],
                'color' => 'required',
            ]);

            Tag::updateOrCreate(
                ['id' => $request->id],
                [
                    'name'  => $request->name,
                    'color' => $request->color,
                ]
            );

            return back()->with('success', 'Tag Saved Successfully.');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $jobtype = Tag::findorfail($id);
            $jobtype->delete();
            return back()->with('success', 'Tag deleted successfully. ');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function bulkDelete(Request $request)
    {
        try {
            Tag::whereIn('id', $request->ids)->delete();
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}
