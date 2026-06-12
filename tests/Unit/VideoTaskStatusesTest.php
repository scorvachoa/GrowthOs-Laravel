<?php

namespace Tests\Unit;

use App\Enums\VideoTaskStatus;
use PHPUnit\Framework\TestCase;

class VideoTaskStatusesTest extends TestCase
{
    public function test_values_returns_seven_statuses(): void
    {
        $this->assertCount(7, VideoTaskStatus::values());
    }

    public function test_values_contains_expected_statuses(): void
    {
        $expected = ['pending', 'script_ready', 'editing', 'review', 'scheduled', 'published', 'cancelled'];
        foreach ($expected as $status) {
            $this->assertContains($status, VideoTaskStatus::values());
        }
    }

    public function test_labels_returns_all_statuses_with_labels(): void
    {
        $labels = VideoTaskStatus::labels();

        $this->assertCount(7, $labels);
        $this->assertEquals('Pendiente', $labels['pending']);
        $this->assertEquals('Publicado', $labels['published']);
        $this->assertEquals('Cancelado', $labels['cancelled']);
    }

    public function test_options_returns_structured_array(): void
    {
        $options = VideoTaskStatus::options();

        $this->assertCount(7, $options);
        $this->assertArrayHasKey('value', $options[0]);
        $this->assertArrayHasKey('label', $options[0]);

        $pending = $options[0];
        $this->assertEquals('pending', $pending['value']);
        $this->assertEquals('Pendiente', $pending['label']);
    }

    public function test_is_valid_returns_true_for_valid(): void
    {
        $this->assertTrue(VideoTaskStatus::isValid('published'));
        $this->assertTrue(VideoTaskStatus::isValid('pending'));
    }

    public function test_is_valid_returns_false_for_invalid(): void
    {
        $this->assertFalse(VideoTaskStatus::isValid('invalid_status'));
        $this->assertFalse(VideoTaskStatus::isValid(''));
    }

    public function test_values_and_labels_have_same_keys(): void
    {
        $this->assertEquals(VideoTaskStatus::values(), array_keys(VideoTaskStatus::labels()));
    }
}
