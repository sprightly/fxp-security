<?php

/*
 * This file is part of the Sonatra package.
 *
 * (c) François Pluchino <francois.pluchino@sonatra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonatra\Component\Security\Tests\Event;

use Sonatra\Component\Security\Event\PostLoadPermissionsEvent;
use Sonatra\Component\Security\Identity\RoleSecurityIdentity;
use Sonatra\Component\Security\Identity\SecurityIdentityInterface;

/**
 * @author François Pluchino <francois.pluchino@sonatra.com>
 */
class PostLoadPermissionsEventTest extends \PHPUnit_Framework_TestCase
{
    public function testEvent()
    {
        $sids = array(
            $this->getMockBuilder(SecurityIdentityInterface::class)->getMock(),
            new RoleSecurityIdentity('ROLE_USER'),
        );
        $roles = array(
            'ROLE_USER',
        );
        $permissionMap = array();

        $event = new PostLoadPermissionsEvent($sids, $roles, $permissionMap);

        $this->assertSame($sids, $event->getSecurityIdentities());
        $this->assertSame($roles, $event->getRoles());
        $this->assertSame($permissionMap, $event->getPermissionMap());

        $permissionMap2 = array(
            '_global' => array(
                'test' => true,
            ),
        );
        $event->setPermissionMap($permissionMap2);

        $this->assertSame($permissionMap2, $event->getPermissionMap());
    }
}