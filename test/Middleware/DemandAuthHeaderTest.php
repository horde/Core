<?php
/**
 * Copyright 2016-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

namespace Horde\Core\Test\Middleware;

use Horde_Test_Case as HordeTestCase;
use Horde_Session;
use Horde_Exception;

use Horde\Core\Middleware\DemandAuthHeader;

class DemandAuthHeaderTest extends HordeTestCase
{
    use SetUpTrait;

    protected function getMiddleware()
    {
        return new DemandAuthHeader(
            $this->responseFactory
        );
    }

    public function testHeaderMissingReturns401()
    {
        $middleware = $this->getMiddleware();
        // look in DemandSessionTokenTest.php for further steps
        $this->assertTrue(true);
    }
}
