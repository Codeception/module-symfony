<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use UnitEnum;

trait ParameterAssertionsTrait
{
    /**
     * Grabs a Symfony parameter
     *
     * ```php
     * <?php
     * $I->grabParameter('app.business_name');
     * ```
     * This only works for explicitly set parameters (just using `bind` for Symfony's dependency injection is not enough).
     */
    public function grabParameter(string $parameterName): array|bool|string|int|float|UnitEnum|null
    {
        $parameterBag = $this->grabParameterBagService();
        return $parameterBag->get($parameterName);
    }

    protected function grabParameterBagService(): ParameterBagInterface
    {
        return $this->grabService('parameter_bag');
    }
}
