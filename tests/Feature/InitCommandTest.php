<?php

declare(strict_types=1);

describe('InitCommand', function () {
    it('creates .dexter.json in auto mode', function () {
        $tmpDir = sys_get_temp_dir().'/dexter_test_init_'.uniqid();
        mkdir($tmpDir, 0755, true);

        chdir($tmpDir);

        $this->artisan('init', ['--auto' => true, '--json' => true])
            ->assertSuccessful();

        expect(is_file($tmpDir.'/.dexter.json'))->toBeTrue();

        $config = json_decode(file_get_contents($tmpDir.'/.dexter.json'), true);
        expect($config)
            ->toHaveKey('driver', 'gemini')
            ->toHaveKey('model', 'gemini-2.5-flash');

        @unlink($tmpDir.'/.dexter.json');
        @rmdir($tmpDir);
    });

    it('rejects when .dexter.json already exists', function () {
        $tmpDir = sys_get_temp_dir().'/dexter_test_init_exists_'.uniqid();
        mkdir($tmpDir, 0755, true);
        file_put_contents($tmpDir.'/.dexter.json', '{}');

        chdir($tmpDir);

        $this->artisan('init', ['--auto' => true, '--json' => true])
            ->assertFailed();

        @unlink($tmpDir.'/.dexter.json');
        @rmdir($tmpDir);
    });

    it('uses specified driver', function () {
        $tmpDir = sys_get_temp_dir().'/dexter_test_init_driver_'.uniqid();
        mkdir($tmpDir, 0755, true);

        chdir($tmpDir);

        $this->artisan('init', ['--auto' => true, '--driver' => 'claude', '--json' => true])
            ->assertSuccessful();

        $config = json_decode(file_get_contents($tmpDir.'/.dexter.json'), true);
        expect($config['driver'])->toBe('claude');
        expect($config['model'])->toBe('claude-sonnet-4-5-20250514');

        @unlink($tmpDir.'/.dexter.json');
        @rmdir($tmpDir);
    });
});
