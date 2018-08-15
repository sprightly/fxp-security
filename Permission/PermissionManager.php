<?php

/*
 * This file is part of the Fxp package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Component\Security\Permission;

use Fxp\Component\DoctrineExtra\Util\ClassUtils;
use Fxp\Component\Security\Event\CheckPermissionEvent;
use Fxp\Component\Security\Event\PostLoadPermissionsEvent;
use Fxp\Component\Security\Event\PreLoadPermissionsEvent;
use Fxp\Component\Security\Exception\PermissionNotFoundException;
use Fxp\Component\Security\Identity\IdentityUtils;
use Fxp\Component\Security\Identity\RoleSecurityIdentity;
use Fxp\Component\Security\Identity\SecurityIdentityInterface;
use Fxp\Component\Security\Identity\SubjectIdentity;
use Fxp\Component\Security\Identity\SubjectIdentityInterface;
use Fxp\Component\Security\Identity\SubjectUtils;
use Fxp\Component\Security\Model\PermissionChecking;
use Fxp\Component\Security\Model\RoleInterface;
use Fxp\Component\Security\PermissionEvents;
use Fxp\Component\Security\Sharing\SharingManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Permission manager.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class PermissionManager extends AbstractPermissionManager
{
    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var PermissionProviderInterface
     */
    protected $provider;

    /**
     * @var PropertyAccessorInterface
     */
    protected $propertyAccessor;

    /**
     * @var array|null
     */
    protected $cacheConfigPermissions;

    /**
     * Constructor.
     *
     * @param EventDispatcherInterface     $dispatcher       The event dispatcher
     * @param PermissionProviderInterface  $provider         The permission provider
     * @param PropertyAccessorInterface    $propertyAccessor The property accessor
     * @param SharingManagerInterface|null $sharingManager   The sharing manager
     * @param PermissionConfigInterface[]  $configs          The permission configs
     */
    public function __construct(EventDispatcherInterface $dispatcher,
                                PermissionProviderInterface $provider,
                                PropertyAccessorInterface $propertyAccessor,
                                SharingManagerInterface $sharingManager = null,
                                array $configs = [])
    {
        parent::__construct($sharingManager, $configs);

        $this->dispatcher = $dispatcher;
        $this->provider = $provider;
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * {@inheritdoc}
     */
    protected function getMaster($subject)
    {
        if (null !== $subject) {
            $subject = SubjectUtils::getSubjectIdentity($subject);

            if ($this->hasConfig($subject->getType())) {
                $config = $this->getConfig($subject->getType());

                if (null !== $config->getMaster()) {
                    if (is_object($subject->getObject())) {
                        $value = $this->propertyAccessor->getValue($subject->getObject(), $config->getMaster());

                        if (is_object($value)) {
                            $subject = SubjectIdentity::fromObject($value);
                        }
                    } else {
                        $subject = SubjectIdentity::fromClassname($this->provider->getMasterClass($config));
                    }
                }
            }
        }

        return $subject;
    }

    /**
     * {@inheritdoc}
     */
    protected function doIsManaged(SubjectIdentityInterface $subject, $field = null)
    {
        if ($this->hasConfig($subject->getType())) {
            if (null === $field) {
                return true;
            } else {
                $config = $this->getConfig($subject->getType());

                return $config->hasField($field);
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function doIsGranted(array $sids, array $permissions, $subject = null, $field = null)
    {
        if (null !== $subject) {
            $this->preloadPermissions([$subject]);
            $this->preloadSharingRolePermissions([$subject]);
        }

        $id = $this->loadPermissions($sids);

        foreach ($permissions as $operation) {
            if (!$this->doIsGrantedPermission($id, $sids, $operation, $subject, $field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetRolePermissions(RoleInterface $role, $subject = null)
    {
        $permissions = [];
        $sid = new RoleSecurityIdentity(ClassUtils::getClass($role), $role->getRole());
        $contexts = $this->buildContexts($role);
        list($class, $field) = PermissionUtils::getClassAndField($subject, true);

        foreach ($this->provider->getPermissionsBySubject($subject, $contexts) as $permission) {
            $operation = $permission->getOperation();
            $granted = $this->isGranted([$sid], [$operation], $subject);
            $isConfig = $this->isConfigPermission($operation, $class, $field);
            $permissions[$operation] = new PermissionChecking($permission, $granted, $isConfig);
        }

        return $this->validateRolePermissions($sid, $permissions, $subject, $class, $field);
    }

    /**
     * Validate the role permissions.
     *
     * @param RoleSecurityIdentity                                  $sid         The role security identity
     * @param PermissionChecking[]                                  $permissions The permission checking
     * @param SubjectIdentityInterface|FieldVote|object|string|null $subject     The object or class name or field vote
     * @param string|null                                           $class       The class name
     * @param string|null                                           $field       The field name
     *
     * @return PermissionChecking[]
     */
    private function validateRolePermissions(RoleSecurityIdentity $sid, array $permissions, $subject = null, $class = null, $field = null)
    {
        $configOperations = $this->getConfigPermissionOperations($class, $field);

        foreach ($configOperations as $configOperation) {
            if (!isset($permissions[$configOperation])) {
                if (null !== $sp = $this->getConfigPermission($sid, $configOperation, $subject, $class, $field)) {
                    $permissions[$sp->getPermission()->getOperation()] = $sp;
                    continue;
                }

                throw new PermissionNotFoundException($configOperation, $class, $field);
            }
        }

        return array_values($permissions);
    }

    /**
     * Get the config permission.
     *
     * @param RoleSecurityIdentity                                  $sid       The role security identity
     * @param string                                                $operation The operation
     * @param SubjectIdentityInterface|FieldVote|object|string|null $subject   The object or class name or field vote
     * @param string|null                                           $class     The class name
     * @param string|null                                           $field     The field name
     *
     * @return PermissionChecking|null
     */
    private function getConfigPermission(RoleSecurityIdentity $sid, $operation, $subject = null, $class = null, $field = null)
    {
        $sps = $this->getConfigPermissions();
        $field = null !== $field ? PermissionProviderInterface::CONFIG_FIELD : null;
        $fieldAction = PermissionUtils::getMapAction($field);
        $pc = null;

        if (isset($sps[PermissionProviderInterface::CONFIG_CLASS][$fieldAction][$operation])) {
            $sp = $sps[PermissionProviderInterface::CONFIG_CLASS][$fieldAction][$operation];
            $pc = new PermissionChecking($sp, $this->isConfigGranted($sid, $operation, $subject, $class), true);
        }

        return $pc;
    }

    /**
     * Check if the config permission is granted.
     *
     * @param RoleSecurityIdentity                                  $sid       The role security identity
     * @param string                                                $operation The operation
     * @param SubjectIdentityInterface|FieldVote|object|string|null $subject   The object or class name or field vote
     * @param string|null                                           $class     The class name
     *
     * @return bool
     */
    private function isConfigGranted(RoleSecurityIdentity $sid, $operation, $subject = null, $class = null)
    {
        $granted = true;

        if (null !== $class && $this->hasConfig($class)) {
            $config = $this->getConfig($class);

            if (null !== $config->getMaster()) {
                $realOperation = $config->getMappingPermission($operation);
                $granted = $this->isGranted([$sid], [$realOperation], $subject);
            }
        }

        return $granted;
    }

    /**
     * Get the config permissions.
     *
     * @return array
     */
    private function getConfigPermissions()
    {
        if (null === $this->cacheConfigPermissions) {
            $sps = $this->provider->getConfigPermissions();
            $this->cacheConfigPermissions = [];

            foreach ($sps as $sp) {
                $classAction = PermissionUtils::getMapAction($sp->getClass());
                $fieldAction = PermissionUtils::getMapAction($sp->getField());
                $this->cacheConfigPermissions[$classAction][$fieldAction][$sp->getOperation()] = $sp;
            }
        }

        return $this->cacheConfigPermissions;
    }

    /**
     * Action to determine whether access is granted for a specific operation.
     *
     * @param string                        $id        The cache id
     * @param SecurityIdentityInterface[]   $sids      The security identities
     * @param string                        $operation The operation
     * @param SubjectIdentityInterface|null $subject   The subject
     * @param string|null                   $field     The field of subject
     *
     * @return bool
     */
    private function doIsGrantedPermission($id, array $sids, $operation, $subject = null, $field = null)
    {
        $event = new CheckPermissionEvent($sids, $this->cache[$id], $operation, $subject, $field);
        $this->dispatcher->dispatch(PermissionEvents::CHECK_PERMISSION, $event);

        if (is_bool($event->isGranted())) {
            return $event->isGranted();
        }

        $classAction = PermissionUtils::getMapAction(null !== $subject ? $subject->getType() : null);
        $fieldAction = PermissionUtils::getMapAction($field);

        return isset($this->cache[$id][$classAction][$fieldAction][$operation])
            || $this->isSharingGranted($operation, $subject, $field);
    }

    /**
     * Load the permissions of sharing roles.
     *
     * @param SubjectIdentityInterface[] $subjects The subjects
     */
    private function preloadSharingRolePermissions(array $subjects)
    {
        if (null !== $this->sharingManager) {
            $this->sharingManager->preloadRolePermissions($subjects);
        }
    }

    /**
     * Load the permissions of roles and returns the id of cache.
     *
     * @param SecurityIdentityInterface[] $sids The security identities
     *
     * @return string
     */
    private function loadPermissions(array $sids)
    {
        $roles = IdentityUtils::filterRolesIdentities($sids);
        $id = implode('|', $roles);

        if (!array_key_exists($id, $this->cache)) {
            $this->cache[$id] = [];
            $preEvent = new PreLoadPermissionsEvent($sids, $roles);
            $this->dispatcher->dispatch(PermissionEvents::PRE_LOAD, $preEvent);
            $perms = $this->provider->getPermissions($roles);

            $this->buildSystemPermissions($id);

            foreach ($perms as $perm) {
                $class = PermissionUtils::getMapAction($perm->getClass());
                $field = PermissionUtils::getMapAction($perm->getField());
                $this->cache[$id][$class][$field][$perm->getOperation()] = true;
            }

            $postEvent = new PostLoadPermissionsEvent($sids, $roles, $this->cache[$id]);
            $this->dispatcher->dispatch(PermissionEvents::POST_LOAD, $postEvent);
            $this->cache[$id] = $postEvent->getPermissionMap();
        }

        return $id;
    }

    /**
     * Check if the permission operation is defined by the config.
     *
     * @param string      $operation The permission operation
     * @param string|null $class     The class name
     * @param string|null $field     The field
     *
     * @return bool
     */
    private function isConfigPermission($operation, $class = null, $field = null)
    {
        $map = $this->getMapConfigPermissions();
        $class = PermissionUtils::getMapAction($class);
        $field = PermissionUtils::getMapAction($field);

        return isset($map[$class][$field][$operation]);
    }

    /**
     * Get the config operations of the subject.
     *
     * @param string|null $class The class name
     * @param string|null $field The field
     *
     * @return string[]
     */
    private function getConfigPermissionOperations($class = null, $field = null)
    {
        $map = $this->getMapConfigPermissions();
        $class = PermissionUtils::getMapAction($class);
        $field = PermissionUtils::getMapAction($field);
        $operations = [];

        if (isset($map[$class][$field])) {
            $operations = array_keys($map[$class][$field]);
        }

        return $operations;
    }

    /**
     * Get the map of the config permissions.
     *
     * @return array
     */
    private function getMapConfigPermissions()
    {
        $id = '_config';

        if (!array_key_exists($id, $this->cache)) {
            $this->cache[$id] = [];
            $this->buildSystemPermissions($id);
        }

        return $this->cache[$id];
    }

    /**
     * Build the system permissions.
     *
     * @param string $id The cache id
     */
    private function buildSystemPermissions($id)
    {
        foreach ($this->configs as $config) {
            foreach ($config->getOperations() as $operation) {
                $field = PermissionUtils::getMapAction(null);
                $this->cache[$id][$config->getType()][$field][$operation] = true;
            }

            foreach ($config->getFields() as $fieldConfig) {
                foreach ($fieldConfig->getOperations() as $operation) {
                    $this->cache[$id][$config->getType()][$fieldConfig->getField()][$operation] = true;
                }
            }
        }
    }

    /**
     * Check if the access is granted by a sharing entry.
     *
     * @param string                        $operation The operation
     * @param SubjectIdentityInterface|null $subject   The subject
     * @param string|null                   $field     The field of subject
     *
     * @return bool
     */
    private function isSharingGranted($operation, $subject = null, $field = null)
    {
        return null !== $this->sharingManager
            ? $this->sharingManager->isGranted($operation, $subject, $field)
            : false;
    }
}
