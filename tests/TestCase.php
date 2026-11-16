<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The trading desk keeps a config cache so Apache cannot lose its
     * settings between requests. Tests must boot from phpunit.xml instead,
     * then put that cache back before the process ends.
     */
    public function createApplication()
    {
        $cached = dirname(__DIR__).'/bootstrap/cache/config.php';
        $held = $cached.'.held';

        if (is_file($cached) && ! is_file($held)) {
            rename($cached, $held);

            register_shutdown_function(static function () use ($cached, $held): void {
                if (is_file($held) && ! is_file($cached)) {
                    rename($held, $cached);
                }
            });
        }

        return parent::createApplication();
    }
}
