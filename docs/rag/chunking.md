# Chunking Strategies

Chunking breaks large documents into smaller pieces optimized for embedding and retrieval. The right chunking strategy significantly impacts retrieval quality.

## Why Chunk?

1. **Embedding Limitations**: Most embedding models have token limits (e.g., 8192 tokens for OpenAI)
2. **Precision**: Smaller chunks enable more precise retrieval of relevant content
3. **Context Windows**: LLM context windows have limits; smaller chunks allow including more diverse sources

## Available Strategies

| Strategy | Best For | Description |
|----------|----------|-------------|
| `RecursiveCharacterChunker` | Most content | Splits by semantic boundaries (paragraphs, sentences) |
| `FixedSizeChunker` | Uniform content | Splits by exact character count |

## RecursiveCharacterChunker (Recommended)

The recursive chunker attempts to preserve semantic meaning by splitting at natural boundaries.

### How It Works

The chunker tries separators in order of preference:
1. `\n\n` - Double newlines (paragraphs)
2. `\n` - Single newlines
3. `. ` - Sentence endings
4. `? ` - Questions
5. `! ` - Exclamations
6. `; ` - Semicolons
7. `, ` - Commas
8. ` ` - Spaces (words)
9. `` - Character by character (last resort)

### Basic Usage

```php
use AgenticOrchestrator\Rag\Chunking\RecursiveCharacterChunker;
use AgenticOrchestrator\Rag\Document;

$chunker = new RecursiveCharacterChunker(
    chunkSize: 1000,
    overlap: 200,
);

$document = Document::fromText($longContent);
$chunks = $chunker->chunk($document);

foreach ($chunks as $chunk) {
    echo "Chunk {$chunk->getMeta('chunk_index')}: ";
    echo strlen($chunk->content) . " chars\n";
}
```

### With Pipeline

```php
$pipeline = RagPipeline::make()
    ->embeddings($embeddings)
    ->store($store)
    ->chunkSize(1000)
    ->chunkOverlap(200);
    // Uses RecursiveCharacterChunker by default
```

### Custom Separators

```php
$chunker = (new RecursiveCharacterChunker())
    ->setSeparators([
        "\n## ",    // Markdown h2
        "\n### ",   // Markdown h3
        "\n\n",     // Paragraphs
        "\n",       // Lines
        ". ",       // Sentences
    ]);
```

### Chunk Metadata

Each chunk includes metadata about its origin:

```php
$chunk->getMeta('parent_id');     // Original document ID
$chunk->getMeta('chunk_index');   // Position in sequence (0, 1, 2...)
$chunk->getMeta('start_offset');  // Character offset in original
$chunk->getMeta('is_chunk');      // Always true for chunks
```

## FixedSizeChunker

Splits text into chunks of exact character count, regardless of content boundaries.

### Basic Usage

```php
use AgenticOrchestrator\Rag\Chunking\FixedSizeChunker;

$chunker = new FixedSizeChunker(
    chunkSize: 500,
    overlap: 50,
);

$chunks = $chunker->chunk($document);
```

### When to Use

- Content without clear semantic boundaries
- Highly structured or uniform content
- When exact chunk sizes are important

### Configuration

```php
$chunker = (new FixedSizeChunker())
    ->setChunkSize(1000)
    ->setOverlap(200)
    ->trimChunks(true);  // Trim whitespace (default: true)
```

## Chunk Size Guidelines

### Factors to Consider

| Factor | Smaller Chunks | Larger Chunks |
|--------|---------------|---------------|
| Precision | Higher | Lower |
| Context | Less | More |
| Retrieval Cost | Higher (more chunks) | Lower |
| Embedding Cost | Higher | Lower |

### Recommended Sizes

| Content Type | Chunk Size | Overlap |
|--------------|------------|---------|
| FAQ/Q&A | 200-500 | 50-100 |
| Documentation | 500-1000 | 100-200 |
| Articles/Blog Posts | 800-1200 | 150-250 |
| Technical Manuals | 1000-1500 | 200-300 |
| Legal Documents | 500-800 | 100-150 |

