<?php

/*
 * This file is part of the Sonatra package.
 *
 * (c) François Pluchino <francois.pluchino@sonatra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonatra\Component\Security\Model\Traits;

use Sonatra\Component\Security\Model\OrganizationInterface;

/**
 * Interface to indicate that the model is linked with a required organization.
 *
 * @author François Pluchino <francois.pluchino@sonatra.com>
 */
interface OrganizationalRequiredInterface extends OrganizationalInterface
{
    /**
     * Set the organization.
     *
     * @param OrganizationInterface $organization The organization
     *
     * @return self
     */
    public function setOrganization(OrganizationInterface $organization);
}
