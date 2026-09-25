<?php

declare(strict_types=1);

namespace Tests\Unit\Tooling;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Tooling\Modifier\ClassModifier;

/**
 * @internal
 */
final class ClassModifierTest extends CIUnitTestCase
{
    public function testMutateArrayPropertyPreservesClassConstFetchAndComments(): void
    {
        $code = <<<'PHP'
<?php

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
    ];

    public array $globals = [
        'before' => [
            // 'honeypot',
            // 'csrf',
        ],
        'after' => [
            // 'honeypot',
        ],
    ];
}
PHP;

        $modifier = ClassModifier::fromString($code);

        // Mutate aliases
        $modifier->mutateArrayProperty('aliases', static function (array $aliases) {
            $aliases['inertia'] = 'App\Filters\HandleInertiaRequests';
            return $aliases;
        });

        // Mutate globals
        $modifier->mutateArrayProperty('globals', static function (array $globals) {
            $globals['before'][] = 'inertia';
            $globals['after'][] = 'inertia';
            return $globals;
        });

        $rendered = $modifier->render();

        // 1. Original class constants MUST NOT be converted to strings
        $this->assertStringContainsString("'csrf' => CSRF::class", $rendered);
        $this->assertStringContainsString("'toolbar' => DebugToolbar::class", $rendered);
        $this->assertStringContainsString("'cors' => Cors::class", $rendered);
        $this->assertStringNotContainsString("'csrf' => 'CSRF'", $rendered);
        $this->assertStringNotContainsString("'toolbar' => 'DebugToolbar'", $rendered);

        // 2. Added class MUST be emitted as ClassConstFetch
        $this->assertStringContainsString("'inertia' => \\App\\Filters\\HandleInertiaRequests::class", $rendered);

        // 3. Globals updated correctly
        $this->assertStringContainsString("'before' => ['inertia']", $rendered);
        $this->assertStringContainsString("'after' => ['inertia']", $rendered);
    }

    public function testAddUseStatement(): void
    {
        $code = <<<'PHP'
<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class App extends BaseConfig
{
}
PHP;

        $modifier = ClassModifier::fromString($code);
        $modifier->addUseStatement('App\Filters\HandleInertiaRequests');

        $rendered = $modifier->render();
        $this->assertStringContainsString('use App\Filters\HandleInertiaRequests;', $rendered);

        // Calling again should not duplicate
        $modifier->addUseStatement('App\Filters\HandleInertiaRequests');
        $this->assertSame(1, substr_count($modifier->render(), 'use App\Filters\HandleInertiaRequests;'));
    }

    public function testUpsertPropertyAndAddTrait(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class BaseController extends Controller
{
}
PHP;

        $modifier = ClassModifier::fromString($code);
        $modifier->addTrait('Jengo\Base\Container\Traits\HasContainer');
        $modifier->upsertProperty('theme', 'dark', 'protected');

        $rendered = $modifier->render();

        $this->assertStringContainsString('use \\Jengo\\Base\\Container\\Traits\\HasContainer;', $rendered);
        $this->assertStringContainsString("protected \$theme = 'dark';", $rendered);
    }

    public function testMutateArrayPropertyPreservesNamespacedHelperStrings(): void
    {
        $code = <<<'PHP'
<?php

namespace Config;

use CodeIgniter\Config\AutoloadConfig;

class Autoload extends AutoloadConfig
{
    public $helpers = [
        'form',
    ];
}
PHP;

        $modifier = ClassModifier::fromString($code);
        $modifier->mutateArrayProperty('helpers', static function (array $helpers) {
            $helpers[] = 'Jengo\Base\Helpers\jengo';
            $helpers[] = 'CodeIgniter\Shield\Helpers\auth';
            $helpers[] = 'CodeIgniter\Settings\Helpers\setting';
            return $helpers;
        });

        $rendered = $modifier->render();

        $this->assertStringContainsString("'Jengo\\Base\\Helpers\\jengo'", $rendered);
        $this->assertStringContainsString("'CodeIgniter\\Shield\\Helpers\\auth'", $rendered);
        $this->assertStringContainsString("'CodeIgniter\\Settings\\Helpers\\setting'", $rendered);
        $this->assertStringNotContainsString('jengo::class', $rendered);
        $this->assertStringNotContainsString('auth::class', $rendered);
        $this->assertStringNotContainsString('setting::class', $rendered);
    }
}

