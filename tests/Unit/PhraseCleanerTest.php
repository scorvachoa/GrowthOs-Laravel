<?php

namespace Tests\Unit;

use App\Services\AI\PhraseCleaner;
use PHPUnit\Framework\TestCase;

class PhraseCleanerTest extends TestCase
{
    private PhraseCleaner $cleaner;

    protected function setUp(): void
    {
        $this->cleaner = new PhraseCleaner;
    }

    public function test_clean_removes_introductory_phrases(): void
    {
        $input = "Claro, aqui tienes las frases:\nFrase uno\nTe presento las opciones\nFrase dos";

        $result = $this->cleaner->clean($input);

        $this->assertStringNotContainsString('Claro', $result);
        $this->assertStringNotContainsString('aqui tienes', mb_strtolower($result));
        $this->assertStringNotContainsString('te presento', mb_strtolower($result));
    }

    public function test_clean_uppercases_and_formats(): void
    {
        $input = "Descubre Cusco\nVisita Machu Picchu";

        $result = $this->cleaner->clean($input);

        $this->assertEquals("DESCUBRE CUSCO\nVISITA MACHU PICCHU", $result);
    }

    public function test_clean_removes_duplicates(): void
    {
        $input = "Frase unica\nFrase unica\nOtra frase";

        $result = $this->cleaner->clean($input);

        $this->assertCount(2, explode("\n", $result));
    }

    public function test_clean_removes_emoji(): void
    {
        $input = "Descubre Cusco 🎯\nVisita Peru ✅";

        $result = $this->cleaner->clean($input);

        $this->assertStringNotContainsString('🎯', $result);
        $this->assertStringNotContainsString('✅', $result);
    }

    public function test_clean_skips_lines_with_skip_words(): void
    {
        $input = "Usar camara estable\nTransicion suave\nFrase valida";

        $result = $this->cleaner->clean($input);

        $this->assertStringNotContainsString('camara', mb_strtolower($result));
        $this->assertStringNotContainsString('transicion', mb_strtolower($result));
        $this->assertStringContainsString('FRASE VALIDA', $result);
    }

    public function test_clean_removes_markdown_bold(): void
    {
        $input = "**Frase importante**\nOtra **frase**";

        $result = $this->cleaner->clean($input);

        $this->assertStringNotContainsString('**', $result);
    }

    public function test_clean_removes_numbering(): void
    {
        $input = "1. Primera frase\n2. Segunda frase\n3. Tercera frase";

        $result = $this->cleaner->clean($input);

        foreach (explode("\n", $result) as $line) {
            $this->assertStringNotStartsNotWith('1.', $line);
            $this->assertStringNotStartsNotWith('2.', $line);
        }
    }

    public function test_clean_removes_lines_exceeding_90_chars(): void
    {
        $input = "Corta\n" . str_repeat('a', 100);

        $result = $this->cleaner->clean($input);

        $this->assertEquals('CORTA', $result);
    }

    public function test_clean_returns_empty_for_all_invalid(): void
    {
        $input = "Claro\nzoom\nmusica\n\n";

        $result = $this->cleaner->clean($input);

        $this->assertEquals('', $result);
    }

    public function test_clean_removes_parenthetical_notes(): void
    {
        $input = "Frase (nota de edicion)\nOtra frase (importante)";

        $result = $this->cleaner->clean($input);

        $this->assertStringNotContainsString('(', $result);
    }
}
