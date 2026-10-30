<?php

namespace App\Jobs;

use App\Http\Controllers\PropertiesController;
use App\Models\Block;
use App\Models\Import;
use App\Models\Property;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ImportProperties implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $csvPath;

    protected int $blockId;

    protected int $importId;

    public function __construct(string $csvPath, int $blockId, int $importId)
    {
        $this->csvPath = $csvPath;
        $this->blockId = $blockId;
        $this->importId = $importId;
    }

    public static function errorFilePath(int $blockId): string
    {
        $block = Block::find($blockId);

        return "imports/errors/{$block->society->name}_{$block->name}_import_errors_.csv";
    }

    public static function deleteFile($uuid)
    {
        $import = Import::where('uuid', $uuid)->first();
        if (! $import) {
            return;
        }
        logger('deleting file');
        if (Storage::disk('public')->exists('imports/'.$import->file_name)) {
            Storage::disk('public')->delete('imports/'.$import->file_name);
        }
        if ($import->error_file_path === null) {
            $import->delete();
        }

    }

    public function handle(): void
    {
        $block = Block::find($this->blockId);
        $import = Import::find($this->importId);

        if (! $block || ! $import) {
            return;
        }

        $import->update(['status' => 'processing']);

        $valid_categories = ['residential', 'commercial', 'other'];
        $type_map = [
            'residential' => ['plot', 'house', 'other'],
            'commercial' => ['plot', 'building', 'plaza', 'other'],
            'other' => ['plot', 'mosque', 'temple', 'hospital', 'park', 'school', 'govt-office', 'other'],
        ];
        $valid_statuses = ['constructed', 'in_progress', 'pending'];
        $valid_units = ['feet', 'meter', 'yard'];
        $valid_booleans = ['0', '1'];

        $fullPath = Storage::disk('public')->path($this->csvPath);
        $handle = fopen($fullPath, 'r');

        $header = self::normalizeHeader(fgetcsv($handle));

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
            $import->update([
                'percent_completed' => 100,
                'status' => 'completed',
            ]);
            $this->deleteFile($import->uuid);

            return;
        }

        $societyBlockIds = $block->society->blocks()->pluck('id');
        $existingPropertyNos = Property::whereIn('block_id', $societyBlockIds)
            ->pluck('property_no')
            ->map(fn ($v) => strtolower(trim($v)))
            ->toArray();

        $errors = [];
        $processed = 0;

        foreach ($rows as $index => $row) {
            $rowNo = $index + 1;
            $rowErrors = [];

            $category = strtolower($row['category'] ?? '');
            $type = strtolower($row['type'] ?? '');
            $construction_status = strtolower($row['construction_status'] ?? 'pending');
            $property_no = trim($row['property_no'] ?? '');
            $street = trim($row['street'] ?? '');
            $name = $row['name'] ?? '' ?: null;
            $landmark = $row['landmark'] ?? '' ?: null;
            $is_corner = trim($row['is_corner'] ?? '');
            $is_main_boulevard_facing = trim($row['is_main_boulevard_facing'] ?? '');
            $is_park_facing = trim($row['is_park_facing'] ?? '');

            // Validate category
            if (! in_array($category, $valid_categories)) {
                $rowErrors[] = "Invalid category '$category'. Must be: ".implode(', ', $valid_categories);
            }

            // Validate type
            if (empty($rowErrors) && (! isset($type_map[$category]) || ! in_array($type, $type_map[$category]))) {
                $rowErrors[] = "Invalid type '$type' for category '$category'. Allowed: ".implode(', ', $type_map[$category] ?? []);
            }

            // Validate property_no
            if (empty($property_no)) {
                $rowErrors[] = 'Property No is required.';
            } elseif (in_array(strtolower($property_no), $existingPropertyNos)) {
                $rowErrors[] = "Property No '$property_no' already exists in this society.";
            }

            // Validate street
            if (empty($street)) {
                $rowErrors[] = 'Street is required.';
            } elseif (strlen($street) > 50) {
                $rowErrors[] = 'Street must not exceed 50 characters.';
            }

            // Validate construction_status
            if (! in_array($construction_status, $valid_statuses)) {
                $rowErrors[] = "Invalid construction_status '$construction_status'. Must be: ".implode(', ', $valid_statuses);
                $construction_status = 'pending';
            }
            if ($type === 'plot') {
                $construction_status = 'pending';
            }

            if ($name && strlen($name) > 100) {
                $rowErrors[] = 'Name must not exceed 100 characters.';
            }
            if ($landmark && strlen($landmark) > 50) {
                $rowErrors[] = 'Landmark must not exceed 50 characters.';
            }

            // Validate is_corner / is_main_boulevard_facing / is_park_facing (nullable booleans)
            if ($is_corner !== '' && ! in_array($is_corner, $valid_booleans, true)) {
                $rowErrors[] = "Invalid is_corner '$is_corner'. Must be 0 or 1.";
            }
            if ($is_main_boulevard_facing !== '' && ! in_array($is_main_boulevard_facing, $valid_booleans, true)) {
                $rowErrors[] = "Invalid is_main_boulevard_facing '$is_main_boulevard_facing'. Must be 0 or 1.";
            }
            if ($is_park_facing !== '' && ! in_array($is_park_facing, $valid_booleans, true)) {
                $rowErrors[] = "Invalid is_park_facing '$is_park_facing'. Must be 0 or 1.";
            }

            // Validate dimensions
            $dimensions = [];
            $dimNamesSeen = [];
            for ($i = 1; $i <= 8; $i++) {
                $dim_name = trim($row["dimension_{$i}_name"] ?? '');
                $dim_size = trim($row["dimension_{$i}_size"] ?? '');
                $dim_unit = strtolower(trim($row["dimension_{$i}_unit"] ?? ''));

                if ($dim_name === '' && $dim_size === '' && $dim_unit === '') {
                    break;
                }

                if ($dim_name === '' || $dim_size === '' || $dim_unit === '') {
                    $rowErrors[] = "Dimension $i is incomplete (name, size and unit are all required).";

                    continue;
                }
                if (! in_array($dim_unit, $valid_units)) {
                    $rowErrors[] = "Dimension $i has invalid unit '$dim_unit'. Must be: ".implode(', ', $valid_units);

                    continue;
                }
                if (! is_numeric($dim_size) || (float) $dim_size < 0 || (float) $dim_size > 999999.9999) {
                    $rowErrors[] = "Dimension $i size '$dim_size' is invalid.";

                    continue;
                }
                if (strlen($dim_name) > 20) {
                    $rowErrors[] = "Dimension $i name must not exceed 20 characters.";

                    continue;
                }
                if (in_array(strtolower($dim_name), $dimNamesSeen)) {
                    $rowErrors[] = "Dimension name '$dim_name' is duplicated.";

                    continue;
                }
                $dimNamesSeen[] = strtolower($dim_name);
                $dimensions[] = ['name' => $dim_name, 'size' => (float) $dim_size, 'unit' => $dim_unit];
            }

            if (count($dimensions) < 2) {
                $rowErrors[] = 'At least 2 dimensions are required (found '.count($dimensions).').';
            }

            // If any errors — skip this row, record error
            if (! empty($rowErrors)) {
                $label = $property_no ?: "Row $rowNo";
                foreach ($rowErrors as $e) {
                    $errors[] = ['property_no' => $label, 'error' => $e];
                }
            } else {
                // Create the property
                try {
                    $property = Property::create([
                        'block_id' => $block->id,
                        'name' => $name,
                        'category' => $category,
                        'property_no' => $property_no,
                        'type' => $type,
                        'street' => $street,
                        'landmark' => $landmark,
                        'construction_status' => $construction_status,
                        'is_corner' => $is_corner !== '' ? (bool) $is_corner : false,
                        'is_main_boulevard_facing' => $is_main_boulevard_facing !== '' ? (bool) $is_main_boulevard_facing : false,
                        'is_park_facing' => $is_park_facing !== '' ? (bool) $is_park_facing : false,
                    ]);

                    foreach ($dimensions as $dim) {
                        $property->dimensions()->create([
                            'name' => $dim['name'],
                            'size' => PropertiesController::convertForSystem($dim['size'], $dim['unit']),
                            'unit' => $dim['unit'],
                        ]);
                    }

                    $existingPropertyNos[] = strtolower($property_no);

                } catch (\Exception $e) {
                    $errors[] = [
                        'property_no' => $property_no,
                        'error' => 'Failed to save: '.$e->getMessage(),
                    ];
                }
            }

            $processed++;
            if ($processed < $total) {
                $import->update([
                    'percent_completed' => (int) round(($processed / $total) * 100),
                ]);
            }
        }

        // Write error file if needed
        if (! empty($errors)) {
            $filePath = Storage::disk('public')->path(self::errorFilePath($this->blockId));
            if (! is_dir(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }

            $fh = fopen($filePath, 'w');
            fputcsv($fh, ['Property No / Row', 'Error']);
            foreach ($errors as $e) {
                fputcsv($fh, [$e['property_no'], $e['error']]);
            }
            fclose($fh);

            $import->update(['error_file_path' => self::errorFilePath($this->blockId)]);
        }

        $import->update(['percent_completed' => 100, 'status' => 'completed']);
        $this->deleteFile($import->uuid);
        logger("Import done. Total: $total, Errors: ".count($errors));
    }

    /**
     * Excel exports often prefix the first header with a UTF-8 BOM and may use
     * different casing / spacing. Normalise so header lookups always match.
     */
    private static function normalizeHeader($header): array
    {
        if (! is_array($header)) {
            return [];
        }

        $header = array_map('trim', $header);
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        return array_map('strtolower', $header);
    }

    public static function validate(string $path, int $blockId): array
    {
        $block = Block::find($blockId);
        if (! $block) {
            logger('ImportProperties: block not found: '.$blockId);

            return ['success' => false, 'errors' => ['Block not found.']];
        }
        if (! file_exists($path)) {
            logger('ImportProperties: file not found: '.$path);

            return ['success' => false, 'errors' => ['Uploaded file not found on server.']];
        }

        $handle = fopen($path, 'r');
        if (! $handle) {
            return ['success' => false, 'errors' => ['Could not open the uploaded file.']];
        }
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return ['success' => false, 'errors' => ['CSV file is empty or has no header row.']];
        }
        $header = self::normalizeHeader($header);
        // Validate CSV file columns
        $requiredColumns = [
            'category',
            'name',
            'property_no',
            'type',
            'is_corner',
            'is_main_boulevard_facing',
            'is_park_facing',
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
        logger($header);

        return ['success' => true];
    }
}
