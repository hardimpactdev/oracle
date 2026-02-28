<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\WithJsonOutput;
use App\Concerns\WithLlmInvocation;
use App\Concerns\WithProjectContext;
use App\Drivers\LlmDriver;
use App\Services\ConfigManager;
use App\Services\ContextGatherer;
use App\Services\PromptBuilder;
use App\Services\RecallClientInterface;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

final class LearnCommand extends Command
{
    use WithJsonOutput;
    use WithLlmInvocation;
    use WithProjectContext;

    protected $signature = 'learn
        {content? : Direct learning to store}
        {--pr= : Extract learnings from a PR}
        {--from-review= : Extract learnings from review output JSON file}
        {--project= : Path to the project (defaults to cwd)}
        {--driver= : LLM driver (gemini, claude, codex)}
        {--model= : LLM model to use}
        {--importance=0.5 : Importance level (0.0-1.0)}
        {--category= : Category (pattern, gotcha, convention, solution)}
        {--update-docs : Write learnings to docs/solutions/}
        {--json : Output as JSON}';

    protected $description = 'Capture learnings into Recall and optionally update project docs';

    public function handle(
        ConfigManager $config,
        ContextGatherer $gatherer,
        PromptBuilder $promptBuilder,
        RecallClientInterface $recall,
        LlmDriver $driver,
    ): int {
        try {
            $projectPath = $this->resolveProjectPath();
            $config->loadProject($projectPath);
        } catch (\RuntimeException $e) {
            return $this->failWithMessage($e->getMessage());
        }

        /** @var string|null $agentId */
        $agentId = $config->projectGet('recall_agent_id');
        /** @var float $importance */
        $importance = (float) $this->option('importance');

        // Direct learning input
        /** @var string|null $directContent */
        $directContent = $this->argument('content');
        if ($directContent !== null) {
            return $this->storeDirect($recall, $directContent, $importance, $agentId);
        }

        // Extract from PR
        /** @var string|null $pr */
        $pr = $this->option('pr');
        if ($pr !== null) {
            return $this->extractFromPr(
                $pr, $projectPath, $config, $gatherer, $promptBuilder, $driver, $recall, $agentId, $importance
            );
        }

        // Extract from review output
        /** @var string|null $reviewFile */
        $reviewFile = $this->option('from-review');
        if ($reviewFile !== null) {
            return $this->extractFromReview($reviewFile, $recall, $agentId, $importance);
        }

        return $this->failWithMessage('Provide content, --pr, or --from-review.');
    }

    private function storeDirect(RecallClientInterface $recall, string $content, float $importance, ?string $agentId): int
    {
        /** @var string|null $category */
        $category = $this->option('category');

        $id = $recall->store($content, $importance, $agentId, 'oracle:direct', $category);

        if ($id === null) {
            return $this->failWithMessage('Failed to store learning in Recall.');
        }

        if ($this->wantsJson()) {
            return $this->outputJsonSuccess([
                'stored' => 1,
                'learnings' => [['id' => $id, 'content' => $content, 'category' => $category]],
            ]);
        }

        $this->info("Stored learning #{$id} in Recall.");

        return self::SUCCESS;
    }

    private function extractFromPr(
        string $prUrl,
        string $projectPath,
        ConfigManager $config,
        ContextGatherer $gatherer,
        PromptBuilder $promptBuilder,
        LlmDriver $driver,
        RecallClientInterface $recall,
        ?string $agentId,
        float $importance,
    ): int {
        // Get PR diff
        $diff = null;
        if (preg_match('/\/pull\/(\d+)/', $prUrl, $matches) === 1) {
            $result = Process::path($projectPath)->run(['gh', 'pr', 'diff', $matches[1]]);
            if ($result->successful()) {
                $diff = $result->output();
            }
        }

        if ($diff === null) {
            return $this->failWithMessage("Could not get diff for PR: {$prUrl}");
        }

        $driverName = $this->resolveDriver($config);
        $driverInstance = $this->resolveDriverInstance($driverName);
        $model = $this->resolveModel($config);
        $timeout = $this->resolveTimeout($config);

        if (! $this->wantsJson()) {
            $this->info("Extracting learnings with {$driverName}/{$model}...");
        }

        $context = $gatherer->gather($projectPath, 'learning extraction');
        $prompt = $promptBuilder->buildLearnPrompt($diff, "PR: {$prUrl}", $context);

        try {
            $result = $this->invokeLlm($driverInstance, $model, $prompt, $projectPath, $timeout);
        } catch (\RuntimeException $e) {
            return $this->failWithMessage($e->getMessage());
        }

        if ($result === null || ! isset($result['learnings'])) {
            return $this->failWithMessage('Failed to extract learnings from PR.');
        }

        return $this->storeLearnings($result['learnings'], $recall, $agentId, $importance, $prUrl, $projectPath);
    }

