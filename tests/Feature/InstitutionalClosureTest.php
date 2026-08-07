<?php
namespace Tests\Feature;
use App\Models\InstitutionalReview;
use App\Models\ScheduledLoad;
use App\Models\User;
use App\Services\InstitutionalClosureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InstitutionalClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_close_without_both_checks(): void
    {
        $load = ScheduledLoad::factory()->create(['status'=>'EN_REVISION_INSTITUCIONAL']);
        $user = User::query()->firstOrFail();
        InstitutionalReview::query()->create([
            'scheduled_load_id'=>$load->id,
            'institutional_link_id'=>$user->id,
            'evidences_correct'=>true,
            'package_prepared_for_signature'=>false,
        ]);

        $this->expectException(RuntimeException::class);
        app(InstitutionalClosureService::class)->close($load,$user->id,'Prueba');
    }
}
