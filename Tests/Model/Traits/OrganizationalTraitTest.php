<?php

/*
 * This file is part of the Sonatra package.
 *
 * (c) François Pluchino <francois.pluchino@sonatra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonatra\Component\Security\Tests\Model\Traits;

use Sonatra\Component\Security\Model\OrganizationInterface;
use Sonatra\Component\Security\Tests\Fixtures\Model\MockUserOrganizationUsers;

/**
 * @author François Pluchino <francois.pluchino@sonatra.com>
 */
class OrganizationalTraitTest extends \PHPUnit_Framework_TestCase
{
    public function testModel()
    {
        /* @var OrganizationInterface|\PHPUnit_Framework_MockObject_MockObject $org */
        $org = $this->getMockBuilder(OrganizationInterface::class)->getMock();
        $org->expects($this->once())
            ->method('getId')
            ->willReturn(42);

        $user = new MockUserOrganizationUsers();

        $this->assertNull($user->getOrganization());

        $user->setOrganization($org);
        $this->assertSame($org, $user->getOrganization());
        $this->assertSame(42, $user->getOrganizationId());
    }
}
