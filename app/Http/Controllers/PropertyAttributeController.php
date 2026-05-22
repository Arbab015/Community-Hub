<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Models\PropertyAttribute;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Mockery\Exception;
use Yajra\DataTables\DataTables;

class PropertyAttributeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $login_user = auth()->user();
        $scope = SocietyAccessResolver::resolver($login_user);
        $owner_id = $scope['ownerId'];
        if ($request->ajax()) {
            return DataTables::of(PropertyAttribute::where('owner_id', $owner_id)->orderBy('id', 'desc'))
                ->addColumn('checkbox', function ($attribute) {
                    return '<input type="checkbox" class="form-check-input checkbox" title="Select Record" value="'.$attribute->id.'">';
                })
                ->addColumn('type', function ($attribute) {
                    return ucwords(str_replace('_', ' ', $attribute->type));
                })
                ->addColumn('actions', function ($attribute) use ($login_user) {
                    $edit = '';
                    $delete = '';
                    if ($login_user->can('edit_attribute')) {
                        $edit = "<i class='fa-solid fa-pen-to-square text-primary edit_attribute_btn cursor-pointer me-2'
                             title='Edit'
                             data-id='{$attribute->id}'
                             data-type='".e($attribute->type)."'
    data-title='".e($attribute->title)."'>
                         </i>";
                    }
                    if ($login_user->can('delete_attribute')) {
                        $delete = '<form action="'.route('attributes.destroy').'"
                          method="POST" style="display:inline;">
                          '.csrf_field().method_field('DELETE').'
                          <input type="hidden" name="id" value="'.$attribute->id.'">
                          <i class="fa-solid fa-trash text-danger cursor-pointer me-2" title="Delete"
                             onclick="confirmDelete(event)">
                          </i>
                       </form>';
                    }

                    return $edit.'  '.$delete;
                })
                ->rawColumns(['checkbox', 'actions'])
                ->make(true);
        }
        $can_edit = $login_user->can('edit_attribute');
        $can_delete = $login_user->can('delete_attribute');
        $show_actions = $can_edit || $can_delete;

        return view('content.property_management.attributes.index', compact('login_user', 'owner_id', 'show_actions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //        dd($request->all());
        $login_user = auth()->user();
        $scope = SocietyAccessResolver::resolver($login_user);
        $request->validate([
            'type' => 'required|string|in:floor_type,unit_type,room_type,amenity',
            'title' => 'required|string|max:255',
            'owner_id' => ['required', 'integer', 'in:'.$scope['ownerId']],
        ]);
        try {
            PropertyAttribute::updateOrCreate(
                [
                    'id' => $request->id,
                ],
                [
                    'owner_id' => $request->owner_id,
                    'type' => $request->type,
                    'title' => $request->title,
                ]
            );

            return back()->with('success', $request->id ? 'Attribute updated successfully.' : 'Attribute created successfully.');
        } catch (QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                return back()->withInput()->with('error', 'Attribute already exists with this type.');
            }
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(PropertyAttribute $propertyAttribute)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PropertyAttribute $propertyAttribute)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PropertyAttribute $propertyAttribute)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $login_user = auth()->user();
        $scope = SocietyAccessResolver::resolver($login_user);
        try {
            $attribute = PropertyAttribute::where('id', $request->id)
                ->where('owner_id', $scope['ownerId'])
                ->firstOrFail();
            $attribute->delete();

            return back()->with('success', 'Attribute deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Unable to delete this record.');
        }
    }

    public function bulkDelete(Request $request)
    {
        $login_user = auth()->user();
        $scope = SocietyAccessResolver::resolver($login_user);
        try {
            PropertyAttribute::whereIn('id', $request->ids)
                ->where('owner_id', $scope['ownerId'])
                ->delete();
            //            $notDeleted = [];
            //            $deletedIds = [];
            //
            //            //            foreach ($attributes as $attribute) {
            //            //                if ($attribute->properties()->exists()) {
            //            //                    $notDeleted[] = $attribute->title;
            //            //                } else {
            //            //                    $deletedIds[] = $attribute->id;
            //            //                }
            //            //            }
            //
            //            if (! empty($deletedIds)) {
            //                Block::whereIn('id', $deletedIds)->delete();
            //            }

            return response()->json([
                'success' => true,
                'message' => 'Selected Atrributes are deleted successfully.',

            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
