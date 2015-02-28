<?php

namespace Spear\Silex\Provider;

use Silex\ServiceProviderInterface;
use Puzzle\Configuration;
use Silex\Application;

class AsseticServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $this->validatePuzzleConfiguration($app);

        $app->register(new \SilexAssetic\AsseticServiceProvider(), array(
            'assetic.path_to_web' => $this->removeEndingSlash($app['documentRoot.path']),
            'assetic.options' => array(
                'debug' => $app['configuration']->read('assetic/debug', false),
                'auto_dump_assets' => $app['configuration']->read('assetic/autoDump', false),
            ),
        ));
    }

    public function boot(Application $app)
    {
        ;
    }

    private function validatePuzzleConfiguration(Application $app)
    {
        if(! isset($app['configuration']) || ! $app['configuration'] instanceof Configuration)
        {
            throw new \LogicException('AsseticProvider requires an instance of puzzle/configuration for the key configuration to be defined.');
        }
    }

    private function removeEndingSlash($path)
    {
        return rtrim($path, DIRECTORY_SEPARATOR);
    }
}
