<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ProjectContext;
use Illuminate\Support\Facades\File;

final class ContextGatherer
{
    public function __construct(
        private readonly RecallClientInterface $recall,
        private readonly ConfigManager $config,
    ) {}

    /**
     * Gather all available context for a project.
     */
    public function gather(string $projectPath, ?string $query = null): ProjectContext
    {
        $conventions = $this->readConventions($projectPath);
        $docs = $this->readDocs($projectPath);
        $hierarchicalDocs = $this->readHierarchicalDocs($projectPath);
        $memories = $query !== null ? $this->queryMemories($query) : [];

        return new ProjectContext(
            projectPath: $projectPath,
            conventions: $conventions,
            docs: $docs,
            hierarchicalDocs: $hierarchicalDocs,
            memories: $memories,
        );
    }

    /**
     * Read the main conventions file (AGENTS.md or CLAUDE.md).
     */
    private function readConventions(string $projectPath): ?string
    {
        /** @var string|null $conventionsFile */
        $conventionsFile = $this->config->projectGet('conventions_file');

        $candidates = $conventionsFile !== null
            ? [$conventionsFile]
            : ['AGENTS.md', 'CLAUDE.md'];

        foreach ($candidates as $candidate) {
            $path = $projectPath.'/'.$candidate;
            if (File::exists($path)) {
                return File::get($path);
            }
        }

        return null;
    }

    /**
     * Read documentation files from docs/solutions/.
     *
     * @return array<string, string>
     */
    private function readDocs(string $projectPath): array
    {
        $docsDir = $projectPath.'/docs/solutions';
        if (! is_dir($docsDir)) {
            return [];
        }

        $docs = [];
        $files = File::allFiles($docsDir);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $relativePath = 'docs/solutions/'.$file->getRelativePathname();
            $docs[$relativePath] = $file->getContents();
        }

        return $docs;
    }

    /**
     * Read hierarchical CLAUDE.md files from relevant directories.
     *
     * @return array<string, string>
     */
    private function readHierarchicalDocs(string $projectPath): array
    {
        $docs = [];

        $searchDirs = ['app', 'resources', 'tests'];

        foreach ($searchDirs as $dir) {
            $fullDir = $projectPath.'/'.$dir;
            if (! is_dir($fullDir)) {
                continue;
            }

            $this->findClaudeMdFiles($fullDir, $projectPath, $docs);
        }

        return $docs;
    }

    /**
     * @param  array<string, string>  $docs
     */
    private function findClaudeMdFiles(string $directory, string $projectRoot, array &$docs): void
    {
        foreach (['CLAUDE.md', 'AGENTS.md'] as $filename) {
            $path = $directory.'/'.$filename;
            if (File::exists($path)) {
                $relativePath = ltrim(str_replace($projectRoot, '', $path), '/');
                $docs[$relativePath] = File::get($path);
            }
        }

        $subdirs = File::directories($directory);
        foreach ($subdirs as $subdir) {
            $basename = basename($subdir);
            if (in_array($basename, ['vendor', 'node_modules', '.git', 'storage'])) {
                continue;
            }
            $this->findClaudeMdFiles($subdir, $projectRoot, $docs);
        }
    }

    /**
     * Query Recall for relevant memories.
     *
     * @return array<array{content: string, source: string|null}>
     */
    private function queryMemories(string $query): array
    {
        /** @var string|null $agentId */
        $agentId = $this->config->projectGet('recall_agent_id');

        try {
            return $this->recall->search($query, $agentId);
        } catch (\Throwable) {
            // Recall is optional — don't fail the whole operation
            return [];
        }
    }
}
