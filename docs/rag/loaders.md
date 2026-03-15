# Document Loaders

Document loaders read content from various sources and convert them into `Document` objects for processing by the RAG pipeline.

## Available Loaders

| Loader | Extensions | Description |
|--------|------------|-------------|
| `TextLoader` | .txt, .text, .log | Plain text files |
| `MarkdownLoader` | .md, .markdown, .mkd, .mdx | Markdown with structure preservation |
| `JsonLoader` | .json, .jsonl, .ndjson | JSON arrays and JSONL files |
| `DirectoryLoader` | (directories) | Recursive directory loading |

## TextLoader

Loads plain text files with optional encoding conversion.

### Basic Usage

```php
use AgenticOrchestrator\Rag\Loaders\TextLoader;

$loader = new TextLoader();
$documents = $loader->load('/path/to/file.txt');

// Returns array with single Document
$doc = $documents[0];
echo $doc->content;
echo $doc->getMeta('loader');      // 'text'
echo $doc->getMeta('size_bytes');
echo $doc->getMeta('modified_at');
```

### With Encoding

```php
$loader = new TextLoader(encoding: 'ISO-8859-1');
$documents = $loader->load('/path/to/latin1-file.txt');
// Content is automatically converted to UTF-8
```

### Adding Extensions

```php
$loader = (new TextLoader())->addExtensions(['cfg', 'ini', 'conf']);
```

### Check Support

```php
if ($loader->supports('/path/to/file.txt')) {
    $documents = $loader->load('/path/to/file.txt');
}
```

## MarkdownLoader

Loads Markdown files with structure preservation, frontmatter extraction, and optional section splitting.

### Basic Usage

```php
use AgenticOrchestrator\Rag\Loaders\MarkdownLoader;

$loader = new MarkdownLoader();
$documents = $loader->load('/path/to/document.md');

$doc = $documents[0];
echo $doc->getMeta('title');       // Extracted from first heading
echo $doc->getMeta('frontmatter'); // Parsed YAML frontmatter
```

### Frontmatter Extraction

Given a Markdown file:

```markdown
---
title: User Guide
author: Documentation Team
version: 2.0
---

# Getting Started

Content here...
```

```php
$loader = new MarkdownLoader(preserveFrontmatter: true);
$documents = $loader->load('/path/to/doc.md');

$doc = $documents[0];
$frontmatter = $doc->getMeta('frontmatter');
// ['title' => 'User Guide', 'author' => 'Documentation Team', 'version' => '2.0']

echo $doc->getMeta('title'); // 'User Guide'
```

### Split by Headings

Split large documents into sections based on heading level:

```php
$loader = (new MarkdownLoader())->splitByHeadings(level: 2);
$documents = $loader->load('/path/to/large-doc.md');

// Each h2 section becomes a separate document
foreach ($documents as $doc) {
    echo $doc->getMeta('section');       // Section heading text
    echo $doc->getMeta('section_level'); // Heading level (2)
    echo $doc->getMeta('section_index'); // Section number
}
```

### Configuration Options

```php
$loader = new MarkdownLoader(
    preserveFrontmatter: true,  // Keep frontmatter in content
    splitByHeadings: false,     // Don't split by default
);
```

## JsonLoader

Loads JSON arrays and JSONL (newline-delimited JSON) files.

### Basic Usage

```php
use AgenticOrchestrator\Rag\Loaders\JsonLoader;

$loader = new JsonLoader();

// Load JSON array
$documents = $loader->load('/path/to/data.json');

// Load JSONL
$documents = $loader->load('/path/to/data.jsonl');
```

### JSON Array Format

```json
[
    {"id": "1", "content": "First document content", "category": "faq"},
    {"id": "2", "content": "Second document content", "category": "guide"}
]
```

```php
$documents = $loader->load('/path/to/data.json');

foreach ($documents as $doc) {
    echo $doc->id;                   // "1", "2"
    echo $doc->content;              // Document content
    echo $doc->getMeta('category');  // "faq", "guide"
}
```

### JSONL Format

```jsonl
{"id": "1", "content": "Line one"}
{"id": "2", "content": "Line two"}
```

### Custom Field Names

```php
// Default: content field is "content", id field is "id"
$loader = new JsonLoader(contentField: 'content', idField: 'id');

// Custom fields
$loader = (new JsonLoader())
    ->contentField('text')    // Use "text" field for content
    ->idField('doc_id');      // Use "doc_id" field for ID
```

### Metadata Extraction

```php
// Extract specific fields as metadata
$loader = (new JsonLoader())->metadataFields(['category', 'author', 'tags']);

$documents = $loader->load('/path/to/data.json');
$doc = $documents[0];

echo $doc->getMeta('category');
echo $doc->getMeta('author');
echo $doc->getMeta('tags');
// Other fields are excluded from metadata
```

