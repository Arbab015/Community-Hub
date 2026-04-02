<?php

namespace App\Http\Controllers;

use App\Models\Society;
use App\Models\SocietyOwner;
use App\Models\User;
use App\Services\FileCompressionService;
use App\Services\FileServices;
use App\Services\PermissionMatrixService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\DataTables;

class UsersController extends Controller
{
    protected $compressor;

    public function __construct(FileServices $compressor)
    {
        $this->compressor = $compressor;
    }
    public function index(Request $request, $slug)
    {
        // dd($slug);
        $login_user = Auth::user();
        if ($request->ajax()) {
            $usersQuery = User::query()->with(['roles.creator']);

            if ($slug === 'society_owners') {
                $usersQuery->whereHas('roles', function ($q) {
                    $q->where('name', 'Society Owner');
                });
            } elseif ($slug === 'society_members') {
                $usersQuery->whereHas('roles', function ($q) {
                    $q->where('name', 'Society Member');
                })->whereHas('memberSocieties', function ($q) {
                    $q->whereIn(
                        'member_societies.society_id',
                        auth()->user()->societies()->select('id')
                    );
                });
            } elseif ($slug === 'system_users') {
                $usersQuery->whereHas('roles.creator.roles', function ($q) {
                    $q->where('name', 'Super Admin');
                })
                    ->whereDoesntHave('roles', function ($q) {
                        $q->where('name', 'society owner');
                    });
            } elseif ($slug === 'society_managers') {
                // logger($usersQuery->whereHas('roles', function ($q) {
                //     $q->where('user_id', auth()->id());
                // }));
                $usersQuery->whereHas('roles', function ($q) {
                    $q->where('user_id', auth()->id());
                })
                    ->whereDoesntHave('roles', function ($q) {
                        $q->whereIn('name', ['Society Member', 'Super Admin']);
                    });
            } else {
                $excluded_roles = ['Super Admin', 'Society Owner'];
                $usersQuery->whereDoesntHave('roles', function ($q) use ($excluded_roles) {
                    $q->whereIn('name', $excluded_roles);
                });
            }

            $usersQuery->orderBy('id', 'desc');
            return DataTables::of($usersQuery)
                ->addColumn('checkbox', function ($user) {
                    return '<input type="checkbox" class="form-check-input checkbox" value="' . $user->id . '" data_type="user">';
                })
                ->addColumn('role', function ($user) {
                    return $user->roles->pluck('name')->implode(', ');
                })
                ->addColumn('name', function ($user) use ($slug) {
                    $url = route('user.edit', [$user->uuid, $slug]);
                    return '
                    <a href="' . $url . '" class="badge bg-label-secondary">
                     ' . $user->first_name . ' ' . $user->last_name . '
                     </a>';
                })
                ->addColumn('actions', function ($user) use ($slug, $login_user) {
                    $edit = "";
                    $delete = "";
                    if ($login_user->can('edit_user')) {
                        $edit = '<a href="' . route('user.edit', [$user->uuid, $slug]) . '" class="me-2">
                              <i class="fa-solid fa-pen-to-square text-primary"></i>
                             </a>';
                    }

                    if ($login_user->can('delete_user')) {
                        $delete = '<form action="' . route('users.destroy', $user->uuid) . '"
                          method="POST" style="display:inline;">
                          ' . csrf_field() . method_field('DELETE') . '
                          <i class="fa-solid fa-trash text-danger"
                             style="cursor:pointer"
                             onclick="confirmDelete(event)">
                          </i>
                       </form>';
                    }
                    return $edit . ' ' . $delete;
                })
                ->rawColumns(['checkbox', 'name', 'actions'])
                ->make(true);
        }

        $can_edit = $login_user->can('edit_user');
        $can_delete = $login_user->can('delete_user');
        $show_actions = $can_edit || $can_delete;

        $roles = Role::where('name', '!=', 'Super Admin')->orderBy('name', 'desc')->get();

        return view('content.users.index', compact(['roles', 'slug', 'show_actions']));
    }



    public function create($slug)
    {
        $roles = null;
        $societies = null;
        if ($slug === 'society_owners') {
            // Only Society Owner role
            $roles = Role::where('name', 'Society Owner')->get();
        } else if ($slug === 'society_members') {
            $societies = Society::where('owner_id', Auth()->id())->get();
        } else if ($slug === 'system_users') {
            $roles = Role::whereNotIn('name', ['Super Admin', 'Society Owner', 'Society Member'])
                ->where('user_id', Auth::id())
                ->orderBy('name', 'desc')
                ->get();
        } else if ($slug === 'society_managers') {
            $roles = Role::whereNotIn('name', ['Super Admin', 'Society Owner'])
                ->orderBy('name', 'desc')
                ->where('user_id', Auth::id())
                ->get();
            logger($roles);
        }
        return view('content.users.create', compact('societies', 'roles', 'slug'));
    }

