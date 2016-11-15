<?php

/*
 * This file is part of the Sonatra package.
 *
 * (c) François Pluchino <francois.pluchino@sonatra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonatra\Component\Security\Doctrine;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use Sonatra\Component\Security\Exception\RuntimeException;

/**
 * Utils for doctrine ORM.
 *
 * @author François Pluchino <francois.pluchino@sonatra.com>
 */
abstract class DoctrineUtils
{
    /**
     * @var array
     */
    private static $cacheIdentifiers = array();

    /**
     * @var array
     */
    private static $cacheZeroIds = array();

    /**
     * @var array
     */
    private static $cacheCastIdentifiers = array();

    /**
     * Clear the caches.
     */
    public static function clearCaches()
    {
        self::$cacheIdentifiers = array();
        self::$cacheZeroIds = array();
        self::$cacheCastIdentifiers = array();
    }

    /**
     * Get the identifier of entity.
     *
     * @param ClassMetadata $targetEntity The target entity
     *
     * @return string
     */
    public static function getIdentifier(ClassMetadata $targetEntity)
    {
        if (!isset(self::$cacheIdentifiers[$targetEntity->getName()])) {
            $identifier = $targetEntity->getIdentifierFieldNames();
            self::$cacheIdentifiers[$targetEntity->getName()] = 0 < count($identifier)
                ? $identifier[0]
                : 'id';
        }

        return self::$cacheIdentifiers[$targetEntity->getName()];
    }

    /**
     * Get the mock id for entity identifier.
     *
     * @param ClassMetadata $targetEntity The target entity
     *
     * @return int|string|null
     */
    public static function getMockZeroId(ClassMetadata $targetEntity)
    {
        if (!isset(self::$cacheZeroIds[$targetEntity->getName()])) {
            $type = self::getIdentifierType($targetEntity);

            switch (true) {
                case $type instanceof GuidType:
                    $value = '00000000-0000-0000-0000-000000000000';
                    break;
                case $type instanceof IntegerType:
                case $type instanceof SmallIntType:
                case $type instanceof BigIntType:
                case $type instanceof DecimalType:
                case $type instanceof FloatType:
                    $value = 0;
                    break;
                case $type instanceof StringType:
                case $type instanceof TextType:
                    $value = '';
                    break;
                default:
                    $value = null;
                    break;
            }

            self::$cacheZeroIds[$targetEntity->getName()] = $value;
        }

        return self::$cacheZeroIds[$targetEntity->getName()];
    }

    /**
     * Cast the identifier.
     *
     * @param ClassMetadata $targetEntity The target entity
     * @param Connection    $connection   The doctrine connection
     *
     * @return string
     */
    public static function castIdentifier(ClassMetadata $targetEntity, Connection $connection)
    {
        if (!isset(self::$cacheCastIdentifiers[$targetEntity->getName()])) {
            $cast = '';

            if ('postgresql' === $connection->getDatabasePlatform()->getName()) {
                $type = self::getIdentifierType($targetEntity);
                $cast = '::'.$type->getSQLDeclaration($targetEntity->getIdentifierFieldNames(),
                                                      $connection->getDatabasePlatform());
            }

            self::$cacheCastIdentifiers[$targetEntity->getName()] = $cast;
        }

        return self::$cacheCastIdentifiers[$targetEntity->getName()];
    }

    /**
     * Get the dbal identifier type.
     *
     * @param ClassMetadata $targetEntity The target entity
     *
     * @return Type
     *
     * @throws RuntimeException When the doctrine dbal type is not found
     */
    public static function getIdentifierType(ClassMetadata $targetEntity)
    {
        $identifier = self::getIdentifier($targetEntity);
        $type = $targetEntity->getTypeOfField($identifier);

        if ($type instanceof Type) {
            return $type;
        }

        if (is_string($type)) {
            return Type::getType($type);
        }

        $msg = 'The Doctrine DBAL type is not found for "%s::%s" identifier';
        throw new RuntimeException(sprintf($msg, $targetEntity->getName(), $identifier));
    }
}