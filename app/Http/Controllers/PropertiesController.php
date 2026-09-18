<?php

namespace App\Http\Controllers;

use App\Jobs\ImportProperties;
use App\Models\Attachment;
use App\Models\Block;
use App\Models\Floor;
use App\Models\Import;
use App\Models\InstallmentTemplate;
use App\Models\PaymentTemplate;
use App\Models\Property;
use App\Models\PropertyAttribute;
use App\Models\PropertyUnit;
use App\Models\Room;
use App\Models\Society;
use App\Services\FileServices;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class PropertiesController extends Controller
{
    public function download($type)
    {
        if ($type === 'template') {
            $path = public_path('templates_&_guidelines/Property-template.csv');

            return response()->download($path, 'Property Template.csv');
        }
        if ($type === 'guidelines') {
            $path = public_path('templates_&_guidelines/Import-Properties-Guidelines.txt');

            return response()->download($path, 'Import Properties Guidelines.txt');
        }

    }

    public static function convertForSystem($value, $from)
    {
        return match ($from) {
            'meter' => $value * 3.28084,
            'yard' => $value * 3,
            'feet' => $value,
            'inch' => $value / 12,
        };
    }

    public function convertForUser($value_in_feet, $to_unit)
    {
        return match ($to_unit) {
            'meter' => $value_in_feet / 3.28084,
            'yard' => $value_in_feet / 3,
            'feet' => $value_in_feet,
            'inch' => $value_in_feet * 12,
        };
    }

    public function propertiesFromBlocksShow(Request $request, $uuid, $isPending = null)
    {
        return $this->show($request, $uuid, $isPending);
    }

    public function propertiesFromSocietyShow(Request $request, $user_type, $society_uuid, $uuid)
    {
        return $this->show($request, $uuid, $user_type, $society_uuid);
    }

    public function show(Request $request, $uuid, $user_type = null, $society_uuid = null, $isPending = null)
    {
        $block = Block::where('uuid', $uuid)->first();
        $society = Society::where('uuid', $society_uuid)->first();

        $query = Property::where('block_id', $block->id)->with('dimensions')->latest();
        $query->when($request->category, fn ($q) => $q->where('category', $request->category));
        $query->when($request->type, fn ($q) => $q->where('type', $request->type));
        $query->when($request->search_content, function ($q) use ($request) {
            $search = $request->search_content;
            $q->where(function ($subQ) use ($search) {
                $subQ->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('property_no', 'LIKE', "%{$search}%");
            });
        });

        if ($request->ajax()) {
            return DataTables::of($query)
                ->addColumn('checkbox', function ($property) {
                    return '<input type="checkbox" class="form-check-input property-checkbox" style="width:1.15em;height:1.15em;cursor:pointer;" value="'.$property->uuid.'">';
                })
                ->addColumn('name', function ($property) {
                    return $property->name ? ucfirst($property->name) : ucfirst($property->type);
                })
                ->addColumn('property_no', function ($property) {
                    return '#'.ucfirst($property->property_no);
                })
                ->addColumn('category', function ($property) {
                    return ucfirst($property->category);
                })
                ->addColumn('actions', function ($property) use ($society, $user_type) {
                    $view = '';
                    $delete = '';
                    $setPrice = '';

                    $viewUrl = isset($society) && $user_type
                      ? route('society.property.details', [$user_type, $society->uuid, $property->uuid])
                      : route('property.details', $property->uuid);

                    if (auth()->user()->can('view_property')) {
                        $view = '<a href="'.$viewUrl.'" class="pe-3">
                    <i class="fa-solid fa-eye text-primary" role="button" title="View details"></i>
                </a>';
                    }

                    if (auth()->user()->can('listing_property')) {
                        $listingData = $property->listing ? htmlspecialchars(json_encode($property->listing), ENT_QUOTES) : '';
                        $setPrice = '<span class="pe-3 set-price-btn" role="button" title="Set Price"
                          data-type="property" data-id="'.$property->uuid.'"
                          data-listing="'.$listingData.'"
                          data-bs-toggle="modal" data-bs-target="#sell_rent_modal">
                          <i class="ti tabler-tag text-success"></i>
                        </span>';
                    }

                    if (auth()->user()->can('delete_property')) {
                        $deleteUrl = '';

                        $delete = '<form action="'.$deleteUrl.'" method="POST" style="display:inline;">
                      '.csrf_field().method_field('DELETE').'
                      <i class="fa-solid fa-trash text-danger cursor-pointer me-2" title="Delete"
                         onclick="confirmDelete(event)">
                      </i>
                   </form>';
                    }

                    return $setPrice.' '.$view.' '.$delete;
                })
                ->rawColumns(['checkbox', 'actions'])
                ->make(true);
        }
        $can_delete = auth()->user()->can('delete_block');
        $can_view = auth()->user()->can('view_block');
        $show_actions = $can_delete || $can_view;
        if ($isPending) {
            session()->flash('success', 'Property created successfully.');
        }

        $query_dropdown = Property::query()->where('block_id', $block->id);
        $property_categories = $query_dropdown->distinct('id')->pluck('category');
        $property_types = $query_dropdown->distinct('id')->pluck('type');

        $payment_templates = PaymentTemplate::where('owner_id', auth()->id())->get();
        $installment_templates = InstallmentTemplate::where('owner_id', auth()->id())->get();

        $property_plans_templates = $block->properties
            ->filter(fn ($property) => $property->paymentPlan?->paymentTemplate)
            ->map(fn ($property) => $property->paymentPlan->paymentTemplate)
            ->unique('id');

        return view(
            'content.property_management.blocks.show',
            compact(
                'society',
                'user_type',
                'block',
                'payment_templates',
                'installment_templates',
                'property_categories',
                'property_types',
                'property_plans_templates',
                'show_actions'
            )
        );
    }

    public function ownerProperties(Request $request)
    {
        $login_user = Auth::user();
        $skip = (int) $request->input('skip', 0);
        $take = 4;
        $total_skip = 4 + $skip;

        $unit_types = PropertyAttribute::where('type', 'unit_type')->orderBy('created_at', 'asc')->get();

        $propertyQuery = Property::where('owner_id', $login_user->id)
            ->with('dimensions', 'attachment');

        $propertyQuery->when($request->category, fn ($q) => $q->where('category', $request->category));
        $propertyQuery->when($request->type, fn ($q) => $q->where('type', $request->type));
        $propertyQuery->when($request->search_content, function ($q) use ($request) {
            $search = $request->search_content;
            $q->where(function ($subQ) use ($search) {
                $subQ->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('property_no', 'LIKE', "%{$search}%");
            });
        });

        $unitQuery = PropertyUnit::where('owner_id', $login_user->id)
            ->with('dimensions', 'attachments', 'floor.property');

        $unitQuery->when($request->type, fn ($q) => $q->where('unit_type', $request->type));
        $unitQuery->when($request->category, function ($q) use ($request) {
            $q->whereHas('floor.property', fn ($q2) => $q2->where('category', $request->category));
        });
        $unitQuery->when($request->search_content, function ($q) use ($request) {
            $search = $request->search_content;
            $q->where('unit_name', 'LIKE', "%{$search}%");
        });

        $properties = $propertyQuery->get();
        $units = $unitQuery->get();

        $normalizedProperties = $properties->map(function ($p) {
            return (object) [
                'uuid' => $p->uuid,
                'source_type' => 'property',
                'name' => $p->name,
                'type' => $p->type,
                'category' => $p->category,
                'property_no' => $p->property_no,
                'image_link' => $p->attachment?->link,
                'updated_at' => $p->updated_at,
                'details_url' => route('owner.property.details', $p->uuid),
            ];
        });

        $normalizedUnits = $units->map(function ($u) use ($unit_types) {
            $property = $u->floor?->property;

            return (object) [
                'uuid' => $u->uuid,
                'source_type' => 'unit',
                'name' => $u->unit_name,
                'type' => $unit_types->firstWhere('id', $u->unit_type)?->title ?? '',
                'category' => $property?->category,
                'property_no' => $u->unit_name,
                'image_link' => $u->attachments->first()?->link,
                'updated_at' => $u->updated_at,
                'details_url' => route('unit.details', ['uuid' => $u->uuid, 'isOwner' => true]),
            ];
        });

        $all = $normalizedProperties->concat($normalizedUnits)
            ->sortByDesc('updated_at')
            ->values();

        $total_properties = $all->count();
        $properties_paged = $all->slice($skip, $take)->values();

        $property_categories = $all->pluck('category')->filter()->unique()->values();
        $property_types = $all->pluck('type')->filter()->unique()->values();

        if ($request->ajax()) {
            return response()->json([
                'total_skip' => $total_skip,
                'properties' => $properties_paged,
                'total_properties' => $total_properties,
            ]);
        }

        return view('content.property_management.blocks.properties.owner_properties',
            compact('properties_paged', 'total_skip', 'property_categories', 'property_types', 'total_properties')
        )->with('properties', $properties_paged);
    }

    public function createFromSociety(Request $request, $user_type, $block_uuid, $uuid = null)
    {
        return $this->create($request, $block_uuid, $user_type, $uuid);
    }

    public function createProperty(Request $request, $block_uuid, $uuid = null)
    {
        return $this->create($request, $block_uuid, null, $uuid);
    }

    public function create($request, $block_uuid, $user_type = null, $uuid = null)
    {
        $block = Block::where('uuid', $block_uuid)->first();
        $society = $user_type ? $block->society : null;
        $tab = $request->query('tab');
        $property = $uuid ? $property = Property::where('uuid', $uuid)
            ->with([
                'dimensions',
                'attachment',
                'attachments',
                'floors.dimensions',
                'floors.rooms.dimensions',
                'floors.units.rooms.dimensions',
                'floors.units.owner',
            ])
            ->first() : null;

        $property_attributes = PropertyAttribute::orderBy('created_at', 'asc')->get();

        $floor_types = $property_attributes->where('type', 'floor_type');
        $unit_types = $property_attributes->where('type', 'unit_type');
        $room_types = $property_attributes->where('type', 'room_type');
        $amenities = $property_attributes->where('type', 'amenity');

        return view('content.property_management.blocks.properties.create', compact('block', 'property', 'tab', 'property_attributes', 'society', 'floor_types', 'unit_types', 'room_types', 'amenities', 'user_type'));
    }

    public function storeOrUpdate(Request $request)
    {
        $section = $request->input('section');
        $block = Block::find($request->block_id);
        $society = $block->society;
        $property = $request->property_id ? Property::find($request->property_id) : null;
        $request_type = strtolower($request->input('request_type', 'on_create'));
        if ($section === 'property') {
            $isMultiple = $request->input('property_count_mode') === 'multiple' && $request_type === 'on_create';

            $rules = [];

            if ($request_type === 'property_info' || $request_type === 'on_create') {
                $rules = [
                    'block_id' => 'required|exists:blocks,id',
                    'category' => 'required|string|in:residential,commercial,other',
                    'name' => 'nullable|string|max:100',
                    'type' => 'required|string|max:20',
                    'street' => 'required|string|max:50',
                    'landmark' => 'nullable|string|max:50',
                    'construction_status' => 'nullable|string|in:constructed,in_progress,pending',
                    'is_corner' => 'nullable|boolean',
                    'is_main_boulevard_facing' => 'nullable|boolean',
                    'is_park_facing' => 'nullable|boolean',
                ];

                if ($isMultiple) {
                    $rules['property_no_prefix'] = 'nullable|string|max:20';
                    $rules['property_no_start'] = 'required|integer|min:1';
                    $rules['property_no_end'] = 'required|integer|min:1|gt:property_no_start';
                } else {
                    $rules['property_no'] = [
                        'required', 'string', 'max:50',
                        Rule::unique('properties', 'property_no')
                            ->where(fn ($q) => $q->whereIn('block_id', $society->blocks()->pluck('id')))
                            ->ignore($property?->id),
                    ];
                }
            }

            if ($request_type === 'property_dimension' || $request_type === 'on_create') {
                $rules['dimensions'] = 'required|array|min:2|max:8';
                $rules['dimensions.*.name'] = 'required|string|max:15|distinct';
                $rules['dimensions.*.size'] = ['required', 'regex:/^\d+(\.\d{1,4})?$/', 'min:0', 'max:999999.9999'];
                $rules['dimensions.*.unit'] = 'required|string|in:feet,meter,yard';
            }

            $request->validate($rules);

            // Extra uniqueness check for multiple — none of the generated nos must already exist
            if ($isMultiple) {
                $prefix = trim($request->property_no_prefix ?? '');
                $nos = range((int) $request->property_no_start, (int) $request->property_no_end);
                $generated = array_map(fn ($n) => $prefix.$n, $nos);
                $blockIds = $society->blocks()->pluck('id');

                $taken = Property::whereIn('block_id', $blockIds)
                    ->whereIn('property_no', $generated)
                    ->pluck('property_no')
                    ->toArray();

                if (! empty($taken)) {
                    return back()
                        ->withErrors(['property_no_start' => 'These property nos already exist: '.implode(', ', $taken)])
                        ->withInput();
                }
            }
        }

        if ($section === 'documents') {
            $request->validate([
                'property_id' => 'required|exists:properties,id',
                'main_pic' => 'nullable|image|max:20000',
                'documents.*' => match ($request_type) {
                    'document' => ['file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:20000'],
                    'media' => ['file', 'mimes:jpg,jpeg,png,gif,svg,mp4,mov,avi,webm,mkv', 'max:20000'],
                    default => ['file', 'mimes:jpg,jpeg,png,gif,svg,mp4,mov,avi,webm,mkv,pdf,doc,docx,xls,xlsx', 'max:20000'],
                },
            ]);
        }

        if ($section === 'construction') {
            $isUpdate = $request_type === 'property_const';

            // Floor type uniqueness: on update ignore already-submitted floor IDs
            $floor_type_rule = $isUpdate
              ? [
                  'required', 'string', 'max:50',
                  function ($attribute, $value, $fail) use ($request, $property) {
                      $submitted = collect($request->input('floors', []))
                          ->pluck('floor_type')
                          ->map(fn ($v) => strtolower($v));
                      if ($submitted->duplicates()->isNotEmpty()) {
                          $fail('Floor type must be unique within the same property.');

                          return;
                      }
                      $submitted_ids = collect($request->input('floors', []))
                          ->pluck('id')->filter()->toArray();
                      $exists = Floor::where('property_id', $property->id)
                          ->whereNotIn('id', $submitted_ids)
                          ->whereRaw('LOWER(floor_type) = ?', [strtolower($value)])
                          ->exists();
                      if ($exists) {
                          $fail('Floor type must be unique within the same property.');
                      }
                  },
              ]
              : [
                  'required', 'string', 'max:50',
                  Rule::unique('floors', 'floor_type')
                      ->where(fn ($q) => $q->where('property_id', $property->id)),
        ];

            // Collect all submitted unit IDs so we can ignore them in the unique check
            $submitted_unit_ids = collect($request->input('floors', []))
                ->flatMap(fn ($floor) => collect($floor['units'] ?? [])->pluck('id'))
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->toArray();

            $unit_name_rule = [
                'required', 'string', 'max:50',
                function ($attribute, $value, $fail) use ($request) {
                    $all_unit_names = [];
                    foreach ($request->input('floors', []) as $floor) {
                        foreach ($floor['units'] ?? [] as $unit) {
                            $all_unit_names[] = strtolower($unit['unit_name'] ?? '');
                        }
                    }
                    if (count(array_keys($all_unit_names, strtolower($value))) > 1) {
                        $fail('The unit name must be unique within the same property.');
                    }
                },

                function ($attribute, $value, $fail) use ($property, $submitted_unit_ids) {
                    $floorIds = Floor::where('property_id', $property?->id)->pluck('id');
                    $exists = PropertyUnit::whereIn('floor_id', $floorIds)
                        ->whereNotIn('id', $submitted_unit_ids)
                        ->whereRaw('LOWER(unit_name) = ?', [strtolower($value)])
                        ->exists();
                    if ($exists) {
                        $fail('The unit name has already been taken.');
                    }
                },
            ];

            $request->validate([
                'property_id' => 'required|exists:properties,id',
                'floors' => 'required|array|min:1',
                'floors.*.floor_type' => $floor_type_rule,
                'floors.*.units.*.unit_name' => $unit_name_rule,
                'floors.*.units.*.unit_type' => 'required|string|max:50',

                'floors.*.units.*.amenities' => 'nullable|array',
                'floors.*.units.*.dimensions' => 'required_if:floors.*.units.*.no_rooms,1|array|min:2|max:8',
                'floors.*.units.*.dimensions.*.name' => 'required_if:floors.*.units.*.no_rooms,1|string|max:20',
                'floors.*.units.*.dimensions.*.size' => 'required_if:floors.*.units.*.no_rooms,1|numeric|min:0|max:999999.99',
                'floors.*.units.*.dimensions.*.unit' => 'required_if:floors.*.units.*.no_rooms,1|string|in:inch,feet,meter,yard',
                'floors.*.units.*.rooms.*.room_type' => 'required|string|max:50',
                'floors.*.units.*.rooms.*.dimensions' => 'required|array|min:2|max:8',
                'floors.*.units.*.rooms.*.dimensions.*.name' => 'required|string|max:20',
                'floors.*.units.*.rooms.*.dimensions.*.size' => 'required|numeric|min:0|max:999999.99',
                'floors.*.units.*.rooms.*.dimensions.*.unit' => 'required|string|in:inch,feet,meter,yard',
                'floors.*.units.*.rooms.*.amenities' => 'nullable|array',

                'floors.*.rooms.*.room_type' => 'required|string|max:50',
                'floors.*.rooms.*.dimensions' => 'required|array|min:2|max:8',
                'floors.*.rooms.*.dimensions.*.name' => 'required|string|max:20',
                'floors.*.rooms.*.dimensions.*.size' => 'required|numeric|min:0|max:999999.99',
                'floors.*.rooms.*.dimensions.*.unit' => 'required|string|in:inch,feet,meter,yard',
                'floors.*.rooms.*.amenities' => 'nullable|array',
            ], [], [
                // custom attribute names — replaces the dot-index path with readable labels
                'floors.*.floor_type' => 'floor type',
                'floors.*.units.*.unit_name' => 'unit name',
                'floors.*.units.*.unit_type' => 'unit type',
                'floors.*.units.*.dimensions' => 'unit dimensions',
                'floors.*.units.*.dimensions.*.name' => 'dimension name',
                'floors.*.units.*.dimensions.*.size' => 'dimension size',
                'floors.*.units.*.dimensions.*.unit' => 'dimension unit',
                'floors.*.units.*.rooms.*.room_type' => 'room type',
                'floors.*.units.*.rooms.*.dimensions' => 'room dimensions',
                'floors.*.units.*.rooms.*.dimensions.*.name' => 'dimension name',
                'floors.*.units.*.rooms.*.dimensions.*.size' => 'dimension size',
                'floors.*.units.*.rooms.*.dimensions.*.unit' => 'dimension unit',
                'floors.*.rooms.*.room_type' => 'room type',
                'floors.*.rooms.*.dimensions' => 'room dimensions',
                'floors.*.rooms.*.dimensions.*.name' => 'dimension name',
                'floors.*.rooms.*.dimensions.*.size' => 'dimension size',
                'floors.*.rooms.*.dimensions.*.unit' => 'dimension unit',
            ]);

        }

        // STORE / UPDATE
        try {
            //            dd($request->floors);
            // PROPERTY BASIC
            if ($section === 'property') {
                $isMultiple = $request->input('property_count_mode') === 'multiple' && $request_type === 'on_create';

                if ($property && ($request_type === 'property_info' || $request_type === 'on_create')) {
                    $isNotPending = $property->construction_status !== 'pending';
                    $property->update([
                        'name' => $request->get('name'),
                        'category' => $request->category,
                        'property_no' => $request->property_no,
                        'type' => $request->type,
                        'street' => $request->street,
                        'landmark' => $request->landmark,
                        'construction_status' => $request->construction_status,
                        'is_corner' => (bool) $request->input('is_corner'),
                        'is_main_boulevard_facing' => (bool) $request->input('is_main_boulevard_facing'),
                        'is_park_facing' => (bool) $request->input('is_park_facing'),
                    ]);

                    if ($isNotPending && $request->construction_status === 'pending') {
                        logger($property);
                        $property->update(['isLocked' => true]);
                    } else {
                        $property->update(['isLocked' => false]);
                    }

                } elseif ($request_type === 'on_create' && ! isset($property)) {

                    $baseData = [
                        'block_id' => $block->id,
                        'name' => $request->get('name'),
                        'category' => $request->category,
                        'type' => $request->type,
                        'street' => $request->street,
                        'landmark' => $request->landmark,
                        'construction_status' => $request->construction_status,
                        'is_corner' => (bool) $request->input('is_corner'),
                        'is_main_boulevard_facing' => (bool) $request->input('is_main_boulevard_facing'),
                        'is_park_facing' => (bool) $request->input('is_park_facing'),
                    ];

                    if ($isMultiple) {
                        $prefix = trim($request->property_no_prefix ?? '');
                        $nos = range((int) $request->property_no_start, (int) $request->property_no_end);
                        $firstProperty = null;

                        foreach ($nos as $n) {
                            $p = Property::create(array_merge($baseData, ['property_no' => $prefix.$n]));
                            if (! $firstProperty) {
                                $firstProperty = $p;
                            }
                            $this->syncRoomDimensions($p, $request->dimensions ?? [], false);
                        }

                        $property = $firstProperty;

                    } else {
                        $property = Property::create(array_merge($baseData, [
                            'property_no' => $request->property_no,
                        ]));
                    }
                }

                if ($request_type === 'property_dimension' || ($request_type === 'on_create' && ! $isMultiple)) {
                    $this->syncRoomDimensions($property, $request->dimensions, $property && $request->property_id);
                }

                if ($request_type !== 'on_create') {
                    return redirect()->back()->with('success', 'Property Updated Successfully');
                } else {
                    $user_type = $request->input('user_type');

                    return redirect()->route(
                        $user_type ? 'society.property.create' : 'property.create',
                        $user_type
                          ? [$user_type, $block->uuid, $property->uuid, 'tab' => 'documents']
                          : ['block_uuid' => $block->uuid, 'uuid' => $property->uuid, 'tab' => 'documents']
                    )->with('success', 'Property saved. Now add documents.');
                }
            }

            // DOCUMENTS
            if ($section === 'documents') {

                if ($request->deleted_files) {
                    $ids = json_decode($request->deleted_files, true);
                    Attachment::whereIn('id', $ids)->delete();
                }

                $newAttachmentIds = [];
                $mainPicId = null;

                $userScope = SocietyAccessResolver::resolver(auth()->user());
                $scopedUploadUserId = ($userScope['isSocietyScoped'] ?? false)
                    ? ($userScope['ownerId'] ?? auth()->id())
                    : auth()->id();

                if ($request->hasFile('main_pic')) {
                    $ids = app(FileServices::class)->compressAndStore(
                        $request->file('main_pic'), $property, true, true, $scopedUploadUserId
                    );
                    $mainPicId = $ids[0] ?? null;
                    $newAttachmentIds = array_merge($newAttachmentIds, $ids);
                }

                if ($request->hasFile('documents')) {
                    $ids = app(FileServices::class)->compressAndStore(
                        $request->file('documents'), $property, false, false, $scopedUploadUserId
                    );
                    $newAttachmentIds = array_merge($newAttachmentIds, $ids);
                }

                if ($request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Files uploaded successfully.',
                        'attachment_ids' => $newAttachmentIds,
                        'main_pic_id' => $mainPicId,
                    ]);
                }

                if ($request_type === 'document' || $request_type === 'media') {
                    return back()->with('success', 'Documents updated successfully.');
                }
            }

            // CONSTRUCTION  —  CREATE (on_create)  or  UPDATE (property_const)
            if ($section === 'construction') {
                $isUpdate = $request_type === 'property_const';
                foreach ($request->floors as $floor_data) {
                    //  FLOOR
                    $floorId = ! empty($floor_data['id']) ? (int) $floor_data['id'] : null;
                    if ($isUpdate && $floorId) {
                        $floor = Floor::find($floorId);
                        if ($floor) {
                            $floor->update(['floor_type' => $floor_data['floor_type']]);
                        }
                    } else {
                        $floor = Floor::create([
                            'property_id' => $property->id,
                            'floor_type' => $floor_data['floor_type'],
                        ]);
                    }
                    if (! $floor) {
                        continue;
                    }
                    //  UNITS
                    if (! empty($floor_data['has_units']) && ! empty($floor_data['units'])) {
                        foreach ($floor_data['units'] as $unit_data) {

                            $unitId = ! empty($unit_data['id']) ? (int) $unit_data['id'] : null;
                            if ($isUpdate && $unitId) {
                                $unit = PropertyUnit::find($unitId);
                                if ($unit) {
                                    $unit->update([
                                        'unit_name' => $unit_data['unit_name'],
                                        'unit_type' => $unit_data['unit_type'],
                                    ]);
                                }
                            } else {
                                $unit = PropertyUnit::create([
                                    'floor_id' => $floor->id,
                                    'unit_name' => $unit_data['unit_name'] ?? null,
                                    'unit_type' => $unit_data['unit_type'] ?? null,
                                    'amenities' => isset($unit_data['amenities'])
                                      ? json_encode($unit_data['amenities'])
                                      : json_encode([]),
                                ]);
                            }

                            if (! empty($unit_data['no_rooms']) && ! empty($unit_data['amenities']) && ! empty($unit_data['dimensions'])) {
                                $unit->update([
                                    'amenities' => json_encode($unit_data['amenities']),
                                ]);
                                $this->syncRoomDimensions($unit, $unit_data['dimensions'], false);
                            } elseif (! empty($unit_data['rooms'])) {
                                foreach ($unit_data['rooms'] as $room_data) {
                                    $roomId = ! empty($room_data['id']) ? (int) $room_data['id'] : null;
                                    $roomAttributes = [
                                        'room_type' => $room_data['room_type'],
                                        'amenities' => isset($room_data['amenities'])
                                          ? json_encode($room_data['amenities'])
                                          : json_encode([]),
                                    ];

                                    if ($isUpdate && $roomId) {
                                        $room = Room::find($roomId);
                                        if ($room) {
                                            $room->update($roomAttributes);
                                        }
                                    } else {
                                        $room = Room::create(array_merge($roomAttributes, [
                                            'floor_id' => $floor->id,
                                            'unit_id' => $unit->id,
                                        ]));
                                    }
                                    if (! $room) {
                                        continue;
                                    }
                                    $this->syncRoomDimensions($room, $room_data['dimensions'] ?? [], $isUpdate);
                                }
                            }
                        }
                    }

                    // ROOMS directly on floor
                    if (! empty($floor_data['rooms'])) {
                        foreach ($floor_data['rooms'] as $room_data) {
                            if (empty($room_data['room_type'])) {
                                continue;
                            }

                            $roomId = ! empty($room_data['id']) ? (int) $room_data['id'] : null;
                            $roomAttributes = [
                                'room_type' => $room_data['room_type'],
                                'amenities' => isset($room_data['amenities'])
                                  ? json_encode($room_data['amenities'])
                                  : json_encode([]),
                            ];
                            if ($isUpdate && $roomId) {
                                $room = Room::find($roomId);

                                if ($room) {
                                    $room->update($roomAttributes);
                                }
                            } else {
                                $room = Room::create(array_merge($roomAttributes, [
                                    'floor_id' => $floor->id,
                                    'unit_id' => null,
                                ]));
                            }

                            if (! $room) {
                                continue;
                            }
                            $this->syncRoomDimensions($room, $room_data['dimensions'] ?? [], $isUpdate);
                        }
                    }
                }

                if ($isUpdate) {
                    return redirect()->back()->with('success', 'Construction details updated successfully.');
                }

                return redirect()->route('block.view', $block->uuid)
                    ->with('success', 'Construction details saved and property created successfully.');
            }

        } catch (QueryException $e) {
            if ($e->getCode() == '22003') {
                return back()->withErrors(['size' => 'Size exceeds allowed limit (max: 999999.9999)']);
            }
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function syncRoomDimensions(Model $model, array $dimensions, bool $isUpdate): void
    {
        $submitted_Ids = [];

        foreach ($dimensions as $dim) {
            if (empty($dim['name'])) {
                continue;
            }

            $converted_size = $this->convertForSystem($dim['size'], $dim['unit']);
            $dimId = ! empty($dim['id']) ? (int) $dim['id'] : null;

            if ($isUpdate && $dimId) {
                $existing = $model->dimensions()->find($dimId);
                if ($existing) {
                    $existing->update([
                        'name' => $dim['name'],
                        'size' => $converted_size,
                        'unit' => $dim['unit'],
                    ]);
                    $submitted_Ids[] = $dimId;

                    continue;
                }
            }

            // No id or not found → create new
            $newDim = $model->dimensions()->create([
                'name' => $dim['name'],
                'size' => $converted_size,
                'unit' => $dim['unit'],
            ]);
            $submitted_Ids[] = $newDim->id;
        }

        $model->dimensions()
            ->whereNotIn('id', $submitted_Ids)
            ->delete();
    }

    public function propertyDetails(Request $request, $uuid)
    {
        return $this->details($request, null, null, $uuid, null);
    }

    public function ownerPropertyDetails(Request $request, $uuid)
    {
        return $this->details($request, null, null, $uuid, true);
    }

    public function propertyDetailsFromSociety(Request $request, $user_type, $society_uuid, $uuid)
    {
        return $this->details($request, $user_type, $society_uuid, $uuid, null);
    }

    public function details($request, $user_type, $society_uuid, $uuid, $isOwner = null)
    {
        $property = Property::where('uuid', $uuid)
            ->with('block', 'paymentPlan.paymentTemplate', 'paymentPlan.installmentAttachments.installmentTemplate')
            ->first();
        $society = $property->block->society ?? null;

        $property_attributes = PropertyAttribute::orderBy('created_at', 'asc')->get();
        $floor_types = $property_attributes->where('type', 'floor_type');
        $unit_types = $property_attributes->where('type', 'unit_type');
        $room_types = $property_attributes->where('type', 'room_type');
        $amenities = $property_attributes->where('type', 'amenity');

        $isCommercial = $property->category === 'commercial';
        $paymentsPlans = PaymentTemplate::where('owner_id', Auth()->id())->where('is_commercial', $isCommercial)->get();
        $installmentTemplates = InstallmentTemplate::where('owner_id', Auth()->id())->where('is_commercial', $isCommercial)->get();

        return view(
            'content.property_management.blocks.properties.property_details',
            compact('property', 'floor_types', 'unit_types', 'isOwner', 'paymentsPlans', 'installmentTemplates', 'room_types', 'amenities', 'society', 'user_type')
        );
    }

    public function roomsByFloor(Request $request, $floor_id)
    {
        $take = (int) $request->input('take', 4);
        $skip = $request->input('skip', 0);
        $floor = Floor::findOrFail($floor_id);

        $query = $floor->rooms()
            ->with(['dimensions'])
            ->when($request->input('search'), fn ($q) => $q->whereHas('type', fn ($q2) => $q2->where('title', 'LIKE', '%'.$request->input('search').'%')))
            ->when($request->input('room_type'), fn ($q) => $q->where('room_type', $request->input('room_type')))
            ->latest();

        $total = $query->count();
        $rooms = $query->skip($skip)->take($take)->get();

        return response()->json([
            'rooms' => $rooms,
            'total' => $total,
            'next_skip' => $skip + $take,
            'has_more' => ($skip + $take) < $total,
        ]);
    }

    public function unitsByFloor(Request $request, $floor_id)
    {
        $take = (int) $request->input('take', 4);
        $skip = $request->input('skip', 0);
        $floor = Floor::findOrFail($floor_id);

        $query = $floor->units()
            ->with(['rooms.dimensions', 'dimensions', 'listing', 'owner'])
            ->when($request->input('search'), fn ($q) => $q->where('unit_name', 'LIKE', '%'.$request->input('search').'%'))
            ->when($request->input('unit_type'), fn ($q) => $q->where('unit_type', $request->input('unit_type')))
            ->latest();

        $total = $query->count();
        $units = $query->skip($skip)->take($take)->get();

        return response()->json([
            'units' => $units,
            'total' => $total,
            'next_skip' => $skip + $take,
            'has_more' => ($skip + $take) < $total,
        ]);
    }

    public function destroy($section, $uuid)
    {
        try {
            if (! in_array($section, ['floor', 'unit', 'room'])) {
                return back()->with([
                    'error' => 'Invalid section type.',
                ], 400);
            }

            switch ($section) {
                case 'floor':
                    $model = Floor::where('uuid', $uuid)->first();
                    break;

                case 'unit':
                    $model = PropertyUnit::where('uuid', $uuid)->first();
                    break;

                case 'room':
                    $model = Room::where('uuid', $uuid)->first();
                    break;
            }

            // Check if record exists
            if (! $model) {
                return back()->with([
                    'error' => ucfirst($section).' not found.',
                ], 404);
            }
            $model->delete();

            return back()->with([
                'status' => true,
                'success' => ucfirst($section).' deleted successfully.',
            ]);

        } catch (\Exception $e) {
            return back()->with([
                'error' => $e->getMessage(),
            ], 500);
        }

    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimetypes:text/csv,text/plain',
            'block_id' => 'required|exists:blocks,id',
        ]);

        $block = Block::find($request->block_id);
        $file = $request->file('file');

        $original_filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = $original_filename.time().'.csv';
        $path = $file->storeAs('imports', $fileName, 'public');
        $fullPath = Storage::disk('public')->path($path);

        $import = Import::create([
            'uuid' => Str::uuid(),
            'user_id' => auth()->id(),
            'file_name' => $fileName,
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'percent_completed' => 0,
            'status' => 'pending',
        ]);

        $result = ImportProperties::validate($fullPath, $block->id);
        logger('result', $result);
        if (! $result['success']) {
            Storage::disk('public')->delete($path);
            $import->delete();

            return response()->json(['errors' => $result['errors']]);
        }

        ImportProperties::dispatch($path, $block->id, $import->id);

        return response()->json(['success' => true]);
    }

    public function importProgress($block_uuid)
    {
        $block = Block::where('uuid', $block_uuid)->first();
        if (! $block) {
            return response()->json(['progress' => 0, 'has_errors' => null, 'status' => 'completed']);
        }

        $running = Import::where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'processing'])
            ->latest()
            ->first();

        if ($running) {
            return response()->json([
                'progress' => (int) $running->percent_completed,
                'status' => 'processing',
                'has_errors' => null,
            ]);
        }

        $latest = Import::where('user_id', auth()->id())
            ->where('status', 'completed')
            ->latest()
            ->first();
        logger(['latest' => $latest]);

        $errorPath = ImportProperties::errorFilePath($block->id);
        logger($errorPath);
        $hasErrors = $latest
          && $latest->error_file_path === $errorPath
          && Storage::disk('public')->exists($errorPath);
        logger(['has_errors' => $hasErrors, 'latest' => $latest, 'error_path' => $errorPath]);

        return response()->json([
            'progress' => 100,
            'status' => 'completed',
            'has_errors' => $hasErrors ?? null,
        ]);
    }

    public function findImportErrorsFiles($block_uuid)
    {
        $block = Block::where('uuid', $block_uuid)->first();
        $errorPath = ImportProperties::errorFilePath($block->id);
        $latest = Import::where('user_id', auth()->id())
            ->where('status', 'completed')
            ->latest()
            ->first();
        $files = collect();
        if ($latest && $latest->error_file_path === $errorPath && Storage::disk('public')->exists($errorPath)) {
            $files = collect([$latest]);
        }

        return response()->json([
            'success' => true,
            'import_error_files' => $files,
        ]);
    }

    public function deleteImportErrors($block_uuid)
    {
        $block = Block::where('uuid', $block_uuid)->first();
        $errorPath = ImportProperties::errorFilePath($block->id);
        if (Storage::disk('public')->exists($errorPath)) {
            Storage::disk('public')->delete($errorPath);
        }
        $import = Import::where('user_id', auth()->id())
            ->where('error_file_path', $errorPath)
            ->latest()
            ->delete();

        return response()->json(['success' => true]);
    }

    public function downloadImportErrors($block_uuid)
    {
        $block = Block::where('uuid', $block_uuid)->firstOrFail();
        $errorPath = ImportProperties::errorFilePath($block->id);

        if (Storage::disk('public')->exists($errorPath)) {
            $path = public_path('storage/'.$errorPath);
            logger($path);

            return response()->download($path);
        }

        return response()->json(['success' => false], 404);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'units' => 'nullable|array',
            'units.*' => 'integer|exists:property_units,id',
            'rooms' => 'nullable|array',
            'rooms.*' => 'integer|exists:rooms,id',
        ]);

        $unitIds = $request->input('units', []);
        $roomIds = $request->input('rooms', []);

        if (empty($unitIds) && empty($roomIds)) {
            return response()->json(['success' => false, 'message' => 'Nothing to delete.'], 422);
        }

        $deleted = 0;

        if (! empty($unitIds)) {
            $deleted += PropertyUnit::whereIn('id', $unitIds)->delete();
        }

        if (! empty($roomIds)) {

            $deleted += Room::whereIn('id', $roomIds)
                ->whereNull('unit_id')
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => "{$deleted} item(s) deleted successfully.",
        ]);
    }

    public function bulkDeleteProperties(Request $request)
    {
        try {
            $request->validate([
                'ids'   => 'required|array',
                'ids.*' => 'string|exists:properties,uuid',
            ]);

            $properties = Property::whereIn('uuid', $request->input('ids'))->get();
            $deleted = 0;

            foreach ($properties as $property) {
                $attachmentIds = $property->attachments()->pluck('id')->toArray();
                $property->dimensions()->delete();

                if ($property->listing) {
                    $property->listing->inquiries()->delete();
                    $property->listing->delete();
                }

                foreach ($property->floors as $floor) {
                    foreach ($floor->units as $unit) {
                        $attachmentIds = array_merge($attachmentIds, $unit->attachments()->pluck('id')->toArray());
                        $unit->dimensions()->delete();
                        if ($unit->listing) {
                            $unit->listing->inquiries()->delete();
                            $unit->listing->delete();
                        }
                        foreach ($unit->rooms as $room) {
                            $room->dimensions()->delete();
                            $room->delete();
                        }
                        $unit->delete();
                    }
                    foreach ($floor->rooms as $room) {
                        $room->dimensions()->delete();
                        $room->delete();
                    }
                    $floor->delete();
                }

                if (! empty($attachmentIds)) {
                    app(FileServices::class)->deleteByIds($attachmentIds);
                }

                $property->delete();
                $deleted++;
            }

            return response()->json([
                'success' => true,
                'message' => "{$deleted} property(ies) deleted successfully.",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroyProperty($uuid, $user_type = null)
    {
        try {
            $property = Property::where('uuid', $uuid)->first();
            $block = $property->block;

            $attachmentIds = $property->attachments()->pluck('id')->toArray();

            $property->dimensions()->delete();

            if ($property->listing) {
                $property->listing->inquiries()->delete();
                $property->listing->delete();
            }

            foreach ($property->floors as $floor) {

                foreach ($floor->units as $unit) {
                    $attachmentIds = array_merge($attachmentIds, $unit->attachments()->pluck('id')->toArray());

                    $unit->dimensions()->delete();

                    if ($unit->listing) {
                        $unit->listing->inquiries()->delete();
                        $unit->listing->delete();
                    }

                    foreach ($unit->rooms as $room) {
                        $room->dimensions()->delete();
                        $room->delete();
                    }

                    $unit->delete();
                }

                foreach ($floor->rooms as $room) {
                    $room->dimensions()->delete();
                    $room->delete();
                }

                $floor->delete();
            }

            if (! empty($attachmentIds)) {
                app(FileServices::class)->deleteByIds($attachmentIds);
            }

            $property->delete();

            if ($user_type) {
                return redirect()->route('society.block.view', [$user_type, $block->society->uuid, $block->uuid])
                    ->with('success', 'Property has been deleted successfully.');
            } else {
                return redirect()->route('block.view', $block->uuid)
                    ->with('success', 'Property has been deleted successfully.');
            }

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }
}
