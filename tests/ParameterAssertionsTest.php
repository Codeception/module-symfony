<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\ParameterAssertionsTrait;
use Tests\Support\CodeceptTestCase;

final class ParameterAssertionsTest extends CodeceptTestCase
{
    use ParameterAssertionsTrait;

    public function testGrabParameter(): void
    {
        $this->assertSame('Codeception', $this->grabParameter('app.business_name'));
        $this->assertSame('value', $this->grabParameter('app.param'));
    }
}
