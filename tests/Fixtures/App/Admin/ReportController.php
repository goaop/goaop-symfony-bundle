<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Admin;

use GeneratedCode\Admin\BaseReportController;

/**
 * Application class extending a class generated at runtime, which Composer can not locate
 *
 * @see \Go\Symfony\GoAopBundle\Tests\Fixtures\App\BaseClassGenerator
 */
final class ReportController extends BaseReportController
{
    public function render(string $name): string
    {
        return 'Report ' . $name . ' of ' . $this->title();
    }
}
