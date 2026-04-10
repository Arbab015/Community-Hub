<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Models\Block;
use App\Models\Society;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Mockery\Exception;
use Yajra\DataTables\DataTables;

class BlocksController extends Controller
{
    public function index(Request $request)
    {
        $login_user = auth()->user();
        $scope = SocietyAccessResolver::resolver($login_user);
        $societies_ids = $scope['ownedSocietyIds'];
        abort_if(! auth()->user()->can('listing_block'), 403, 'Unauthorized.');
        if ($request->ajax()) {
            return DataTables::of(Block::whereIn('society_id', $societies_ids)->orderBy('id', 'desc'))
                ->addColumn('checkbox', function ($block) {
                    return '<input type="checkbox" class="form-check-input checkbox" title="Select Record" value="'.$block->id.'">';
                })
                ->addColumn('society', function ($block) {
                    return $block->society->name;
                })
                ->addColumn('actions', function ($block) use ($login_user) {
                    $edit = '';
                    $delete = '';
                    $view = '';
                    if ($login_user->can('edit_block')) {
                        $edit = "<i class='fa-solid fa-pen-to-square text-primary edit_block_btn cursor-pointer me-2'
            title='Edit'
            data-id='{$block->id}'
            data-name='".e($block->name)."'
            data-society_id='{$block->society_id}'>
        </i>";
                    }
                    if ($login_user->can('delete_block')) {
                        $delete = '<form action="'.route('blocks.destroy', $block->uuid).'"
                          method="POST" style="display:inline;">
                          '.csrf_field().method_field('DELETE').'
                          <i class="fa-solid fa-trash text-danger cursor-pointer me-2" title="Delete"
                             onclick="confirmDelete(event)">
                          </i>
                       </form>';
                    }
                    if ($login_user->can('view_block')) {
                        $view = '<a href="'.route('blocks.view', $block->uuid).'" class="pe-3">
                        <i class="fa-solid fa-eye text-primary" role="button" title="View details"></i>
                    </a>';
                    }
                    return $view.' '.$edit.'  '.$delete;
                })
                ->rawColumns(['checkbox', 'actions', 'society'])
                ->make(true);
        }
        $can_edit = $login_user->can('edit_block');
        $can_delete = $login_user->can('delete_block');
        $can_view = $login_user->can('view_block');
        $show_actions = $can_edit || $can_delete || $can_view;
        $societies = Society::whereIn('id', $societies_ids)->get();

        return view('content.property management.blocks.index', compact('show_actions', 'societies'));
    }

    public function store(Request $request)
    {
        $login_user = auth()->user();
        $scope = SocietyAccessResolver::resolver($login_user);
        $societies_ids = $scope['ownedSocietyIds'];
        $request->validate([
            'name' => 'required|string|max:255',
            'society_id' => ['required', 'integer', Rule::in($societies_ids)],
        ]);
        try {
            Block::updateOrCreate(
                [
                    'id' => $request->id,
                ],
                [
                    'name' => $request->name,
                    'society_id' => $request->society_id,
                ]
            );

            return back()->with('success', $request->id ? 'Block updated successfully.' : 'Block created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy($uuid)
    {
        try {
            $block = block::where('uuid', $uuid)->first();
            if ($block->properties()->exists()) {
                return back()->with('error', 'Unable to delete this block due to existing property associations.');
            }
            $block->delete();

            return back()->with('success', 'Block deleted successfully. ');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function bulkDelete(Request $request)
    {
        try {
            $blocks = Block::whereIn('id', $request->ids)->get();

            $notDeleted = [];
            $deletedIds = [];

            foreach ($blocks as $block) {
                if ($block->properties()->exists()) {
                    $notDeleted[] = $block->name;
                } else {
                    $deletedIds[] = $block->id;
                }
            }

            if (! empty($deletedIds)) {
                Block::whereIn('id', $deletedIds)->delete();
            }

            return response()->json([
                'success' => true,
                //        'message' => count($deletedIds) . ' blocks deleted successfully.',
                //        'not_deleted' => $notDeleted
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
