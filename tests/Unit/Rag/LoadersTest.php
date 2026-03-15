<?php

declare(strict_types=1);

use AgenticOrchestrator\Rag\Loaders\JsonLoader;
use AgenticOrchestrator\Rag\Loaders\MarkdownLoader;
use AgenticOrchestrator\Rag\Loaders\TextLoader;

describe('TextLoader', function () {
    it('loads text file', function () {
        $loader = new TextLoader;
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'Test content');

        try {
            $docs = $loader->load($tempFile);

            expect($docs)->toHaveCount(1);
            expect($docs[0]->content)->toBe('Test content');
            expect($docs[0]->source)->toBe($tempFile);
            expect($docs[0]->getMeta('loader'))->toBe('text');
        } finally {
            unlink($tempFile);
        }
    });

    it('throws for missing file', function () {
        $loader = new TextLoader;

        expect(fn () => $loader->load('/nonexistent/file.txt'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('supports txt extension', function () {
        $loader = new TextLoader;
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.txt';
        file_put_contents($tempFile, 'content');

        try {
            expect($loader->supports($tempFile))->toBeTrue();
        } finally {
            unlink($tempFile);
        }
    });
});

describe('MarkdownLoader', function () {
    it('loads markdown file', function () {
        $loader = new MarkdownLoader;
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.md';
        file_put_contents($tempFile, "# Title\n\nContent here.");

        try {
            $docs = $loader->load($tempFile);

            expect($docs)->toHaveCount(1);
            expect($docs[0]->content)->toContain('# Title');
            expect($docs[0]->getMeta('loader'))->toBe('markdown');
            expect($docs[0]->getMeta('title'))->toBe('Title');
        } finally {
            unlink($tempFile);
        }
    });

    it('extracts frontmatter', function () {
        $loader = new MarkdownLoader;
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.md';
        $content = <<<'MD'
---
title: My Document
author: Test
---

# Content

Body text.
MD;
        file_put_contents($tempFile, $content);

        try {
            $docs = $loader->load($tempFile);

            expect($docs[0]->getMeta('frontmatter'))->toBeArray();
            expect($docs[0]->getMeta('frontmatter')['title'])->toBe('My Document');
        } finally {
            unlink($tempFile);
        }
    });

    it('splits by headings when configured', function () {
        $loader = (new MarkdownLoader)->splitByHeadings(2);
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.md';
        $content = <<<'MD'
# Main Title

Intro text.

## Section 1

Section 1 content.

## Section 2

Section 2 content.
MD;
        file_put_contents($tempFile, $content);

        try {
            $docs = $loader->load($tempFile);

            // Should have sections split
            expect(count($docs))->toBeGreaterThan(1);
        } finally {
            unlink($tempFile);
        }
    });

    it('supports md extension', function () {
        $loader = new MarkdownLoader;
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.md';
        file_put_contents($tempFile, '# Test');

        try {
            expect($loader->supports($tempFile))->toBeTrue();
        } finally {
            unlink($tempFile);
        }
    });
});

describe('JsonLoader', function () {
    it('loads json array file', function () {
        $loader = new JsonLoader;
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.json';
        $data = [
            ['id' => '1', 'content' => 'First item'],
            ['id' => '2', 'content' => 'Second item'],
        ];
        file_put_contents($tempFile, json_encode($data));

        try {
            $docs = $loader->load($tempFile);

            expect($docs)->toHaveCount(2);
            expect($docs[0]->content)->toBe('First item');
            expect($docs[1]->content)->toBe('Second item');
        } finally {
            unlink($tempFile);
        }
    });

    it('loads single json object', function () {
        $loader = new JsonLoader;
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.json';
        file_put_contents($tempFile, '{"content": "Single object"}');

        try {
            $docs = $loader->load($tempFile);

            expect($docs)->toHaveCount(1);
            expect($docs[0]->content)->toBe('Single object');
        } finally {
            unlink($tempFile);
        }
    });

    it('loads jsonl file', function () {
        $loader = new JsonLoader;
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.jsonl';
        $content = '{"content": "Line 1"}'."\n".'{"content": "Line 2"}';
        file_put_contents($tempFile, $content);

        try {
            $docs = $loader->load($tempFile);

            expect($docs)->toHaveCount(2);
            expect($docs[0]->content)->toBe('Line 1');
            expect($docs[1]->content)->toBe('Line 2');
        } finally {
            unlink($tempFile);
        }
    });

    it('uses custom content field', function () {
        $loader = (new JsonLoader)->contentField('text');
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.json';
        file_put_contents($tempFile, '[{"text": "Custom field content"}]');

        try {
            $docs = $loader->load($tempFile);

            expect($docs[0]->content)->toBe('Custom field content');
        } finally {
            unlink($tempFile);
        }
    });

    it('uses custom id field', function () {
        $loader = (new JsonLoader)->idField('doc_id');
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.json';
        file_put_contents($tempFile, '[{"doc_id": "custom-123", "content": "test"}]');

        try {
            $docs = $loader->load($tempFile);

            expect($docs[0]->id)->toBe('custom-123');
        } finally {
            unlink($tempFile);
        }
    });

    it('extracts metadata fields', function () {
        $loader = (new JsonLoader)->metadataFields(['category', 'author']);
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.json';
        file_put_contents($tempFile, '[{"content": "test", "category": "tech", "author": "AI", "ignored": true}]');

        try {
            $docs = $loader->load($tempFile);

            expect($docs[0]->getMeta('category'))->toBe('tech');
            expect($docs[0]->getMeta('author'))->toBe('AI');
            expect($docs[0]->hasMeta('ignored'))->toBeFalse();
        } finally {
            unlink($tempFile);
        }
    });

    it('supports json extension', function () {
        $loader = new JsonLoader;
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.json';
        file_put_contents($tempFile, '{}');

        try {
            expect($loader->supports($tempFile))->toBeTrue();
        } finally {
            unlink($tempFile);
        }
    });
});
