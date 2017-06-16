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

use PHPUnit\Framework\TestCase;
use Sonatra\Component\Security\Event\PostSecurityIdentityEvent;
use Sonatra\Component\Security\Identity\SecurityIdentityInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * @author François Pluchino <francois.pluchino@sonatra.com>
 */
class PostSecurityIdentityEventTest extends TestCase
{
    public function testEvent()
    {
        /* @var TokenInterface $token */
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $sids = array(
            $this->getMockBuilder(SecurityIdentityInterface::class)->getMock(),
        );

        $event = new PostSecurityIdentityEvent($token, $sids);

        $this->assertSame($token, $event->getToken());
        $this->assertSame($sids, $event->getSecurityIdentities());
        $this->assertTrue($event->isPermissionEnabled());
    }
}
