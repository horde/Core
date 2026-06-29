<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Test\Unit\Factory;

use Horde\HashTable\HashTable;
use Horde_Cache_Exception;
use Horde_Core_Factory_Cache;
use Horde_Injector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * Unit tests for {@see Horde_Core_Factory_Cache::_resolveHashTable()}.
 *
 * The factory's full create() path touches Horde::getDriverConfig and a
 * matrix of globals that are out of scope for this regression suite. The
 * change under test is local to _resolveHashTable() and its diagnostic
 * helper. Reflection invokes them on a real factory instance so the test
 * exercises the production class without subclassing.
 *
 * Issue horde/Core#159: a misresolved modern HashTable used to silently fall
 * back to the deprecated Horde_Core_HashTable_Wrapper, which an out-of-tree
 * rebind of 'Horde_HashTable' could then route into the modern-shape get()
 * and produce a TypeError. The factory now refuses the silent swap and
 * raises a descriptive Horde_Cache_Exception instead; the wrapper is never
 * consulted.
 */
#[CoversClass(Horde_Core_Factory_Cache::class)]
class CacheResolveHashTableTest extends TestCase
{
    /**
     * Track $conf so each test starts from a clean slate. Some tests poke
     * $conf['cache']['driver'] and $conf['hashtable']['driver']; tearDown
     * restores whatever was there.
     *
     * @var array<string, mixed>|null
     */
    private ?array $savedConf = null;

    protected function setUp(): void
    {
        global $conf;
        $this->savedConf = $conf ?? null;
        $conf = [];
    }

    protected function tearDown(): void
    {
        global $conf;
        $conf = $this->savedConf;
    }

    /**
     * Build a Horde_Core_Factory_Cache around a mocked injector with the
     * supplied bindings. Unknown keys throw RuntimeException to mimic
     * Horde_Injector behaviour for unbound keys.
     *
     * Critically: 'Horde_Core_HashTable_Wrapper' is NEVER added to
     * $instances. A test that fails by reaching the wrapper would surface
     * here as "No binding for Horde_Core_HashTable_Wrapper" — which is the
     * exact protection the regression test is asserting.
     *
     * @param array<string, object|callable> $instances Either an object to
     *                                                  return, or a callable
     *                                                  invoked to compute the
     *                                                  return value (used to
     *                                                  inject Throwables).
     */
    private function makeFactory(array $instances): Horde_Core_Factory_Cache
    {
        $injector = $this->createMock(Horde_Injector::class);
        $injector->expects($this->atLeastOnce())
            ->method('getInstance')
            ->willReturnCallback(function (string $key) use ($instances) {
                if (!array_key_exists($key, $instances)) {
                    throw new RuntimeException("No binding for $key");
                }
                $binding = $instances[$key];
                if (is_callable($binding)) {
                    return $binding();
                }
                return $binding;
            });

        return new Horde_Core_Factory_Cache($injector);
    }

    /**
     * Invoke the protected _resolveHashTable via reflection so the test
     * exercises the production class instead of a subclass.
     */
    private function callResolve(Horde_Core_Factory_Cache $factory, Horde_Injector $injector): mixed
    {
        $method = new ReflectionMethod(Horde_Core_Factory_Cache::class, '_resolveHashTable');

        return $method->invoke($factory, $injector);
    }

    /**
     * Capture the injector the factory was built with, so we can hand it
     * back to _resolveHashTable through reflection. Horde_Core_Factory_Cache
     * stores it as $_injector on the Base parent.
     */
    private function injectorOf(Horde_Core_Factory_Cache $factory): Horde_Injector
    {
        $prop = new \ReflectionProperty(\Horde_Core_Factory_Base::class, '_injector');

        return $prop->getValue($factory);
    }

    #[Test]
    public function testReturnsModernHashTableWhenBound(): void
    {
        $hashtable = $this->createStub(HashTable::class);
        $factory = $this->makeFactory([
            HashTable::class => $hashtable,
        ]);

        $result = $this->callResolve($factory, $this->injectorOf($factory));

        self::assertSame($hashtable, $result);
    }

    #[Test]
    public function testThrowsDescriptiveExceptionWhenModernResolutionFails(): void
    {
        global $conf;
        $conf['cache']['driver'] = 'memcache';
        $conf['hashtable']['driver'] = 'memcache';

        $original = new RuntimeException('Redis client refused connection');
        $factory = $this->makeFactory([
            HashTable::class => static function () use ($original): never {
                throw $original;
            },
        ]);

        try {
            $this->callResolve($factory, $this->injectorOf($factory));
            self::fail('Expected Horde_Cache_Exception was not thrown.');
        } catch (Horde_Cache_Exception $e) {
            self::assertStringContainsString('cache.driver=memcache', $e->getMessage());
            self::assertStringContainsString('hashtable.driver=memcache', $e->getMessage());
            // The original throwable's class and message are folded into the
            // diagnostic. Horde_Exception_Wrapped's two-arg constructor drops
            // a 3rd $previous; inlining the cause keeps the operator-visible
            // trail intact through the wrapping.
            self::assertStringContainsString(RuntimeException::class, $e->getMessage());
            self::assertStringContainsString('Redis client refused connection', $e->getMessage());
            self::assertStringContainsString(
                'Horde_Core_HashTable_Wrapper is no longer used as a fallback',
                $e->getMessage()
            );
        }
    }

    #[Test]
    public function testFailureMessageOmitsHashtableDriverWhenUnset(): void
    {
        global $conf;
        $conf['cache']['driver'] = 'memcache';
        // Deliberately leave $conf['hashtable']['driver'] unset.

        $factory = $this->makeFactory([
            HashTable::class => static function (): never {
                throw new RuntimeException('boom');
            },
        ]);

        try {
            $this->callResolve($factory, $this->injectorOf($factory));
            self::fail('Expected Horde_Cache_Exception was not thrown.');
        } catch (Horde_Cache_Exception $e) {
            self::assertStringContainsString('cache.driver=memcache', $e->getMessage());
            self::assertStringNotContainsString('hashtable.driver=', $e->getMessage());
        }
    }

    #[Test]
    public function testWrapperFallbackIsNotConsultedOnFailure(): void
    {
        // The makeFactory mock throws "No binding for X" for any key not in
        // $instances. By omitting 'Horde_Core_HashTable_Wrapper' here and
        // asserting we receive Horde_Cache_Exception (not RuntimeException
        // from the mock), we prove the wrapper was never looked up.
        $factory = $this->makeFactory([
            HashTable::class => static function (): never {
                throw new RuntimeException('modern resolution failed');
            },
        ]);

        $this->expectException(Horde_Cache_Exception::class);

        $this->callResolve($factory, $this->injectorOf($factory));
    }
}
