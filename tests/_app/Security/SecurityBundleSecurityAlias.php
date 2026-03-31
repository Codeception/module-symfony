<?php

declare(strict_types=1);

if (!class_exists(\Symfony\Bundle\SecurityBundle\Security::class) && class_exists(\Symfony\Component\Security\Core\Security::class)) {
    class_alias(\Symfony\Component\Security\Core\Security::class, \Symfony\Bundle\SecurityBundle\Security::class);
}
