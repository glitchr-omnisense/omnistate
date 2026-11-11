<?php

namespace Omnistate\Tests;

use Omnistate\Testing\RegistryContractTests;
use PHPUnit\Framework\TestCase;

/**
 * The conformance suite where it is written: no class of this package uses
 * the trait, so PHPStan reads it here. Abstract, and not named *Test.php:
 * PHPUnit runs nothing of it (each register's contract test does).
 */
abstract class RegistryContractCase extends TestCase
{
    use RegistryContractTests;
}
