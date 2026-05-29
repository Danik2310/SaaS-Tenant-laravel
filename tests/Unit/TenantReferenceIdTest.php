<?php

namespace Tests\Unit;

use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TenantReferenceIdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_reference_id_is_auto_generated_on_create()
    {
        $tenant = Tenant::create([
            'id' => 'test-ref-'.uniqid(),
            'name' => 'Reference Test',
            'email' => 'ref@test.com',
        ]);

        $this->assertNotNull($tenant->reference_id);
        $this->assertStringStartsWith('TEN-', $tenant->reference_id);
    }

    public function test_reference_id_matches_expected_format()
    {
        $tenant = Tenant::create([
            'id' => 'test-format-'.uniqid(),
            'name' => 'Format Test',
        ]);

        $this->assertMatchesRegularExpression(
            '/^TEN-\d{8}-\d{4}$/',
            $tenant->reference_id
        );
    }

    public function test_custom_reference_id_is_not_overwritten()
    {
        $customRef = 'CUSTOM-REF-001';
        $tenant = Tenant::create([
            'id' => 'test-custom-'.uniqid(),
            'name' => 'Custom Ref Test',
            'reference_id' => $customRef,
        ]);

        $this->assertEquals($customRef, $tenant->reference_id);
    }

    public function test_generated_reference_ids_are_unique()
    {
        $ids = [];
        $count = 5;

        for ($i = 0; $i < $count; $i++) {
            $tenant = Tenant::create([
                'id' => 'test-unique-'.$i.'-'.uniqid(),
                'name' => 'Unique Test '.$i,
            ]);
            $ids[] = $tenant->reference_id;
        }

        $this->assertCount($count, array_unique($ids));
    }

    public function test_generate_reference_id_returns_string()
    {
        $refId = Tenant::generateReferenceId();
        $this->assertIsString($refId);
        $this->assertMatchesRegularExpression('/^TEN-\d{8}-\d{4}$/', $refId);
    }

    public function test_reference_id_increments_counter()
    {
        $ref1 = Tenant::generateReferenceId();
        $ref2 = Tenant::generateReferenceId();

        $seq1 = (int) substr($ref1, -4);
        $seq2 = (int) substr($ref2, -4);

        $this->assertEquals($seq1 + 1, $seq2);
    }

    public function test_tenant_resource_exposes_reference_id()
    {
        $tenant = Tenant::create([
            'id' => 'test-resource-'.uniqid(),
            'name' => 'Resource Test',
        ]);

        $resource = new TenantResource($tenant);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('reference_id', $data);
        $this->assertEquals($tenant->reference_id, $data['reference_id']);
    }
}
