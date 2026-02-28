<?php

declare(strict_types=1);

use App\Services\ResponseParser;

describe('ResponseParser', function () {
    beforeEach(function () {
        $this->parser = new ResponseParser;
    });

    describe('stripCodeFences', function () {
        it('strips json code fences', function () {
            $input = '```json
{"steps": []}
```';

            expect($this->parser->stripCodeFences($input))->toBe('{"steps": []}');
        });

        it('strips plain code fences', function () {
            $input = '```
{"verdict": "approve"}
```';

            expect($this->parser->stripCodeFences($input))->toBe('{"verdict": "approve"}');
        });

        it('strips markdown code fences', function () {
            $input = '```markdown
# Some content
```';

            expect($this->parser->stripCodeFences($input))->toBe('# Some content');
        });

        it('strips md code fences', function () {
            $input = '```md
content here
```';

            expect($this->parser->stripCodeFences($input))->toBe('content here');
        });

        it('returns text unchanged when no fences present', function () {
            $input = '{"steps": []}';

            expect($this->parser->stripCodeFences($input))->toBe($input);
        });
    });

    describe('parseJson', function () {
        it('parses plain JSON', function () {
            $input = '{"steps": [], "summary": "test"}';

            $result = $this->parser->parseJson($input);

            expect($result)->toBe(['steps' => [], 'summary' => 'test']);
        });

        it('parses JSON inside code fences', function () {
            $input = '```json
{"verdict": "approve", "summary": "looks good"}
```';

            $result = $this->parser->parseJson($input);

            expect($result)
                ->toBeArray()
                ->toHaveKey('verdict', 'approve')
                ->toHaveKey('summary', 'looks good');
        });

        it('returns null for invalid JSON', function () {
            $result = $this->parser->parseJson('not json at all');

            expect($result)->toBeNull();
        });

        it('reads output.json fallback', function () {
            $tmpDir = sys_get_temp_dir().'/oracle_test_'.uniqid();
            mkdir($tmpDir);
            file_put_contents($tmpDir.'/output.json', '{"steps": [{"title": "test"}]}');

            $result = $this->parser->parseJson('invalid json', $tmpDir);

            expect($result)
                ->toBeArray()
                ->toHaveKey('steps');

            // output.json should be cleaned up
            expect(is_file($tmpDir.'/output.json'))->toBeFalse();

            rmdir($tmpDir);
        });

        it('trims whitespace before parsing', function () {
            $input = '  {"key": "value"}  ';

            $result = $this->parser->parseJson($input);

            expect($result)->toBe(['key' => 'value']);
        });
    });
});
