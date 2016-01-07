<?php

namespace Hasty;

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

class Resource
{

    private static $entityManager;

    /**
     * @param $modelDir
     * @return EntityManager
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getEntityManager()
    {
        if (!static::$entityManager) {
            $devel = true;
            $config = Setup::createAnnotationMetadataConfiguration(
                array(ROOT . \DS . 'app' . \DS . 'Model' . \DS . 'Entity'), $devel);
            $conn = array(
                'driver' => 'pdo_sqlite',
                'path' => '/tmp/db.sqlite3'
            );

            static::$entityManager = EntityManager::create($conn, $config);
        }
        return static::$entityManager;
    }
}
