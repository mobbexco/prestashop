<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit5a24f0225a4743d0d9febe4e45470acd
{
    public static $prefixLengthsPsr4 = array (
        'M' => 
        array (
            'Mobbex\\PS\\Checkout\\' => 19,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Mobbex\\PS\\Checkout\\' => 
        array (
            0 => __DIR__ . '/../..' . '/',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit5a24f0225a4743d0d9febe4e45470acd::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit5a24f0225a4743d0d9febe4e45470acd::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit5a24f0225a4743d0d9febe4e45470acd::$classMap;

        }, null, ClassLoader::class);
    }
}
