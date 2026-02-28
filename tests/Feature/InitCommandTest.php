<?php

declare(strict_types=1);

describe('InitCommand', function () {
    it('creates .oracle.json in auto mode', function () {
        $tmpDir = sys_get_temp_dir().'/oracle_test_init_'.uniqid();
        mkdir($tmpDir, 0755, true);

        chdir($tmpDir);

        $this->artisan('init', ['--auto' => true, '--json' => true])
            ->assertSuccessful();

        expect(is_file($tmpDir.'/.oracle.json'))->toBeTrue();

        $config = json_decode(file_get_contents($tmpDir.'/.oracle.json'), true);
        expect($config)
            ->toHaveKey('driver', 'gemini')
            ->toHaveKey('model', 'gemini-2.5-flash');

        @unlink($tmpDir.'/.oracle.json');
        @rmdir($tmpDir);
    });

    it('rejects when .oracle.json already exists', function () {
        $tmpDir = sys_get_temp_dir().'/oracle_test_init_exists_'.uniqid();
        mkdir($tmpDir, 0755, true);
        file_put_contents($tmpDir.'/.oracle.json', '{}');

        chdir($tmpDir);

        $this->artisan('init', ['--auto' => true, '--json' => true])
            ->assertFailed();

        @unlink($tmpDir.'/.oracle.json');
        @rmdir($tmpDir);
    });

    it('uses specified driver', function () {
        $tmpDir = sys_get_temp_dir().'/oracle_test_init_driver_'.uniqid();
        mkdir($tmpDir, 0755, true);

        chdir($tmpDir);

        $this->artisan('init', ['--auto' => true, '--driver' => 'claude', '--json' => true])
            ->assertSuccessful();

        $config = json_decode(file_get_contents($tmpDir.'/.oracle.json'), true);
        expect($config['driver'])->toBe('claude');
        expect($config['model'])->toBe('claude-sonnet-4-5-20250514');

        @unlink($tmpDir.'/.oracle.json');
        @rmdir($tmpDir);
    });
});
