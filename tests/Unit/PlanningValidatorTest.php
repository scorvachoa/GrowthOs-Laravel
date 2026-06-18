<?php

namespace Tests\Unit;

use App\Services\PlanningValidator;
use PHPUnit\Framework\TestCase;

class PlanningValidatorTest extends TestCase
{
    private PlanningValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PlanningValidator;
    }

    public function test_assert_working_day_passes_for_valid_day(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->assertWorkingDay('2026-06-17', [0, 1, 2, 3, 4, 5, 6]);
    }

    public function test_assert_working_day_passes_for_monday_with_weekdays(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->assertWorkingDay('2026-06-22', [1, 2, 3, 4, 5]);
    }

    public function test_resolve_block_returns_valid_block(): void
    {
        $settings = [
            'block_hours' => 2,
            'default_work_start' => '09:00',
            'default_work_end' => '18:00',
            'lunch_start' => '13:00',
            'lunch_end' => '14:00',
        ];
        $result = $this->validator->resolveBlock($settings, '09:00-11:00');
        $this->assertEquals('09:00-11:00', $result);
    }

    public function test_resolve_block_returns_first_valid_for_invalid_block(): void
    {
        $settings = [
            'block_hours' => 2,
            'default_work_start' => '09:00',
            'default_work_end' => '18:00',
            'lunch_start' => '13:00',
            'lunch_end' => '14:00',
        ];
        $result = $this->validator->resolveBlock($settings, '99:00-XX:00');
        $this->assertEquals('09:00-11:00', $result);
    }
}
