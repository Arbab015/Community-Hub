<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Property;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PropertyImportTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private Block $block;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'first_name' => 'Import',
            'last_name' => 'Tester',
            'email' => 'import.'.uniqid().'@example.com',
            'password' => 'password',
            'cnic_passport' => 'CNIC-'.uniqid(),
            'gender' => 'male',
            'marital_status' => 'married',
            'permanent_address' => 'Permanent Address',
            'present_address' => 'Present Address',
            'contact' => '0300'.random_int(1000000, 9999999),
            'is_filer' => false,
        ]);

        $this->user->givePermissionTo(Permission::findOrCreate('create_property'));

        $society = Society::create([
            'name' => 'Import Society',
            'address' => 'Address',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'owner_id' => $this->user->id,
        ]);

        $this->block = Block::create([
            'society_id' => $society->id,
            'name' => 'Block A',
        ]);
    }

    public function test_a_valid_csv_imports_properties(): void
    {
        $this->postCsv($this->header().$this->row('P-9001'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('properties', [
            'block_id' => $this->block->id,
            'property_no' => 'P-9001',
            'name' => 'Demo House',
        ]);
    }

    public function test_a_utf8_bom_in_the_header_does_not_break_the_import(): void
    {
        $this->postCsv("\xEF\xBB\xBF".$this->header().$this->row('P-9002'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(1, Property::where('property_no', 'P-9002')->count());
    }

    public function test_rows_with_omitted_trailing_columns_are_imported(): void
    {
        $header = $this->header('dimension_3_name,dimension_3_size,dimension_3_unit');

        $this->postCsv($header.$this->row('P-9003'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(1, Property::where('property_no', 'P-9003')->count());
    }

    private function header(string $extra = ''): string
    {
        $header = 'category, name, property_no, type,is_corner,is_main_boulevard_facing,is_park_facing,construction_status,landmark, street,dimension_1_name, dimension_1_size,dimension_1_unit,dimension_2_name, dimension_2_size,dimension_2_unit';

        return $extra === '' ? $header."\n" : $header.','.$extra."\n";
    }

    private function row(string $propertyNo): string
    {
        return "residential,Demo House,{$propertyNo},house,0,1,0,constructed,Landmark,Street 1,Width,30,feet,Length,50,feet\n";
    }

    private function postCsv(string $content)
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        file_put_contents($path, $content);
        $file = new UploadedFile($path, 'properties.csv', 'text/csv', null, true);

        return $this->actingAs($this->user)->post(route('properties.import'), [
            'block_id' => $this->block->id,
            'file' => $file,
        ]);
    }
}
