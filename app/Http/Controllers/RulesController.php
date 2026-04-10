<?php

namespace App\Http\Controllers;

use App\Models\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Mockery\Exception;
use Yajra\DataTables\DataTables;

class RulesController extends Controller
{
    public function index(Request $request)
    {
        $login_user = Auth::user();
        if ($request->ajax()) {
            return DataTables::of(Rule::where('society_owner_id', $login_user->id)->orderBy('id', 'desc'))
                ->addColumn('checkbox', function ($rule) {
                    return '<input type="checkbox" class="form-check-input checkbox" title="Select Record" value="'.$rule->id.'">';
                })
                ->addColumn('description', function ($rule) {
                    $short_description = Str::limit($rule->description, 50, '...');

                    return '<span class="badge bg-label-info me-1" title="'.$rule->description.'"> '.$short_description.' </span>';
                })
                ->addColumn('related_to', function ($rule) {
                    $badges = '';
                    if (! empty($rule->related_to)) {
                        foreach ($rule->related_to as $item) {
                            $badges .= '<span class="badge bg-label-primary me-1">'
                              .ucfirst($item).
                              '</span>';
                        }
                    }

                    return $badges ?: '-';
                })
                ->addColumn('actions', function ($rule) use ($login_user) {
                    $edit = '';
                    $delete = '';
                    if ($login_user->can('edit_rule')) {
                        $edit = "<i class='fa-solid fa-pen-to-square text-primary editRuleBtn'
            data-id='{$rule->id}'
            data-name='{$rule->name}'
            data-description='{$rule->description}'
            data-related_to='".json_encode($rule->related_to)."'
        ></i>";
                    }
                    if ($login_user->can('delete_rule')) {
                        $delete = '<form action="'.route('rules.destroy', $rule->id).'" method="POST" style="display:inline;">'
                          .csrf_field()
                          .method_field('DELETE')
                          .'<i class="fa-solid fa-trash-can text-danger ms-2" role="button" title="Delete" onclick="confirmDelete(event)">
                                </i>'
                          .'</form>';

                        return $edit.' '.$delete;
                    }
                })
                ->rawColumns(['actions', 'checkbox', 'related_to', 'description'])
                ->make(true);
        }
        $can_edit = $login_user->can('edit_rule');
        $can_delete = $login_user->can('delete_rule');
        $show_actions = $can_edit || $can_delete;

        return view('content.Rules.index', compact('show_actions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'related_to' => 'required|array|min:1',
            'related_to.*' => 'in:discussions,suggestions,issues',
            'society_owner_id' => 'required|exists:users,id',
        ]);

        try {
            if ($request->id) {
                $rule = Rule::findOrFail($request->id);
                $rule->update([
                    'name' => $request->name,
                    'description' => $request->description,
                    'society_owner_id' => $request->society_owner_id,
                    'related_to' => $request->related_to,
                ]);
                $message = ' Rule Update Successfully. ';
            } else {
                Rule::create([
                    'name' => $request->name,
                    'description' => $request->description,
                    'society_owner_id' => $request->society_owner_id,
                    'related_to' => $request->related_to,
                ]);
                $message = ' Rule Created Successfully. ';
            }

            return redirect()->back()->with('success', $message);

        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $rule = Rule::findorfail($id);
            $rule->delete();

            return back()->with('success', 'Rule deleted successfully. ');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function bulkDelete(Request $request)
    {
        try {
            Rule::whereIn('id', $request->ids)->delete();

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}
