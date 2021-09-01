<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import('../config/{packages}/*.yaml');
        $container->import('../config/{packages}/'.$this->environment.'/*.yaml');

        if (is_file(\dirname(__DIR__).'/config/services.yaml')) {
            $container->import('../config/services.yaml');
            $container->import('../config/{services}_'.$this->environment.'.yaml');
        } elseif (is_file($path = \dirname(__DIR__).'/config/services.php')) {
            (require $path)($container->withPath($path), $this);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }

    public function getHomeDirectory($directory)
    {
        $home = isset($_SERVER['HOME']) ? $_SERVER['HOME'] : "/tmp/fteam";
        $path = realpath($home).'/.fteam';
        return sprintf("%s/%s/%s/%s", $path, $directory, $this->environment, md5(__DIR__));
    }

    public function getCacheDir()
    {
        return $this->getHomeDirectory(".cache");
    }
    
    public function getLogDir()
    {
        return $this->getHomeDirectory(".log");
    }
    
    public function getProjectDir()
    {
        return __DIR__.'/../';
    }
}
