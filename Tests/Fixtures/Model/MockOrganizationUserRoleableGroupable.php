<?php

/*
 * This file is part of the Sonatra package.
 *
 * (c) François Pluchino <francois.pluchino@sonatra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonatra\Component\Security\Tests\Fixtures\Model;

use Sonatra\Component\Security\Model\GroupInterface;
use Sonatra\Component\Security\Model\Traits\GroupableInterface;
use Sonatra\Component\Security\Model\Traits\RoleableInterface;
use Sonatra\Component\Security\Model\Traits\RoleableTrait;

/**
 * @author François Pluchino <francois.pluchino@sonatra.com>
 */
class MockOrganizationUserRoleableGroupable extends MockOrganizationUser implements RoleableInterface, GroupableInterface
{
    use RoleableTrait;

    /**
     * @var array
     */
    protected $groups = array();

    /**
     * Add a group.
     *
     * @param GroupInterface $group The group
     */
    public function addGroup(GroupInterface $group)
    {
        $this->groups[$group->getName()] = $group;
    }

    /**
     * {@inheritdoc}
     */
    public function hasGroup($name)
    {
        return isset($this->groups[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getGroups()
    {
        return $this->groups;
    }
}