    public function edit($uuid, $slug = null)
    {
        $request_user = User::where('uuid', $uuid)->first();
        $user = $request_user->load('roles.permissions');
        $roles = Role::with('permissions')
            ->whereNotIn('name', ['Super Admin', 'Society Owner'])
            ->orderBy('name', 'desc')
            ->where('user_id', Auth()->id())
            ->get();
        $assignedRole = $user->roles->first();
        $permissionMatrix = PermissionMatrixService::build();
        $isSelf = Auth::id() === $request_user->id;

        return view(
            'content.users.edit',
            compact('user', 'roles', 'isSelf', 'permissionMatrix', 'assignedRole', 'slug')
        );
    }

    public function storeOrUpdate(Request $request, $slug, $uuid = null)
    {
        // dd($request->all());
        $user = $uuid
            ? User::where('uuid', $uuid)->firstOrFail()
            : new User();
        $section = $request->input('section');
        $allRules = [
            'basic' => [
                'first_name' => 'required|string|max:255|min:2',
                'last_name'  => 'nullable|string|max:255',
                'contact'    => 'required|string|max:20',
                'picture'    => $uuid ? 'nullable|image|max:5120' : 'required|image|max:5120',
            ],
            'security' => [
                'password' => $uuid
                    ? 'nullable|string|min:8|confirmed'
                    : 'required|string|min:8|confirmed',
            ],
            'other' => [
                'dob'               => 'required|date',
                'country'           => 'required|string|max:50',
                'cnic_passport'     => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('users', 'cnic_passport')->ignore($uuid, 'uuid'),
                ],
                'gender'            => 'required|in:male,female,other',
                'marital_status'    => 'required|in:married,un-married',
                'profession'        => 'required|string|max:100',
                'emergency_contact' => 'required|string|max:20',
                'present_address'   => 'required|string|min:15',
                'permanent_address' => 'required|string|min:15',
            ],
            'meta' => [
                'email' => [
                    'required',
                    'email',
                    Rule::unique('users', 'email')->ignore($uuid, 'uuid'),
                ],
            ],
        ];
        $rules = $section
            ? ($allRules[$section] ?? [])
            : collect($allRules)->except('roles')->collapse()->toArray();

        // Validate
        $data = $request->validate($rules);
        try {
            // Password hashing
            if (array_key_exists('password', $data)) {
                if ($data['password']) {
                    $data['password'] = Hash::make($data['password']);
                } else {
                    unset($data['password']);
                }
            }

            // Create or Update user
            if ($uuid) {
                $user->update($data);
                $message = 'User updated successfully';
            } else {
                $user = User::create($data);
                $message = 'User created successfully';
            }

            if (
                $request->filled('role') &&
                ($section === 'roles' || !$uuid)
            ) {
                // dd($request->filled('role'));
                $user->assignRole(Role::find($request->role));
            }

            if ($request->hasFile('picture')) {
                $old = $user->attachment()->where('is_main', true)->first();
                if ($old) {
                    Storage::disk('public')->delete($old->link);
                    $old->delete();
                }
                app(FileServices::class)
                    ->compressAndStore($request->file('picture'), $user, true, true);
            }

            if ($slug === 'society_members') {
                $request->validate([
                    'society' => 'required|exists:societies,id',
                ]);
                $user->memberSocieties()->syncWithoutDetaching([
                    $request->society
                ]);
            }

            if ($uuid) {
                return redirect()->back()->with('success', $message);
            } else {
                return redirect()->route('users.index', compact('slug'))->with('success', $message);
            }
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }


    public function destroy($uuid)
    {
        try {
            $user = User::findOrFail($uuid);
            $old = $user->attachment;
            if ($old) {
                if (Storage::disk('public')->exists($old->link)) {
                    Storage::disk('public')->delete($old->link);
                }
                $old->delete();
            }
            $user->delete();
            return redirect()->route('users.index')->with('success', 'User deleted successfully');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function bulkDelete(Request $request)
    {
        try {
            User::whereIn('id', $request->ids)->delete();
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function downloadTemplate(Request $request)
    {
        $path = public_path('templates/Users-Community_Hub.csv');
        return response()->download($path, 'users_template.csv');
    }


    public function importUsers(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimetypes:text/csv,text/plain',

            ]);
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}
