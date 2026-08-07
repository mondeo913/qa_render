<?php

namespace Tests\Unit;

use App\Services\LoadBoardService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LoadBoardServiceTest extends TestCase
{
    #[DataProvider('statusColumns')]
    public function test_status_is_projected_to_the_expected_kanban_column(
        string $status,
        float $completion,
        string $expected
    ): void {
        $this->assertSame(
            $expected,
            LoadBoardService::columnForStatus($status, $completion)
        );
    }

    public static function statusColumns(): array
    {
        return [
            ['PROGRAMADA', 0, LoadBoardService::COLUMN_TODO],
            ['ABIERTA', 0, LoadBoardService::COLUMN_TODO],
            ['EN_CAPTURA', 20, LoadBoardService::COLUMN_PROGRESS],
            ['OBSERVADA', 45, LoadBoardService::COLUMN_PROGRESS],
            ['ENTREGADA', 60, LoadBoardService::COLUMN_REVIEW],
            ['EN_REVISION_INSTITUCIONAL', 80, LoadBoardService::COLUMN_REVIEW],
            ['VALIDADA', 90, LoadBoardService::COLUMN_DONE],
            ['VALIDADO_Y_CERRADO', 100, LoadBoardService::COLUMN_DONE],
            ['VENCIDA', 0, LoadBoardService::COLUMN_TODO],
            ['VENCIDA', 40, LoadBoardService::COLUMN_PROGRESS],
        ];
    }
}
