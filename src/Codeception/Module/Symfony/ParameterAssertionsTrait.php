<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

trait ParameterAssertionsTrait
{
    /**
     * Grabs a Symfony parameter
     *
     * ```php
     * <?php
     * $I->grabParameter('app.business_name');
     * ```
     *
     * @param string $parameterName
     * @return array|bool|float|int|string|null
     */
    public function grabParameter(string $parameterName)
    {
        $parameterBag = $this->grabParameterBagService();
        return $parameterBag->get($parameterName);
    }

    protected function grabParameterBagService(): ParameterBagInterface
    {
        return $this->grabService('parameter_bag');
    }
}
