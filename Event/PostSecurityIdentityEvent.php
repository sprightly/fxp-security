<?php

/*
 * This file is part of the Sonatra package.
 *
 * (c) François Pluchino <francois.pluchino@sonatra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonatra\Component\Security\Event;

use Sonatra\Component\Security\Event\Traits\SecurityIdentityEventTrait;
use Sonatra\Component\Security\Identity\SecurityIdentityInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The post security identity retrieval strategy event.
 *
 * @author François Pluchino <francois.pluchino@sonatra.com>
 */
class PostSecurityIdentityEvent extends AbstractSecurityEvent
{
    use SecurityIdentityEventTrait;

    /**
     * Constructor.
     *
     * @param TokenInterface              $token              The token
     * @param SecurityIdentityInterface[] $securityIdentities The security identities
     * @param bool                        $permissionEnabled  Check if the permission manager is enabled
     */
    public function __construct(TokenInterface $token, array $securityIdentities = array(), $permissionEnabled = true)
    {
        $this->token = $token;
        $this->securityIdentities = $securityIdentities;
        $this->permissionEnabled = $permissionEnabled;
    }
}