    private function extractFromReview(string $reviewFile, RecallClientInterface $recall, ?string $agentId, float $importance): int
    {
        if (! is_file($reviewFile)) {
            return $this->failWithMessage("Review file not found: {$reviewFile}");
        }

        $content = file_get_contents($reviewFile);
        if ($content === false) {
            return $this->failWithMessage("Could not read review file: {$reviewFile}");
        }

        $review = json_decode($content, true);
        if (! is_array($review)) {
            return $this->failWithMessage('Invalid JSON in review file.');
        }

        $learnings = [];

        // Extract from comments
        foreach ($review['comments'] ?? [] as $comment) {
            $learnings[] = [
                'content' => $comment['body'] ?? '',
                'category' => 'convention',
                'importance' => $importance,
            ];
        }

        // Extract from friction points
        foreach ($review['friction_points'] ?? $review['workarounds'] ?? [] as $point) {
            $learnings[] = [
                'content' => $point['description'] ?? '',
                'category' => 'gotcha',
                'importance' => $importance,
            ];
        }

        if ($learnings === []) {
            return $this->failWithMessage('No learnings found in review file.');
        }

        return $this->storeLearnings($learnings, $recall, $agentId, $importance, "review:{$reviewFile}");
    }

    /**
     * @param  array<array{content: string, category: string, importance?: float}>  $learnings
     */
    private function storeLearnings(
        array $learnings,
        RecallClientInterface $recall,
        ?string $agentId,
        float $defaultImportance,
        string $source,
        ?string $projectPath = null,
    ): int {
        $stored = [];

        foreach ($learnings as $learning) {
            $imp = (float) ($learning['importance'] ?? $defaultImportance);
            $category = $learning['category'] ?? null;

            $id = $recall->store($learning['content'], $imp, $agentId, "oracle:{$source}", $category);

            if ($id !== null) {
                $stored[] = ['id' => $id, 'content' => $learning['content'], 'category' => $category];
            }
        }

        // Optionally write to docs/solutions/
        if ($this->option('update-docs') && $projectPath !== null) {
            $this->writeDocs($stored, $projectPath);
        }

        if ($this->wantsJson()) {
            return $this->outputJsonSuccess([
                'stored' => count($stored),
                'learnings' => $stored,
            ]);
        }

        $this->info('Stored '.count($stored).' learnings in Recall.');

        return self::SUCCESS;
    }

    /**
     * @param  array<array{id: int, content: string, category: string|null}>  $learnings
     */
    private function writeDocs(array $learnings, string $projectPath): void
    {
        $docsDir = $projectPath.'/docs/solutions/oracle';
        if (! is_dir($docsDir)) {
            mkdir($docsDir, 0755, true);
        }

        $date = date('Ymd');
        $filename = "learnings-{$date}.md";
        $path = $docsDir.'/'.$filename;

        $content = "# Oracle Learnings — {$date}\n\n";

        foreach ($learnings as $learning) {
            $category = $learning['category'] ?? 'uncategorized';
            $content .= "## [{$category}] Learning #{$learning['id']}\n\n{$learning['content']}\n\n";
        }

        file_put_contents($path, $content);
    }

    private function resolveDriverInstance(string $driverName): LlmDriver
    {
        return match ($driverName) {
            'claude' => new \App\Drivers\ClaudeDriver,
            'codex' => new \App\Drivers\CodexDriver,
            default => new \App\Drivers\GeminiDriver,
        };
    }
}
