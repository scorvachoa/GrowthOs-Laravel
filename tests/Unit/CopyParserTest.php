<?php

namespace Tests\Unit;

use App\Services\AI\CopyParser;
use PHPUnit\Framework\TestCase;

class CopyParserTest extends TestCase
{
    private CopyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CopyParser;
    }

    public function test_parse_full_copy(): void
    {
        $raw = "TITULO\nCusco en un minuto\n\nDESCRIPCION\nDescubre la ciudad imperial.\n\nCTA\nVisitanos hoy\nSiguenos\n\nHASHTAGS\n#cusco #peru\n\nTAGS\nCusco, Peru, viaje";

        $result = $this->parser->parse($raw);

        $this->assertEquals('Cusco en un minuto', $result['title']);
        $this->assertStringContainsString('Descubre la ciudad imperial.', $result['description']);
        $this->assertStringContainsString('Visitanos hoy', $result['cta']);
        $this->assertStringContainsString('#cusco', $result['hashtags']);
        $this->assertStringContainsString('Cusco', $result['tags']);
    }

    public function test_parse_empty_text(): void
    {
        $result = $this->parser->parse('');

        $this->assertEquals('', $result['title']);
        $this->assertEquals('', $result['description']);
        $this->assertEquals('', $result['cta']);
        $this->assertEquals('', $result['hashtags']);
        $this->assertEquals('', $result['tags']);
        $this->assertEquals('', $result['raw']);
    }

    public function test_parse_with_hashtags_only(): void
    {
        $raw = "TITULO\nTitulo\n\nDESCRIPCION\nDescripcion\n\nCTA\nCta\n\nHASHTAGS\n#viaje #aventura\n\nTAGS\nTag1, Tag2";

        $result = $this->parser->parse($raw);

        $this->assertEquals('Titulo', $result['title']);
        $this->assertContains('#viaje', explode(' ', $result['hashtags']));
    }

    public function test_parse_with_label_variants(): void
    {
        $raw = "TÍTULO CORTO\nVideo increible\n\nDESCRIPCIÓN OPTIMIZADA\nTexto de descripcion\n\nCTA FINAL\nSuscribete\n\nHASHTAG\n#contenido\n\nTAGS SEO\nvideo, youtube, viral";

        $result = $this->parser->parse($raw);

        $this->assertEquals('Video increible', $result['title']);
        $this->assertEquals('Texto de descripcion', $result['description']);
    }

    public function test_extracts_hashtags_from_text(): void
    {
        $raw = "TITULO\nTitulo\n\nDESCRIPCION\nTexto #genial\n\nCTA\nClick\n\nHASHTAGS\n\nTAGS\nTag";

        $result = $this->parser->parse($raw);

        $this->assertStringContainsString('#genial', $result['hashtags']);
    }

    public function test_parse_numbered_labels(): void
    {
        $raw = "1. TITULO\nTitulo\n\n2. DESCRIPCION\nDescripcion\n\n3. CTA\nSuscribete";

        $result = $this->parser->parse($raw);

        $this->assertEquals('Titulo', $result['title']);
        $this->assertEquals('Descripcion', $result['description']);
    }

    public function test_removes_bold_markdown(): void
    {
        $raw = "TITULO\n**Titulo en negrita**\n\nDESCRIPCION\n**Texto** con **formato**";

        $result = $this->parser->parse($raw);

        $this->assertEquals('Titulo en negrita', $result['title']);
        $this->assertStringNotContainsString('**', $result['description']);
    }
}
