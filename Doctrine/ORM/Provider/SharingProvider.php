<?php

/*
 * This file is part of the Sonatra package.
 *
 * (c) François Pluchino <francois.pluchino@sonatra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonatra\Component\Security\Doctrine\ORM\Provider;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Sonatra\Component\Security\Exception\InvalidArgumentException;
use Sonatra\Component\Security\Identity\IdentityUtils;
use Sonatra\Component\Security\Identity\SecurityIdentityInterface;
use Sonatra\Component\Security\Identity\SecurityIdentityManagerInterface;
use Sonatra\Component\Security\Identity\SubjectIdentityInterface;
use Sonatra\Component\Security\Sharing\SharingManagerInterface;
use Sonatra\Component\Security\Sharing\SharingProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The Doctrine Orm Sharing Provider.
 *
 * @author François Pluchino <francois.pluchino@sonatra.com>
 */
class SharingProvider extends AbstractProvider implements SharingProviderInterface
{
    /**
     * @var EntityRepository
     */
    protected $sharingRepo;

    /**
     * @var SharingManagerInterface
     */
    protected $sharingManager;

    /**
     * @var SecurityIdentityManagerInterface
     */
    protected $sidManager;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * Constructor.
     *
     * @param EntityRepository                 $roleRepository    The role repository
     * @param EntityRepository                 $sharingRepository The sharing repository
     * @param SecurityIdentityManagerInterface $sidManager        The security identity manager
     * @param TokenStorageInterface            $tokenStorage      The token storage
     */
    public function __construct(EntityRepository $roleRepository,
                                EntityRepository $sharingRepository,
                                SecurityIdentityManagerInterface $sidManager,
                                TokenStorageInterface $tokenStorage)
    {
        parent::__construct($roleRepository);

        $this->sharingRepo = $sharingRepository;
        $this->sidManager = $sidManager;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function setSharingManager(SharingManagerInterface $sharingManager)
    {
        $this->sharingManager = $sharingManager;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissionRoles(array $roles)
    {
        if (empty($roles)) {
            return array();
        }

        $qb = $this->roleRepo->createQueryBuilder('r')
            ->addSelect('p')
            ->leftJoin('r.permissions', 'p');

        $pRoles = $this->addWhere($qb, $roles)
            ->orderBy('p.class', 'asc')
            ->addOrderBy('p.field', 'asc')
            ->addOrderBy('p.operation', 'asc')
            ->getQuery()
            ->getResult();

        $this->roleRepo->clear();

        return $pRoles;
    }

    /**
     * {@inheritdoc}
     */
    public function getSharingEntries(array $subjects, $sids = null)
    {
        if (empty($subjects) || null === $this->sharingRepo) {
            return array();
        }

        $now = new \DateTime('now');
        $now->setTimezone(new \DateTimeZone('UTC'));
        $sids = $this->getSecurityIdentities($sids);
        $qb = $this->sharingRepo->createQueryBuilder('s')
            ->addSelect('p')
            ->leftJoin('s.permissions', 'p');

        $sharingEntries = $this->addWhereForSharing($qb, $subjects, $sids)
            ->andWhere('s.enabled = TRUE AND (s.startedAt IS NULL OR s.startedAt <= :now) AND (s.endedAt IS NULL OR s.endedAt >= :now)')
            ->setParameter('now', $now, Type::DATETIME)
            ->orderBy('p.class', 'asc')
            ->addOrderBy('p.field', 'asc')
            ->addOrderBy('p.operation', 'asc')
            ->getQuery()
            ->getResult();

        $this->sharingRepo->clear();

        return $sharingEntries;
    }

    /**
     * Add where condition for sharing.
     *
     * @param QueryBuilder                $qb       The query builder
     * @param SubjectIdentityInterface[]  $subjects The subjects
     * @param SecurityIdentityInterface[] $sids     The security identities
     *
     * @return QueryBuilder
     */
    private function addWhereForSharing(QueryBuilder $qb, array $subjects, array $sids)
    {
        $where = '';
        $parameters = array();

        foreach ($subjects as $i => $subject) {
            $class = 'subject'.$i.'_class';
            $id = 'subject'.$i.'_id';
            $parameters[$class] = $subject->getType();
            $parameters[$id] = $subject->getIdentifier();
            $where .= '' === $where ? '' : ' OR ';
            $where .= sprintf('(s.subjectClass = :%s AND s.subjectId = :%s)', $class, $id);
        }

        $qb->where($where);

        foreach ($parameters as $key => $value) {
            $qb->setParameter($key, $value);
        }

        return $this->addWhereSecurityIdentitiesForSharing($qb, $sids);
    }

    /**
     * Add security identities where condition for sharing.
     *
     * @param QueryBuilder                $qb   The query builder
     * @param SecurityIdentityInterface[] $sids The security identities
     *
     * @return QueryBuilder
     */
    private function addWhereSecurityIdentitiesForSharing(QueryBuilder $qb, array $sids)
    {
        if (!empty($sids)) {
            $where = '';
            $parameters = array();
            $groupSids = $this->groupSecurityIdentities($sids);
            $i = 0;

            foreach ($groupSids as $type => $identifiers) {
                $qClass = 'sid'.$i.'_class';
                $qIdentifiers = 'sid'.$i.'_ids';
                $parameters[$qClass] = $type;
                $parameters[$qIdentifiers] = $identifiers;
                $where .= '' === $where ? '' : ' OR ';
                $where .= sprintf('(s.identityClass = :%s AND s.identityName IN (:%s))', $qClass, $qIdentifiers);
                ++$i;
            }

            $qb->andWhere($where);

            foreach ($parameters as $key => $identifiers) {
                $qb->setParameter($key, $identifiers);
            }
        }

        return $qb;
    }

    /**
     * Group the security identities definition.
     *
     * @param SecurityIdentityInterface[] $sids The security identities
     *
     * @return array
     */
    private function groupSecurityIdentities(array $sids)
    {
        $groupSids = array();

        if (null === $this->sharingManager) {
            throw new InvalidArgumentException('The "setSharingManager()" must be called before');
        }

        foreach ($sids as $sid) {
            if (IdentityUtils::isValid($sid)) {
                $type = $this->sharingManager->getIdentityConfig($sid->getType())->getType();
                $groupSids[$type][] = $sid->getIdentifier();
            }
        }

        return $groupSids;
    }

    /**
     * Get the security identities.
     *
     * @param SecurityIdentityInterface[]|null $sids The security identities to filter the sharing entries
     *
     * @return SecurityIdentityInterface[]
     */
    private function getSecurityIdentities($sids = null)
    {
        if (null === $sids) {
            $sids = $this->sidManager->getSecurityIdentities($this->tokenStorage->getToken());
        }

        return null !== $sids
            ? $sids
            : array();
    }
}
