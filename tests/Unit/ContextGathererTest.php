<?php

declare(strict_types=1);

use App\Data\ProjectContext;
use App\Services\ConfigManager;
use App\Services\ContextGatherer;
use App\Services\RecallClientInterface;

describe('ContextGatherer', function () {
    it('reads AGENTS.md as conventions', function () {
        $tmpDir = sys_get_temp_dir().'/oracle_test_context_'.uniqid();
        mkdir($tmpDir, 0755, true);
        file_put_contents($tmpDir.'/AGENTS.md', '# Project Conventions');

        $recall = Mockery::mock(RecallClientInterface::class);
        $recall->shouldReceive('search')->andReturn([]);

        $config = new ConfigManager;
        $config->loadProject($tmpDir);

        $gatherer = new ContextGatherer($recall, $config);
        $context = $gatherer->gather($tmpDir);

        expect($context)
            ->toBeInstanceOf(ProjectContext::class)
            ->conventions->toBe('# Project Conventions')
            ->projectPath->toBe($tmpDir);

        @unlink($tmpDir.'/AGENTS.md');
        @rmdir($tmpDir);
    });

    it('falls back to CLAUDE.md when AGENTS.md missing', function () {
        $tmpDir = sys_get_temp_dir().'/oracle_test_context_fb_'.uniqid();
        mkdir($tmpDir, 0755, true);
        file_put_contents($tmpDir.'/CLAUDE.md', '# Claude Conventions');

        $recall = Mockery::mock(RecallClientInterface::class);
        $recall->shouldReceive('search')->andReturn([]);

        $config = new ConfigManager;
        $config->loadProject($tmpDir);

        $gatherer = new ContextGatherer($recall, $config);
        $context = $gatherer->gather($tmpDir);

        expect($context->conventions)->toBe('# Claude Conventions');

        @unlink($tmpDir.'/CLAUDE.md');
        @rmdir($tmpDir);
    });

    it('reads docs/solutions/ files', function () {
        $tmpDir = sys_get_temp_dir().'/oracle_test_context_docs_'.uniqid();
        mkdir($tmpDir.'/docs/solutions', 0755, true);
        file_put_contents($tmpDir.'/docs/solutions/fix.md', '# Fix Guide');

        $recall = Mockery::mock(RecallClientInterface::class);
        $recall->shouldReceive('search')->andReturn([]);

        $config = new ConfigManager;
        $config->loadProject($tmpDir);

        $gatherer = new ContextGatherer($recall, $config);
        $context = $gatherer->gather($tmpDir);

        expect($context->docs)->toHaveKey('docs/solutions/fix.md');
        expect($context->docs['docs/solutions/fix.md'])->toBe('# Fix Guide');

        @unlink($tmpDir.'/docs/solutions/fix.md');
        @rmdir($tmpDir.'/docs/solutions');
        @rmdir($tmpDir.'/docs');
        @rmdir($tmpDir);
    });

    it('queries recall when query is provided', function () {
        $tmpDir = sys_get_temp_dir().'/oracle_test_context_recall_'.uniqid();
        mkdir($tmpDir, 0755, true);

        $recall = Mockery::mock(RecallClientInterface::class);
        $recall->shouldReceive('search')
            ->with('dark mode', null)
            ->once()
            ->andReturn([
                ['content' => 'Use CSS variables for theming', 'source' => 'oracle'],
            ]);

        $config = new ConfigManager;
        $config->loadProject($tmpDir);

        $gatherer = new ContextGatherer($recall, $config);
        $context = $gatherer->gather($tmpDir, 'dark mode');

        expect($context->memories)->toHaveCount(1);
        expect($context->memories[0]['content'])->toBe('Use CSS variables for theming');

        @rmdir($tmpDir);
    });

    it('handles recall failures gracefully', function () {
        $tmpDir = sys_get_temp_dir().'/oracle_test_context_fail_'.uniqid();
        mkdir($tmpDir, 0755, true);

        $recall = Mockery::mock(RecallClientInterface::class);
        $recall->shouldReceive('search')
            ->andThrow(new RuntimeException('Connection refused'));

        $config = new ConfigManager;
        $config->loadProject($tmpDir);

        $gatherer = new ContextGatherer($recall, $config);
        $context = $gatherer->gather($tmpDir, 'some query');

        expect($context->memories)->toBe([]);

        @rmdir($tmpDir);
    });
});
