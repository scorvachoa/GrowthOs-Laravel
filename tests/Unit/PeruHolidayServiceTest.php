<?php

namespace Tests\Unit;

use App\Services\PeruHolidayService;
use PHPUnit\Framework\TestCase;

class PeruHolidayServiceTest extends TestCase
{
    private PeruHolidayService $service;

    protected function setUp(): void
    {
        $this->service = new PeruHolidayService;
    }

    public function test_for_year_returns_array(): void
    {
        $holidays = $this->service->forYear(2026);

        $this->assertIsArray($holidays);
        $this->assertNotEmpty($holidays);
    }

    public function test_for_year_contains_fixed_holidays(): void
    {
        $holidays = $this->service->forYear(2026);

        $this->assertArrayHasKey('2026-01-01', $holidays);
        $this->assertEquals('Ano Nuevo', $holidays['2026-01-01']);

        $this->assertArrayHasKey('2026-05-01', $holidays);
        $this->assertEquals('Dia del Trabajo', $holidays['2026-05-01']);

        $this->assertArrayHasKey('2026-07-28', $holidays);
        $this->assertEquals('Fiestas Patrias', $holidays['2026-07-28']);

        $this->assertArrayHasKey('2026-12-25', $holidays);
        $this->assertEquals('Navidad', $holidays['2026-12-25']);
    }

    public function test_for_year_contains_15_holidays(): void
    {
        $holidays = $this->service->forYear(2026);

        $this->assertCount(15, $holidays);
    }

    public function test_for_year_easter_dependent_holidays_exist(): void
    {
        $holidays = $this->service->forYear(2026);

        $easterDependent = array_filter($holidays, fn ($name) => in_array($name, ['Jueves Santo', 'Viernes Santo']));
        $this->assertCount(2, $easterDependent);
    }

    public function test_for_year_all_keys_are_valid_dates(): void
    {
        $holidays = $this->service->forYear(2026);

        foreach (array_keys($holidays) as $date) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
            $this->assertTrue(\DateTime::createFromFormat('Y-m-d', $date) !== false, "Invalid date: {$date}");
        }
    }

    public function test_for_year_all_values_are_strings(): void
    {
        $holidays = $this->service->forYear(2026);

        foreach ($holidays as $name) {
            $this->assertIsString($name);
            $this->assertNotEmpty($name);
        }
    }

    public function test_for_year_different_years_have_different_easter(): void
    {
        $h2025 = $this->service->forYear(2025);
        $h2026 = $this->service->forYear(2026);

        $easter2025 = array_search('Viernes Santo', $h2025);
        $easter2026 = array_search('Viernes Santo', $h2026);

        $this->assertNotFalse($easter2025);
        $this->assertNotFalse($easter2026);
        $this->assertNotEquals($easter2025, $easter2026);
    }

    public function test_for_year_fixed_holidays_same_across_years(): void
    {
        $h2025 = $this->service->forYear(2025);
        $h2026 = $this->service->forYear(2026);

        $this->assertEquals('Ano Nuevo', $h2025['2025-01-01']);
        $this->assertEquals('Ano Nuevo', $h2026['2026-01-01']);
        $this->assertEquals('Navidad', $h2025['2025-12-25']);
        $this->assertEquals('Navidad', $h2026['2026-12-25']);
    }
}
