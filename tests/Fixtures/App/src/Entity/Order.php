<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Entity used by the Doctrine integration test: excluded from the service registration, like entities usually are
 */
#[ORM\Entity]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $id = 0;

    public function total(): int
    {
        return 42;
    }
}