### Example: FAQ Content

```php
// Short, focused answers benefit from smaller chunks
$pipeline->chunkSize(300)->chunkOverlap(50);
```

### Example: Technical Documentation

```php
// Longer explanations need more context
$pipeline->chunkSize(1000)->chunkOverlap(200);
```

## Overlap

Overlap ensures context isn't lost at chunk boundaries.

### How Overlap Works

```
Document: "The quick brown fox jumps over the lazy dog."

Chunk Size: 20, Overlap: 5

Chunk 0: "The quick brown fox " (chars 0-19)
Chunk 1: " fox jumps over the " (chars 15-34) <- overlaps with previous
Chunk 2: " the lazy dog."      (chars 30-44) <- overlaps with previous
```

### Overlap Guidelines

- **Minimum**: 10-20% of chunk size
- **Recommended**: 15-25% of chunk size
- **Maximum**: 50% of chunk size (diminishing returns)

```php
// 20% overlap
$chunkSize = 1000;
$overlap = 200;
```

## Chunking Multiple Documents

### Batch Processing

```php
$documents = [
    Document::fromText('First document...'),
    Document::fromText('Second document...'),
    Document::fromText('Third document...'),
];

$chunker = new RecursiveCharacterChunker(1000, 200);
$allChunks = $chunker->chunkAll($documents);

// Each chunk retains reference to its parent document
foreach ($allChunks as $chunk) {
    echo "From document: {$chunk->getMeta('parent_id')}\n";
}
```

## Implementing Custom Chunkers

Create specialized chunkers for specific content types:

```php
use AgenticOrchestrator\Rag\Contracts\ChunkingStrategyInterface;
use AgenticOrchestrator\Rag\Document;

class CodeChunker implements ChunkingStrategyInterface
{
    protected int $chunkSize = 500;
    protected int $overlap = 50;

    public function chunk(Document $document): array
    {
        $content = $document->content;

        // Split by function/class definitions
        $pattern = '/(?=(?:function|class|interface|trait)\s+\w+)/';
        $sections = preg_split($pattern, $content, -1, PREG_SPLIT_NO_EMPTY);

        $chunks = [];
        foreach ($sections as $i => $section) {
            if (trim($section) === '') {
                continue;
            }

            $chunks[] = $document->createChunk(
                content: trim($section),
                chunkIndex: $i,
                startOffset: strpos($content, $section),
            );
        }

        return $chunks;
    }

    public function chunkAll(array $documents): array
    {
        $chunks = [];
        foreach ($documents as $doc) {
            $chunks = array_merge($chunks, $this->chunk($doc));
        }
        return $chunks;
    }

    public function setChunkSize(int $size): static
    {
        $this->chunkSize = $size;
        return $this;
    }

    public function setOverlap(int $overlap): static
    {
        $this->overlap = $overlap;
        return $this;
    }
}
```

### Using Custom Chunker

```php
$pipeline->chunker(new CodeChunker())->from('/path/to/code')->ingest();
```

## Best Practices

1. **Start with RecursiveCharacterChunker**: It works well for most content types.

2. **Tune Based on Content**: Adjust chunk size based on your specific content type.

3. **Test Retrieval Quality**: Experiment with different sizes and measure retrieval accuracy.

4. **Consider Your Queries**: Shorter queries work better with smaller chunks; complex queries may need larger chunks.

5. **Monitor Chunk Distribution**: Check that chunks are reasonably sized:

```php
$chunks = $chunker->chunkAll($documents);
$sizes = array_map(fn($c) => strlen($c->content), $chunks);

echo "Min: " . min($sizes) . "\n";
echo "Max: " . max($sizes) . "\n";
echo "Avg: " . array_sum($sizes) / count($sizes) . "\n";
```

6. **Preserve Metadata**: Chunking preserves original document metadata, enabling filtering during retrieval.
