<?php

namespace Hasty;

use Doctrine\Common\Cache\MemcachedCache;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;


class Resource
{

    private static $entityManager;

    /**
     * @return EntityManager
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getEntityManager()
    {
        if (static::$entityManager) return static::$entityManager;

        $memcache = new \Memcached();
        $memcache->addServer('localhost', 11211);

        $cacheDriver = new MemcachedCache();
        $cacheDriver->setMemcached($memcache);

        $devel = true;
        $config = Setup::createAnnotationMetadataConfiguration(
            array(ROOT . \DS . 'app' . \DS . 'Model' . \DS . 'Entity'),
            $devel, null, $cacheDriver);

        if(Config::get('debug'))
            $config->setSQLLogger(new Doctrine\Logger());

        static::$entityManager = EntityManager::create(
            Config::get('dbconn'), $config);
        return static::$entityManager;
    }
}
