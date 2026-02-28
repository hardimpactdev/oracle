<?php

declare(strict_types=1);

use App\Services\ConfigManager;
use Illuminate\Support\Facades\File;

describe('ConfigManager', function () {
    it('reads and writes global config', function () {
        $config = new ConfigManager;

        $tmpDir = sys_get_temp_dir().'/oracle_test_config_'.uniqid();
        mkdir($tmpDir, 0755, true);

        // Write a config file to a known location
        $configFile = $tmpDir.'/config.json';
        file_put_contents($configFile, json_encode(['driver' => 'claude']));

        // Test get/set on a fresh instance
        $config->set('test_key', 'test_value');

        expect($config->get('test_key'))->toBe('test_value');

        // Cleanup
        @unlink($config->getGlobalConfigDir().'/config.json');
        @rmdir($tmpDir);
    });

    it('loads project config', function () {
        $config = new ConfigManager;

        $tmpDir = sys_get_temp_dir().'/oracle_test_project_'.uniqid();
        mkdir($tmpDir, 0755, true);
        file_put_contents($tmpDir.'/.oracle.json', json_encode([
            'driver' => 'claude',
            'model' => 'claude-sonnet-4-5-20250514',
        ]));

        $config->loadProject($tmpDir);

        expect($config->projectGet('driver'))->toBe('claude');
        expect($config->projectGet('model'))->toBe('claude-sonnet-4-5-20250514');
        expect($config->projectGet('nonexistent'))->toBeNull();

        // Cleanup
        @unlink($tmpDir.'/.oracle.json');
        @rmdir($tmpDir);
    });

    it('resolves with project overriding global', function () {
        $config = new ConfigManager;

        $tmpDir = sys_get_temp_dir().'/oracle_test_resolve_'.uniqid();
        mkdir($tmpDir, 0755, true);
        file_put_contents($tmpDir.'/.oracle.json', json_encode(['driver' => 'claude']));

        $config->loadProject($tmpDir);

        // Project config should win
        expect($config->resolve('driver', 'gemini'))->toBe('claude');

        // Non-existent key falls to default
        expect($config->resolve('timeout', 180))->toBe(180);

        // Cleanup
        @unlink($tmpDir.'/.oracle.json');
        @rmdir($tmpDir);
    });

    it('saves project config', function () {
        $config = new ConfigManager;

        $tmpDir = sys_get_temp_dir().'/oracle_test_save_'.uniqid();
        mkdir($tmpDir, 0755, true);

        $config->setProjectConfig(['driver' => 'codex', 'model' => 'codex-mini']);
        $config->saveProject($tmpDir);

        $saved = json_decode(file_get_contents($tmpDir.'/.oracle.json'), true);
        expect($saved)->toBe(['driver' => 'codex', 'model' => 'codex-mini']);

        // Cleanup
        @unlink($tmpDir.'/.oracle.json');
        @rmdir($tmpDir);
    });
});
