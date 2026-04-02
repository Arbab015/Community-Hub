<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Models\Block;
use App\Models\Property;
use App\Models\Society;
use App\Services\FileServices;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Mockery\Exception;
use Yajra\DataTables\DataTables;

class PropertiesController extends Controller
{



  public function show($uuid){
      $block = Block::where('uuid', $uuid)->first();

      return view('content.property management.blocks.show', compact('block'));
  }


  public function create(Request $request){
      $block_id= $request->query('block');
      $block = Block::findorfail($block_id);
      return view('content.property management.blocks.properties.create', compact('block'));
  }



  public function store(Request $request)
  {
    $block = Block::findOrFail($request->block_id);
    $society = $block->society;

    $request->validate([
      'block_id'            => 'required|exists:blocks,id',
      'category'            => 'required|string|in:residential,commercial,other',
      'name'  => 'nullable|string|max:100',
      'property_no'         => [
        'required',
        'string',
        'max:50',
        Rule::unique('properties', 'property_no')
          ->where(function ($query) use ($society) {
            $query->whereIn('block_id', $society->blocks()->pluck('id'));
          }),
      ],
      'type'                => 'required|string|max:255',
      'address'             => 'required|string|max:255',
      'is_constructed'      => 'nullable|boolean',
      'dimensions'          => 'required|array|min:1',
      'dimensions.*.name'   => 'required|string|max:255',
      'dimensions.*.size'   => 'required|decimal:0,2|min:0',
      'dimensions.*.unit'   => 'required|string|in:feet,square_feet,meter,yard,marla,kanal',
    ]);

    try {
      $property = Property::create([
        'block_id'       => $block->id,
        'name' => $request->get('name'),
        'category'       => $request->category,
        'property_no'    => $request->property_no,
        'type'           => $request->type,
        'address'        => $request->address,
        'is_constructed' => $request->boolean('is_constructed'),
      ]);

      // store dimensions
      foreach ($request->dimensions as $dimension) {
        $property->dimensions()->create([
          'name' => $dimension['name'],
          'size' => $dimension['size'],
          'unit' => $dimension['unit'],
        ]);
      }

      if ($request->hasFile('main_pic')) {
        logger('main_pic');
        app(FileServices::class)->compressAndStore(
          $request->file('main_pic'),
          $property,
          true,
          true
        );
      }
      if ($request->hasFile('documents')) {
        logger('documents');
        app(FileServices::class)->compressAndStore(
          $request->file('documents'),
          $property,
          false
        );
      }

      return back()->with('success', 'Property created successfully.');
    } catch (\Exception $e) {
      return back()->with('error', $e->getMessage());
    }
  }

}
