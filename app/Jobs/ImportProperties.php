<?php

namespace App\Jobs;

use App\Http\Controllers\PropertiesController;
use App\Models\Block;
use App\Models\Property;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportProperties implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $validatedRows;

    protected int $blockId;

    public function __construct(array $validatedRows, int $blockId)
    {
        $this->validatedRows = $validatedRows;
        $this->blockId = $blockId;
    }

    public function handle(): void
    {
        $block = Block::find($this->blockId);
        if (! $block) {
            return;
        }

        $total = count($this->validatedRows);
        $processed = 0;

        foreach (array_chunk($this->validatedRows, 10) as $chunk) {
            foreach ($chunk as $data) {
                $dimensions = $data['dimensions'];
                unset($data['dimensions']);

                $property = Property::create($data);

                foreach ($dimensions as $dim) {
                    $property->dimensions()->create([
                        'name' => $dim['name'],
                        'size' => PropertiesController::convertForSystem($dim['size'], $dim['unit']),
                        'unit' => $dim['unit'],
                    ]);
                }
            }
            $processed += count($chunk);
            $block->update([
                'import_properties_progress' => (int) round(($processed / $total) * 100),
            ]);
            logger((int) round(($processed / $total) * 100));
        }

        $block->update(['import_properties_progress' => 100]);
        logger("ImportProperties completed. Created: $total properties.");
    }

    public static function validate(string $path, int $blockId): array
    {
        $block = Block::find($blockId);
        if (! $block) {
            logger('ImportProperties: block not found: '.$blockId);

            return ['success' => false, 'errors' => ['Block not found.']];
        }
        $valid_categories = ['residential', 'commercial', 'other'];
        $type_map = [
            'residential' => ['plot', 'house', 'other'],
            'commercial' => ['plot', 'building', 'plaza', 'other'],
            'other' => ['plot', 'mosque', 'temple', 'hospital', 'park', 'school', 'govt-office', 'other'],
        ];

        $valid_statuses = ['constructed', 'in_progress', 'pending'];
        $valid_units = ['feet', 'meter', 'yard'];

        if (! file_exists($path)) {
            logger('ImportProperties: file not found: '.$path);

            return ['success' => false, 'errors' => ['Uploaded file not found on server.']];
        }

        $handle = fopen($path, 'r');
        if (! $handle) {
            return ['success' => false, 'errors' => ['Could not open the uploaded file.']];
        }
        $header = fgetcsv($handle);
        logger($header);

        if (! $header) {
            fclose($handle);

            return ['success' => false, 'errors' => ['CSV file is empty or has no header row.']];
        }
        $header = array_map('trim', $header);
        // validate CSV file columns
        $requiredColumns = [
            'category',
            'name',
            'property_no',
            'type',
            'construction_status',
            'landmark',
            'street',
            // Only first 2 dimensions are required
            'dimension_1_name', 'dimension_1_size', 'dimension_1_unit',
            'dimension_2_name', 'dimension_2_size', 'dimension_2_unit',
        ];

        // Optional dimension columns (3–8) must exist in header IF they appear at all
        $optionalDimColumns = [];
        for ($i = 3; $i <= 8; $i++) {
            $optionalDimColumns[] = "dimension_{$i}_name";
            $optionalDimColumns[] = "dimension_{$i}_size";
            $optionalDimColumns[] = "dimension_{$i}_unit";
        }

        $missingColumns = array_diff($requiredColumns, $header);
        if (! empty($missingColumns)) {
            fclose($handle);

            return [
                'success' => false,
                'errors' => ['Missing required CSV columns: '.implode(', ', $missingColumns)],
            ];
        }

        // For optional dims: if ANY column of a dimension group is present,
        // ALL three must be present (partial group = error)
        for ($i = 3; $i <= 8; $i++) {
            $namCol = "dimension_{$i}_name";
            $sizeCol = "dimension_{$i}_size";
            $unitCol = "dimension_{$i}_unit";

            $hasName = in_array($namCol, $header);
            $hasSize = in_array($sizeCol, $header);
            $hasUnit = in_array($unitCol, $header);

            $presentCount = (int) $hasName + (int) $hasSize + (int) $hasUnit;

            if ($presentCount > 0 && $presentCount < 3) {
                $missing = [];
                if (! $hasName) {
                    $missing[] = $namCol;
                }
                if (! $hasSize) {
                    $missing[] = $sizeCol;
                }
                if (! $hasUnit) {
                    $missing[] = $unitCol;
                }
                fclose($handle);

                return [
                    'success' => false,
                    'errors' => ["Dimension $i column group is incomplete. Missing: ".implode(', ', $missing)],
                ];
            }
        }

        $rows = [];
        $headerCount = count($header);
        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row)) === 0) {
                continue;
            }
            $row = array_slice(array_pad($row, $headerCount, ''), 0, $headerCount);
            $rows[] = array_combine($header, array_map('trim', $row));
        }
        fclose($handle);

        $total = count($rows);
        if ($total === 0) {
            return ['success' => false, 'errors' => ['CSV file has no data rows.']];
        }

        $societyBlockIds = $block->society->blocks()->pluck('id');
        $existingPropertyNos = Property::whereIn('block_id', $societyBlockIds)
            ->pluck('property_no')
            ->map(fn ($v) => strtolower(trim($v)))
            ->toArray();

        $errors = [];
        $validatedRows = [];
        $seenNosInFile = [];

        foreach ($rows as $index => $row) {
            $lineNo = $index + 1;
            $rowErrors = [];

            $category = strtolower($row['category'] ?? '');
            if (empty($category) || ! in_array($category, $valid_categories)) {
                $rowErrors[] = "  Invalid category '$category'. Must be one of: ".implode(', ', $valid_categories);
            }

            $type = strtolower($row['type'] ?? '');
            if (! empty($category) && in_array($category, $valid_categories)) {
                if (empty($type) || ! in_array($type, $type_map[$category])) {
                    $rowErrors[] = "  Invalid type '$type' for category '$category'. Allowed: ".implode(', ', $type_map[$category]);
                }
            }

            $property_no = $row['property_no'] ?? '';
            if (empty($property_no)) {
                $rowErrors[] = '  Property_no is required.';
            } else {
                $propertyNoLower = strtolower(trim($property_no));

                if (in_array($propertyNoLower, $existingPropertyNos)) {
                    $rowErrors[] = "  Property_no '$property_no' already exists in this society.";
                }

                if (in_array($propertyNoLower, $seenNosInFile)) {
                    $rowErrors[] = "  Property_no '$property_no' is duplicated within the CSV file.";
                }

                if (! in_array($propertyNoLower, $seenNosInFile)) {
                    $seenNosInFile[] = $propertyNoLower;
                }
            }

            $street = $row['street'] ?? '';
            if (empty($street)) {
                $rowErrors[] = '  Street is required.';
            } elseif (strlen($street) > 50) {
                $rowErrors[] = $street.' Street must not exceed 50 characters.';
            }

            $construction_status = strtolower($row['construction_status'] ?? 'pending');
            if (! in_array($construction_status, $valid_statuses)) {
                $rowErrors[] = "  Invalid construction_status '$construction_status'. Must be one of: ".implode(', ', $valid_statuses);
                $construction_status = 'pending';
            }
            if ($type === 'plot' && $construction_status !== 'pending') {
                $construction_status = 'pending';
            }

            $name = ! empty($row['name']) ? $row['name'] : null;
            $landmark = ! empty($row['landmark']) ? $row['landmark'] : null;

            if ($name && strlen($name) > 100) {
                $rowErrors[] = '  Name must not exceed 100 characters.';
            }
            if ($landmark && strlen($landmark) > 50) {
                $rowErrors[] = '  Landmark must not exceed 50 characters.';
            }

            $dimensions = [];
            $dimErrors = [];
            $dimNamesSeen = [];

            for ($i = 1; $i <= 8; $i++) {
                $dim_name = $row["dimension_{$i}_name"] ?? '';
                $dim_size = $row["dimension_{$i}_size"] ?? '';
                $dim_unit = strtolower($row["dimension_{$i}_unit"] ?? '');

                $nameEmpty = ($dim_name === '');
                $sizeEmpty = ($dim_size === '');
                $unitEmpty = ($dim_unit === '');

                if ($nameEmpty && $sizeEmpty && $unitEmpty) {
                    break;
                }

                if ($nameEmpty || $sizeEmpty || $unitEmpty) {
                    $missing = [];
                    if ($nameEmpty) {
                        $missing[] = 'name';
                    }
                    if ($sizeEmpty) {
                        $missing[] = 'size';
                    }
                    if ($unitEmpty) {
                        $missing[] = 'unit';
                    }
                    $dimErrors[] = "  Dimension $i is incomplete — missing: ".implode(', ', $missing).'. All three fields (name, size, unit) are required together.';

                    continue;
                }

                if (! in_array($dim_unit, $valid_units)) {
                    $dimErrors[] = "  Dimension $i has invalid unit '$dim_unit'. Must be one of: ".implode(', ', $valid_units);

                    continue;
                }

                if (! is_numeric($dim_size) || (float) $dim_size < 0 || (float) $dim_size > 999999.9999) {
                    $dimErrors[] = "  Dimension $i size '$dim_size' is invalid. Must be a positive number up to 999999.9999.";

                    continue;
                }

                if (strlen($dim_name) > 20) {
                    $dimErrors[] = "  Dimension $i name '$dim_name' must not exceed 20 characters.";

                    continue;
                }

                $dimNameLower = strtolower($dim_name);
                if (in_array($dimNameLower, $dimNamesSeen)) {
                    $dimErrors[] = "  Dimension name '$dim_name' is duplicated. Dimension names must be unique within a property.";

                    continue;
                }
                $dimNamesSeen[] = $dimNameLower;

                $dimensions[] = [
                    'name' => $dim_name,
                    'size' => (float) $dim_size,
                    'unit' => $dim_unit,
                ];
            }

            foreach ($dimErrors as $de) {
                $rowErrors[] = $de;
            }

            if (empty($dimErrors) && count($dimensions) < 2) {
                $rowErrors[] = '  At least 2 dimensions are required (found '.count($dimensions).').';
            }

            if (! empty($rowErrors)) {
                foreach ($rowErrors as $e) {
                    $errors[] = empty($property_no) ? "Row Number $lineNo:   $e" : "Property NO $property_no:    $e";
                }
            } else {
                $validatedRows[] = [
                    'block_id' => $block->id,
                    'name' => $name,
                    'category' => $category,
                    'property_no' => $property_no,
                    'type' => $type,
                    'street' => $street,
                    'landmark' => $landmark,
                    'construction_status' => $construction_status,
                    'dimensions' => $dimensions,
                ];
            }
        }

        if (! empty($errors)) {
            logger('ImportProperties: validation failed.', $errors);

            return ['success' => false, 'errors' => $errors];
        }

        return ['success' => true, 'rows' => $validatedRows];
    }
}
