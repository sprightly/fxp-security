<?php

/*
 * This file is part of the Sonatra package.
 *
 * (c) François Pluchino <francois.pluchino@sonatra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonatra\Component\Security\Tests\Identity;

use Sonatra\Component\Security\Identity\RoleSecurityIdentity;
use Sonatra\Component\Security\Identity\SecurityIdentityInterface;
use Sonatra\Component\Security\Model\Traits\RoleableInterface;
use Sonatra\Component\Security\Model\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Role\RoleInterface;

/**
 * @author François Pluchino <francois.pluchino@sonatra.com>
 */
class RoleSecurityIdentityTest extends \PHPUnit_Framework_TestCase
{
    public function testDebugInfo()
    {
        $sid = new RoleSecurityIdentity('ROLE_TEST');

        $this->assertSame('RoleSecurityIdentity(ROLE_TEST)', (string) $sid);
    }

    public function testTypeAndIdentifier()
    {
        $identity = new RoleSecurityIdentity('identifier');

        $this->assertSame(RoleSecurityIdentity::TYPE, $identity->getType());
        $this->assertSame('identifier', $identity->getIdentifier());
    }

    public function getIdentities()
    {
        $id3 = $this->getMockBuilder(SecurityIdentityInterface::class)->getMock();
        $id3->expects($this->any())->method('getType')->willReturn(RoleSecurityIdentity::TYPE);
        $id3->expects($this->any())->method('getIdentifier')->willReturn('identifier');

        return array(
            array(new RoleSecurityIdentity('identifier'), true),
            array(new RoleSecurityIdentity('other'), false),
            array($id3, false),
        );
    }

    /**
     * @dataProvider getIdentities
     *
     * @param mixed $value  The value
     * @param bool  $result The expected result
     */
    public function testEquals($value, $result)
    {
        $identity = new RoleSecurityIdentity('identifier');

        $this->assertSame($result, $identity->equals($value));
    }

    public function testFromAccount()
    {
        /* @var RoleInterface|\PHPUnit_Framework_MockObject_MockObject $role */
        $role = $this->getMockBuilder(RoleInterface::class)->getMock();
        $role->expects($this->once())
            ->method('getRole')
            ->willReturn('ROLE_TEST');

        $sid = RoleSecurityIdentity::fromAccount($role);

        $this->assertInstanceOf(RoleSecurityIdentity::class, $sid);
        $this->assertSame(RoleSecurityIdentity::TYPE, $sid->getType());
        $this->assertSame('ROLE_TEST', $sid->getIdentifier());
    }

    public function testFormToken()
    {
        /* @var RoleInterface|\PHPUnit_Framework_MockObject_MockObject $role */
        $role = $this->getMockBuilder(RoleInterface::class)->getMock();
        $role->expects($this->once())
            ->method('getRole')
            ->willReturn('ROLE_TEST');

        /* @var RoleableInterface|\PHPUnit_Framework_MockObject_MockObject $user */
        $user = $this->getMockBuilder(RoleableInterface::class)->getMock();
        $user->expects($this->once())
            ->method('getRoles')
            ->willReturn(array($role));

        /* @var TokenInterface|\PHPUnit_Framework_MockObject_MockObject $token */
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $sids = RoleSecurityIdentity::fromToken($token);

        $this->assertCount(1, $sids);
        $this->assertInstanceOf(RoleSecurityIdentity::class, $sids[0]);
        $this->assertSame(RoleSecurityIdentity::TYPE, $sids[0]->getType());
        $this->assertSame('ROLE_TEST', $sids[0]->getIdentifier());
    }

    /**
     * @expectedException \Sonatra\Component\Security\Exception\InvalidArgumentException
     * @expectedExceptionMessage The user class must implement "Sonatra\Component\Security\Model\Traits\RoleableInterface"
     */
    public function testFormTokenWithInvalidInterface()
    {
        /* @var UserInterface|\PHPUnit_Framework_MockObject_MockObject $user */
        $user = $this->getMockBuilder(UserInterface::class)->getMock();

        /* @var TokenInterface|\PHPUnit_Framework_MockObject_MockObject $token */
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        RoleSecurityIdentity::fromToken($token);
    }
}
