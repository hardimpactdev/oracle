<?php

declare(strict_types=1);

describe('ConfigCommand', function () {
    it('shows resolved config', function () {
        $this->artisan('config', ['--json' => true])
            ->assertSuccessful();
    });

    it('sets and gets a config value', function () {
        $this->artisan('config', ['action' => 'set', 'key' => 'test_key', 'value' => 'test_value', '--json' => true])
            ->assertSuccessful();

        $this->artisan('config', ['action' => 'get', 'key' => 'test_key', '--json' => true])
            ->assertSuccessful();
    });

    it('rejects unknown actions', function () {
        $this->artisan('config', ['action' => 'invalid', '--json' => true])
            ->assertFailed();
    });

    it('requires key for get action', function () {
        $this->artisan('config', ['action' => 'get', '--json' => true])
            ->assertFailed();
    });

    it('requires key and value for set action', function () {
        $this->artisan('config', ['action' => 'set', '--json' => true])
            ->assertFailed();
    });
});
