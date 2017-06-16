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

use PHPUnit\Framework\TestCase;
use Sonatra\Component\Security\Tests\Fixtures\Model\MockOrganization;
use Sonatra\Component\Security\Tests\Fixtures\Model\MockOrganizationUserRoleableGroupable;
use Sonatra\Component\Security\Tests\Fixtures\Model\MockRoleable;
use Sonatra\Component\Security\Tests\Fixtures\Model\MockUserRoleable;

/**
 * @author François Pluchino <francois.pluchino@sonatra.com>
 */
class RoleableTraitTest extends TestCase
{
    public function testModel()
    {
        $roleable = new MockRoleable();

        $this->assertFalse($roleable->hasRole('ROLE_TEST'));

        $roleable->setRoles(array(
            'ROLE_TEST',
            'ROLE_USER',
            'ROLE_ORGANIZATION_USER',
        ));

        $this->assertTrue($roleable->hasRole('ROLE_TEST'));
        $this->assertFalse($roleable->hasRole('ROLE_USER')); // Skip the ROLE_USER role
        $this->assertFalse($roleable->hasRole('ROLE_ORGANIZATION_USER')); // Skip the ROLE_ORGANIZATION_USER role

        $this->assertEquals(array('ROLE_TEST'), $roleable->getRoles());

        $roleable->removeRole('ROLE_TEST');
        $this->assertFalse($roleable->hasRole('ROLE_TEST'));
    }

    public function testUserModel()
    {
        $roleable = new MockUserRoleable();

        $this->assertEquals(array('ROLE_USER'), $roleable->getRoles());

        $roleable->addRole('ROLE_TEST');

        $validRoles = array(
            'ROLE_TEST',
            'ROLE_USER',
        );
        $this->assertEquals($validRoles, $roleable->getRoles());
    }

    public function testOrganizationUserModel()
    {
        $org = new MockOrganization('foo');
        $user = new MockUserRoleable();
        $roleable = new MockOrganizationUserRoleableGroupable($org, $user);

        $this->assertEquals(array('ROLE_ORGANIZATION_USER'), $roleable->getRoles());

        $roleable->addRole('ROLE_TEST');

        $validRoles = array(
            'ROLE_TEST',
            'ROLE_ORGANIZATION_USER',
        );
        $this->assertEquals($validRoles, $roleable->getRoles());
    }
}