### Fallback Content Fields

If the configured content field is not found, the loader tries these fields in order:
- `text`
- `body`
- `message`
- `description`
- `value`

If none are found, the entire object is JSON-encoded as content.

## DirectoryLoader

Recursively loads documents from a directory, auto-detecting file types.

### Basic Usage

```php
use AgenticOrchestrator\Rag\Loaders\DirectoryLoader;

$loader = new DirectoryLoader();
$documents = $loader->load('/path/to/docs');

// Loads all supported files: .txt, .md, .json, .jsonl
```

### Filter by Extension

```php
$loader = (new DirectoryLoader())->includeExtensions(['md', 'txt']);
$documents = $loader->load('/path/to/docs');
// Only loads .md and .txt files
```

### Exclude Patterns

By default, these patterns are excluded:
- `**/node_modules/**`
- `**/vendor/**`
- `**/.git/**`
- `**/.*` (hidden files)

```php
$loader = (new DirectoryLoader())->exclude([
    '**/test/**',
    '**/drafts/**',
    '**/*.backup',
]);
```

### Non-Recursive Loading

```php
$loader = (new DirectoryLoader())->recursive(false);
$documents = $loader->load('/path/to/docs');
// Only loads files in the immediate directory
```

### Register Custom Loaders

```php
use AgenticOrchestrator\Rag\Loaders\DirectoryLoader;

class CsvLoader implements DocumentLoaderInterface
{
    // ... implementation
}

$loader = (new DirectoryLoader())
    ->registerLoader(['csv', 'tsv'], new CsvLoader());
```

## Document Object

All loaders produce `Document` objects:

```php
use AgenticOrchestrator\Rag\Document;

// Properties
$doc->id;       // Unique identifier
$doc->content;  // Text content
$doc->metadata; // Array of metadata
$doc->source;   // Source path (if from file)

// Methods
$doc->getMeta('key', 'default');
$doc->hasMeta('key');
$doc->getLength();
$doc->isEmpty();

// Immutable modifications
$newDoc = $doc->withContent('new content');
$newDoc = $doc->withMetadata(['extra' => 'data']);
$newDoc = $doc->withId('new-id');
```

### Creating Documents Manually

```php
// From text
$doc = Document::fromText('Content here', ['type' => 'manual']);

// From file data
$doc = Document::fromFile('/path/to/file.txt', $content, $metadata);

// From array
$doc = Document::fromArray([
    'id' => 'custom-id',
    'content' => 'Content',
    'metadata' => ['key' => 'value'],
    'source' => '/path/to/source',
]);

// Using constructor
$doc = new Document(
    id: 'my-id',
    content: 'My content',
    metadata: ['key' => 'value'],
    source: '/optional/source/path',
);
```

## Implementing Custom Loaders

Create a loader for any file format by implementing `DocumentLoaderInterface`:

```php
use AgenticOrchestrator\Rag\Contracts\DocumentLoaderInterface;
use AgenticOrchestrator\Rag\Document;

class PdfLoader implements DocumentLoaderInterface
{
    public function load(string $source): array
    {
        // Validate source
        if (!file_exists($source)) {
            throw new \InvalidArgumentException("File not found: {$source}");
        }

        // Extract text from PDF (using smalot/pdfparser or similar)
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($source);
        $content = $pdf->getText();

        // Return Document array
        return [
            Document::fromFile($source, $content, [
                'loader' => 'pdf',
                'pages' => count($pdf->getPages()),
            ]),
        ];
    }

    public function supports(string $source): bool
    {
        if (!file_exists($source) || !is_file($source)) {
            return false;
        }

        return strtolower(pathinfo($source, PATHINFO_EXTENSION)) === 'pdf';
    }
}
```

### Using Custom Loader

```php
// With pipeline
$pipeline->loader(new PdfLoader())->from('/path/to/doc.pdf')->ingest();

// With DirectoryLoader
$dirLoader = (new DirectoryLoader())
    ->registerLoader(['pdf'], new PdfLoader());
```

## Best Practices

1. **Choose the Right Loader**: Use specialized loaders (Markdown, JSON) when possible for better metadata extraction.

2. **Filter Large Directories**: Use `includeExtensions()` and `exclude()` to avoid loading unnecessary files.

3. **Handle Encoding**: Specify encoding for non-UTF-8 files to avoid character issues.

4. **Split Large Documents**: Use `splitByHeadings()` for Markdown files to create more focused chunks.

5. **Preserve Metadata**: Configure loaders to extract relevant metadata for filtering during retrieval.
