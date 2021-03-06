<?php

/*
 * This file is part of the Fxp package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Component\Security\Doctrine\ORM\Listener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Fxp\Component\DoctrineExtra\Util\ClassUtils;
use Fxp\Component\Security\Exception\SecurityException;
use Fxp\Component\Security\Identity\SubjectIdentity;
use Fxp\Component\Security\Sharing\SharingManagerInterface;

/**
 * Doctrine ORM listener for sharing filter.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class SharingDeleteListener implements EventSubscriber
{
    /**
     * @var string
     */
    protected $sharingClass;

    /**
     * @var SharingManagerInterface
     */
    protected $sharingManager;

    /**
     * @var array
     */
    protected $deleteSharingSubjects = [];

    /**
     * @var array
     */
    protected $deleteSharingIdentities = [];

    /**
     * @var bool
     */
    protected $initialized = false;

    /**
     * Constructor.
     *
     * @param string $sharingClass The classname of sharing model
     */
    public function __construct($sharingClass)
    {
        $this->sharingClass = $sharingClass;
    }

    /**
     * Specifies the list of listened events.
     *
     * @return string[]
     */
    public function getSubscribedEvents()
    {
        return [
            Events::onFlush,
            Events::postFlush,
        ];
    }

    /**
     * On flush action.
     *
     * @param OnFlushEventArgs $args The event
     */
    public function onFlush(OnFlushEventArgs $args)
    {
        $uow = $args->getEntityManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $class = ClassUtils::getClass($entity);

            if ($this->getSharingManager()->hasSubjectConfig($class)) {
                $subject = SubjectIdentity::fromObject($entity);
                $this->deleteSharingSubjects[$subject->getType()][] = $subject->getIdentifier();
            } elseif ($this->getSharingManager()->hasIdentityConfig($class)) {
                $subject = SubjectIdentity::fromObject($entity);
                $this->deleteSharingIdentities[$subject->getType()][] = $subject->getIdentifier();
            }
        }
    }

    /**
     * Post flush action.
     *
     * @param PostFlushEventArgs $args The event
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (!empty($this->deleteSharingSubjects) || !empty($this->deleteSharingIdentities)) {
            $this->buildDeleteQuery($args->getEntityManager())->execute();
        }

        $this->deleteSharingSubjects = [];
        $this->deleteSharingIdentities = [];
    }

    /**
     * Set the sharing manager.
     *
     * @param SharingManagerInterface $sharingManager The sharing manager
     *
     * @return self
     */
    public function setSharingManager(SharingManagerInterface $sharingManager)
    {
        $this->sharingManager = $sharingManager;

        return $this;
    }

    /**
     * Get the sharing manager.
     *
     * @return SharingManagerInterface
     */
    public function getSharingManager()
    {
        $this->init();

        return $this->sharingManager;
    }

    /**
     * Init listener.
     */
    protected function init()
    {
        if (!$this->initialized) {
            $msg = 'The "%s()" method must be called before the init of the "%s" class';

            if (null === $this->sharingManager) {
                throw new SecurityException(sprintf($msg, 'setSharingManager', \get_class($this)));
            }

            $this->initialized = true;
        }
    }

    /**
     * Build the delete query.
     *
     * @param EntityManagerInterface $em The entity manager
     *
     * @return Query
     */
    private function buildDeleteQuery(EntityManagerInterface $em)
    {
        $qb = $em->createQueryBuilder()
            ->delete($this->sharingClass, 's');

        $this->buildCriteria($qb, $this->deleteSharingSubjects, 'subjectClass', 'subjectId');
        $this->buildCriteria($qb, $this->deleteSharingIdentities, 'identityClass', 'identityName');

        return $qb->getQuery();
    }

    /**
     * Build the query criteria.
     *
     * @param QueryBuilder $qb         The query builder
     * @param array        $mapIds     The map of classname and identifiers
     * @param string       $fieldClass The name of field class
     * @param string       $fieldId    The name of field identifier
     */
    private function buildCriteria(QueryBuilder $qb, array $mapIds, $fieldClass, $fieldId)
    {
        if (!empty($mapIds)) {
            $where = '';
            $parameters = [];
            $i = 0;

            foreach ($mapIds as $type => $identifiers) {
                $pClass = $fieldClass.'_'.$i;
                $pIdentifiers = $fieldId.'s_'.$i;
                $parameters[$pClass] = $type;
                $parameters[$pIdentifiers] = $identifiers;
                $where .= '' === $where ? '' : ' OR ';
                $where .= sprintf('(s.%s = :%s AND s.%s IN (:%s))', $fieldClass, $pClass, $fieldId, $pIdentifiers);
                ++$i;
            }

            $qb->andWhere($where);

            foreach ($parameters as $key => $identifiers) {
                $qb->setParameter($key, $identifiers);
            }
        }
    }
}
