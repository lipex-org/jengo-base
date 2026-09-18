<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;

final class DxHelpersTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('Jengo\Base\Helpers\jengo');
    }

    public function testValueHelper(): void
    {
        $this->assertSame('hello', value('hello'));
        $this->assertSame(42, value(fn() => 42));
        $this->assertSame('greet Alice', value(fn($name) => "greet {$name}", 'Alice'));
    }

    public function testTapHelperWithCallback(): void
    {
        $obj = new \stdClass();
        $obj->count = 1;

        $result = tap($obj, function ($o) {
            $o->count += 5;
        });

        $this->assertSame($obj, $result);
        $this->assertSame(6, $obj->count);
    }

    public function testTapHelperHigherOrderProxy(): void
    {
        $target = new class {
            public int $called = 0;
            public function increment(int $by = 1): void
            {
                $this->called += $by;
            }
        };

        $result = tap($target)->increment(3);

        $this->assertSame($target, $result);
        $this->assertSame(3, $target->called);
    }

    public function testRescueReturnsValueOnSuccess(): void
    {
        $result = rescue(fn() => 10 / 2, 'fallback');

        $this->assertSame(5, $result);
    }

    public function testRescueReturnsFallbackOnException(): void
    {
        $result = rescue(function () {
            throw new \RuntimeException('Failure');
        }, 'fallback_value', false);

        $this->assertSame('fallback_value', $result);
    }

    public function testRescueExecutesClosureFallback(): void
    {
        $result = rescue(function () {
            throw new \RuntimeException('Boom');
        }, fn(\Throwable $e) => 'Caught: ' . $e->getMessage(), false);

        $this->assertSame('Caught: Boom', $result);
    }

    public function testRetrySucceedsOnFirstAttempt(): void
    {
        $attempts = 0;
        $result = retry(3, function ($attempt) use (&$attempts) {
            $attempts = $attempt;
            return 'success';
        });

        $this->assertSame('success', $result);
        $this->assertSame(1, $attempts);
    }

    public function testRetryRetriesUntilSuccess(): void
    {
        $attempts = 0;
        $result = retry(3, function ($attempt) use (&$attempts) {
            $attempts = $attempt;
            if ($attempt < 2) {
                throw new \RuntimeException('Transient error');
            }
            return 'recovered';
        });

        $this->assertSame('recovered', $result);
        $this->assertSame(2, $attempts);
    }

    public function testRetryThrowsExceptionWhenExhausted(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Persistent failure');

        retry(2, function () {
            throw new \RuntimeException('Persistent failure');
        });
    }

    public function testBlankHelper(): void
    {
        $this->assertTrue(blank(null));
        $this->assertTrue(blank(''));
        $this->assertTrue(blank('   '));
        $this->assertTrue(blank([]));

        // 0 and false must NOT be blank
        $this->assertFalse(blank(0));
        $this->assertFalse(blank('0'));
        $this->assertFalse(blank(false));
        $this->assertFalse(blank('hello'));
        $this->assertFalse(blank(['item']));
    }

    public function testFilledHelper(): void
    {
        $this->assertFalse(filled(null));
        $this->assertFalse(filled(''));
        $this->assertTrue(filled('hello'));
        $this->assertTrue(filled(0));
        $this->assertTrue(filled(false));
    }

    public function testDataGetWithArraysAndObjects(): void
    {
        $data = [
            'user' => [
                'name' => 'John',
                'address' => (object) [
                    'city' => 'Nairobi',
                ],
            ],
            'teams' => [
                ['name' => 'Engineering'],
                ['name' => 'Marketing'],
            ],
        ];

        $this->assertSame('John', data_get($data, 'user.name'));
        $this->assertSame('Nairobi', data_get($data, 'user.address.city'));
        $this->assertSame('Default', data_get($data, 'user.missing.key', 'Default'));
        $this->assertSame(['Engineering', 'Marketing'], data_get($data, 'teams.*.name'));
    }

    public function testDataSetWithArrays(): void
    {
        $target = [];
        data_set($target, 'user.profile.name', 'Alice');

        $this->assertSame(['user' => ['profile' => ['name' => 'Alice']]], $target);
    }

    public function testHeadAndLast(): void
    {
        $array = ['first', 'middle', 'last'];

        $this->assertSame('first', head($array));
        $this->assertSame('last', last($array));
    }

    public function testStrAndArrHelpers(): void
    {
        $slug = str('Hello World')->lower()->kebab()->toString();
        $this->assertSame('hello-world', $slug);

        $whenStr = str('hello')
            ->when(true, fn($s) => $s->upper())
            ->toString();
        $this->assertSame('HELLO', $whenStr);

        $unlessStr = str('hello')
            ->unless(false, fn($s) => $s->upper())
            ->toString();
        $this->assertSame('HELLO', $unlessStr);

        $wrapped = \Jengo\Base\Libraries\Arr::wrap('item');
        $this->assertSame(['item'], $wrapped);

        $collapsed = \Jengo\Base\Libraries\Arr::set([['a', 'b'], ['c']])->collapse()->toArray();
        $this->assertSame(['a', 'b', 'c'], $collapsed);

        $filtered = arr(['apple', 'banana', 'cherry'])
            ->when(true, fn($a) => $a->reverse())
            ->toArray();
        $this->assertSame(['cherry', 'banana', 'apple'], array_values($filtered));
    }

    public function testNowAndToday(): void
    {
        $now = now();
        $today = today();

        $this->assertInstanceOf(Time::class, $now);
        $this->assertInstanceOf(Time::class, $today);
        $this->assertSame($now->format('Y-m-d'), $today->format('Y-m-d'));
    }

    public function testFlashHelper(): void
    {
        flash('test_key', 'test_value');
        $this->assertSame('test_value', flash('test_key'));
    }

    public function testLoggerAndLogShortcuts(): void
    {
        info('Test info message');
        warning('Test warning message');
        error('Test error message');
        logger('Direct log message', 'debug');

        $this->assertNotNull(logger());
    }
}
