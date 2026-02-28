<?php

declare(strict_types=1);

namespace App\Data;

final readonly class ProjectContext
{
    /**
     * @param  string  $projectPath  Absolute path to the project root
     * @param  string|null  $conventions  Content of AGENTS.md / CLAUDE.md
     * @param  array<string, string>  $docs  Map of relative path => content from docs/solutions/
     * @param  array<string, string>  $hierarchicalDocs  Map of relative path => content from nested CLAUDE.md files
     * @param  array<array{content: string, source: string|null}>  $memories  Relevant memories from Recall
     */
    public function __construct(
        public string $projectPath,
        public ?string $conventions = null,
        public array $docs = [],
        public array $hierarchicalDocs = [],
        public array $memories = [],
    ) {}

    /**
     * @return array<string>
     */
    public function docPaths(): array
    {
        return array_keys($this->docs);
    }

    /**
     * @return array<string>
     */
    public function memoryContents(): array
    {
        return array_column($this->memories, 'content');
    }
}
