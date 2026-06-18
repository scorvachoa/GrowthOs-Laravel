<?php

namespace Tests\Unit;

use App\Support\WorkBlocks;
use PHPUnit\Framework\TestCase;

class WorkBlocksTest extends TestCase
{
    public function test_all_returns_default_blocks(): void
    {
        $blocks = WorkBlocks::all();

        $this->assertCount(4, $blocks);
        $this->assertContains('09:00-11:00', $blocks);
        $this->assertContains('16:00-18:00', $blocks);
    }

    public function test_generate_2h_blocks(): void
    {
        $blocks = WorkBlocks::generate(2);

        $this->assertCount(4, $blocks);
        $this->assertEquals('09:00-11:00', $blocks[0]);
        $this->assertEquals('11:00-13:00', $blocks[1]);
        $this->assertEquals('14:00-16:00', $blocks[2]);
        $this->assertEquals('16:00-18:00', $blocks[3]);
    }

    public function test_generate_1h_blocks(): void
    {
        $blocks = WorkBlocks::generate(1);

        $this->assertCount(8, $blocks);
        $this->assertEquals('09:00-10:00', $blocks[0]);
        $this->assertEquals('12:00-13:00', $blocks[3]);
        $this->assertEquals('14:00-15:00', $blocks[4]);
        $this->assertEquals('17:00-18:00', $blocks[7]);
    }

    public function test_generate_4h_blocks(): void
    {
        $blocks = WorkBlocks::generate(4);

        $this->assertCount(2, $blocks);
        $this->assertEquals('09:00-13:00', $blocks[0]);
        $this->assertEquals('14:00-18:00', $blocks[1]);
    }

    public function test_generate_with_custom_hours(): void
    {
        $blocks = WorkBlocks::generate(2, 10, 17);

        $this->assertCount(2, $blocks);
        $this->assertEquals('10:00-12:00', $blocks[0]);
        $this->assertEquals('14:00-16:00', $blocks[1]);
    }

    public function test_from_settings_uses_block_hours(): void
    {
        $blocks = WorkBlocks::fromSettings(['block_hours' => 1]);

        $this->assertCount(8, $blocks);
    }

    public function test_from_settings_defaults(): void
    {
        $blocks = WorkBlocks::fromSettings([]);

        $this->assertCount(4, $blocks);
    }

    public function test_from_settings_with_custom_hours(): void
    {
        $blocks = WorkBlocks::fromSettings([
            'block_hours' => 2,
            'default_work_start' => '08:00',
            'default_work_end' => '16:00',
        ]);

        $this->assertCount(3, $blocks);
        $this->assertEquals('08:00-10:00', $blocks[0]);
        $this->assertEquals('10:00-12:00', $blocks[1]);
        $this->assertEquals('14:00-16:00', $blocks[2]);
    }

    public function test_parse_hour(): void
    {
        $this->assertEquals(9, WorkBlocks::parseHour('09:00'));
        $this->assertEquals(14, WorkBlocks::parseHour('14:30'));
    }

    public function test_empty_counts(): void
    {
        $blocks = ['09:00-11:00', '11:00-13:00'];
        $counts = WorkBlocks::emptyCounts($blocks);

        $this->assertEquals(['09:00-11:00' => 0, '11:00-13:00' => 0], $counts);
    }

    public function test_empty_counts_defaults_to_all(): void
    {
        $counts = WorkBlocks::emptyCounts();

        $this->assertCount(4, $counts);
        $this->assertEquals(0, $counts['09:00-11:00']);
    }

    public function test_is_valid(): void
    {
        $this->assertTrue(WorkBlocks::isValid('09:00-11:00'));
        $this->assertFalse(WorkBlocks::isValid('invalid-block'));
    }

    public function test_generate_respects_lunch_break(): void
    {
        $blocks = WorkBlocks::generate(2);

        foreach ($blocks as $block) {
            [$start, $end] = explode('-', $block);
            $startH = (int) explode(':', $start)[0];
            $endH = (int) explode(':', $end)[0];

            if ($startH >= 13 && $endH <= 14) {
                $this->fail("Block {$block} falls within lunch break (13-14)");
            }
        }
    }
}
