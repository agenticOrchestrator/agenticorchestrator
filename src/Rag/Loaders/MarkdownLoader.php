<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag\Loaders;

use AgenticOrchestrator\Rag\Contracts\DocumentLoaderInterface;
use AgenticOrchestrator\Rag\Document;

/**
 * MarkdownLoader - Loads Markdown files with structure preservation.
 *
 * Preserves heading structure and can optionally extract sections.
 */
class MarkdownLoader implements DocumentLoaderInterface
{
    /**
     * Supported file extensions.
     *
     * @var array<string>
     */
    protected array $extensions = ['md', 'markdown', 'mkd', 'mdx'];

    /**
     * Whether to preserve frontmatter.
     */
    protected bool $preserveFrontmatter = true;

    /**
     * Whether to split by headings.
     */
    protected bool $splitByHeadings = false;

    /**
     * Minimum heading level for splitting.
     */
    protected int $splitLevel = 2;

    /**
     * Create a new markdown loader.
     */
    public function __construct(
        bool $preserveFrontmatter = true,
        bool $splitByHeadings = false,
    ) {
        $this->preserveFrontmatter = $preserveFrontmatter;
        $this->splitByHeadings = $splitByHeadings;
    }

    /**
     * Load documents from a Markdown file.
     *
     * @param  string  $source  The file path
     * @return array<Document>
     */
    public function load(string $source): array
    {
        if (! file_exists($source)) {
            throw new \InvalidArgumentException("File not found: {$source}");
        }

        $content = file_get_contents($source);

        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$source}");
        }

        $metadata = [
            'loader' => 'markdown',
            'size_bytes' => filesize($source),
            'modified_at' => date('c', filemtime($source)),
        ];

        // Extract frontmatter if present
        $frontmatter = [];
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
            $frontmatter = $this->parseFrontmatter($matches[1]);
            $metadata['frontmatter'] = $frontmatter;

            if (! $this->preserveFrontmatter) {
                $content = substr($content, strlen($matches[0]));
            }
        }

        // Extract title from first heading if not in frontmatter
        if (! isset($frontmatter['title'])) {
            if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
                $metadata['title'] = trim($matches[1]);
            }
        } else {
            $metadata['title'] = $frontmatter['title'];
        }

        if ($this->splitByHeadings) {
            return $this->splitByHeadingLevel($source, $content, $metadata);
        }

        return [
            Document::fromFile($source, $content, $metadata),
        ];
    }

    /**
     * Check if the loader supports a given source.
     */
    public function supports(string $source): bool
    {
        if (! file_exists($source) || ! is_file($source)) {
            return false;
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));

        return in_array($extension, $this->extensions, true);
    }

    /**
     * Enable splitting by headings.
     */
    public function splitByHeadings(int $level = 2): static
    {
        $this->splitByHeadings = true;
        $this->splitLevel = $level;

        return $this;
    }

    /**
     * Parse YAML frontmatter.
     *
     * @return array<string, mixed>
     */
    protected function parseFrontmatter(string $yaml): array
    {
        // Simple YAML parsing for common frontmatter fields
        $data = [];
        $lines = explode("\n", $yaml);

        foreach ($lines as $line) {
            if (preg_match('/^(\w+):\s*(.*)$/', trim($line), $matches)) {
                $key = $matches[1];
                $value = trim($matches[2], '"\'');
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Split content by heading level.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<Document>
     */
    protected function splitByHeadingLevel(string $source, string $content, array $metadata): array
    {
        $pattern = '/^(#{1,'.$this->splitLevel.'})\s+(.+)$/m';
        $sections = preg_split($pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($sections === false || count($sections) <= 1) {
            return [Document::fromFile($source, $content, $metadata)];
        }

        $documents = [];
        $currentHeading = null;
        $currentLevel = 0;
        $sectionIndex = 0;

        // Handle content before first heading
        $preamble = trim(array_shift($sections));
        if ($preamble !== '') {
            $documents[] = Document::fromFile(
                $source,
                $preamble,
                array_merge($metadata, [
                    'section' => 'preamble',
                    'section_index' => $sectionIndex++,
                ])
            )->withId(Document::generateId($source.'_preamble'));
        }

        // Process heading/content pairs
        while (count($sections) >= 2) {
            $hashes = array_shift($sections);
            $heading = array_shift($sections);
            $sectionContent = array_shift($sections) ?? '';

            $level = strlen($hashes);
            $fullSection = "{$hashes} {$heading}\n{$sectionContent}";

            $documents[] = Document::fromFile(
                $source,
                trim($fullSection),
                array_merge($metadata, [
                    'section' => trim($heading),
                    'section_level' => $level,
                    'section_index' => $sectionIndex++,
                ])
            )->withId(Document::generateId($source.'_section_'.$sectionIndex));
        }

        return $documents;
    }
}
