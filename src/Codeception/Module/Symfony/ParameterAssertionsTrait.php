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
     * @param string $name
     * @return array|bool|float|int|string|null
     */
    public function grabParameter(string $name)
    {
        $parameterBag = $this->grabParameterBagService();
        return $parameterBag->get($name);
    }

    protected function grabParameterBagService(): ParameterBagInterface
    {
        return $this->grabService('parameter_bag');
    }
}
