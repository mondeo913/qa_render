<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    public function test_every_versioned_operation_is_documented_and_only_login_is_anonymous(): void
    {
        $spec = json_decode(file_get_contents(base_path('docs/api/openapi.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('3.0.3', $spec['openapi']);
        $this->assertSame([['bearerAuth' => []]], $spec['security']);
        $documented = []; $ids = [];
        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $key = strtoupper($method).' api/v1'.$path;
                $documented[] = $key;
                $this->assertNotContains($operation['operationId'], $ids);
                $ids[] = $operation['operationId'];
                if ($key === 'POST api/v1/auth/token') $this->assertSame([], $operation['security']);
                else $this->assertNotSame([], $operation['security'] ?? $spec['security']);
                $this->assertArrayHasKey('422', $operation['responses']);
            }
        }
        $implemented = [];
        foreach (Route::getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/v1/')) continue;
            foreach (array_diff($route->methods(), ['HEAD']) as $method) $implemented[] = $method.' '.$route->uri();
        }
        sort($implemented); sort($documented);
        $this->assertSame($implemented, $documented, 'El contrato debe describir cada ruta real, sin operaciones ficticias.');
    }
}
