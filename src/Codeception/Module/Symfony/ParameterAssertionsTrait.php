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
     * @return mixed|null
     */
    public function grabParameter(string $name)
    {
        /** @var ParameterBagInterface $parameterBag */
        $parameterBag = $this->grabService('parameter_bag');
        return $parameterBag->get($name);
    }
}