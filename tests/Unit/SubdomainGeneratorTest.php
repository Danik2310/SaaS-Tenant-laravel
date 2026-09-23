<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\Tenant;
use App\Tenants\Support\SubdomainGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubdomainGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_slug_from_company_name(): void
    {
        $this->assertSame('acme-corp.sasapp', (new SubdomainGenerator)->generate('Acme Corp'));
    }

    public function test_transliterates_accents(): void
    {
        $this->assertSame('ejemplo-unico.sasapp', (new SubdomainGenerator)->generate('Ejemplo Único'));
    }

    public function test_appends_numeric_suffix_when_domain_is_taken(): void
    {
        Tenant::withoutEvents(fn () => Tenant::create([
            'id' => 'TEN-000001',
            'name' => 'Acme',
            'email' => 'acme@example.com',
            'status' => 'Active',
        ]));

        Domain::create([
            'tenant_id' => 'TEN-000001',
            'domain' => 'acme-corp.sasapp',
            'is_primary' => true,
        ]);

        $this->assertSame('acme-corp-2.sasapp', (new SubdomainGenerator)->generate('Acme Corp'));
    }

    public function test_falls_back_when_company_name_is_not_slugifiable(): void
    {
        $domain = (new SubdomainGenerator)->generate('!!!');

        $this->assertStringStartsWith('workspace-', $domain);
        $this->assertStringEndsWith('.sasapp', $domain);
    }
}
