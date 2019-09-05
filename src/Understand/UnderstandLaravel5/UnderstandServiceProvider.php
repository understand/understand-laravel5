<?php namespace Understand\UnderstandLaravel5;

use Illuminate\Support\ServiceProvider;

abstract class UnderstandServiceProvider extends ServiceProvider
{
    /**
     * Register config
     *
     * @return void
     */
    protected function registerConfig()
    {
        $configPath = __DIR__ . '/../../config/understand-laravel.php';
        $this->mergeConfigFrom($configPath, 'understand-laravel');
    }

    /**
     * Register token generator class
     *
     * @return void
     */
    protected function registerTokenProvider()
    {
        $this->app->singleton('understand.tokenProvider', function ()
        {
            return new TokenProvider();
        });
    }

    /**
     * Register data collector class
     *
     * @return void
     */
    protected function registerDataCollector()
    {
        $this->app->singleton('understand.dataCollector', function ()
        {
            return new DataCollector();
        });
    }

    /**
     * Register exception encoder
     *
     * @return void
     */
    protected function registerExceptionEncoder()
    {
        $this->app->bind('understand.exceptionEncoder', function ()
        {
            return new ExceptionEncoder;
        });
    }

    /**
     * Register exception and event logger
     *
     * @return void
     */
    protected function registerEventLoggers()
    {
        $this->app->bind('understand.eventLogger', function($app)
        {
            return new EventLogger($app['understand.logger'], $app['config']);
        });

        $this->app->bind('understand.exceptionLogger', function($app)
        {
            return new ExceptionLogger($app['understand.logger'], $app['understand.exceptionEncoder'], $app['config']);
        });
    }

    /**
     * Register understand logger
     *
     * @return void
     */
    protected function registerLogger()
    {
        $this->app->singleton('understand.logger', function($app)
        {
            $fieldProvider = $app['understand.fieldProvider'];
            $handler = $this->resolveHandler($app);

            return new Logger($fieldProvider, $handler);
        });
    }

    /**
     * Return default handler
     *
     * @param type $app
     * @return mixed
     * @throws \ErrorException
     */
    protected function resolveHandler($app)
    {
        $inputToken = $app['config']->get('understand-laravel.token');

        $apiUrl = $app['config']->get('understand-laravel.url', 'https://api.understand.io');
        $handlerType = $app['config']->get('understand-laravel.handler');
        $sslBundlePath = $app['config']->get('understand-laravel.ssl_ca_bundle');

        if ($handlerType == 'async')
        {
            return new Handlers\AsyncHandler($inputToken, $apiUrl, $sslBundlePath);
        }

        if ($handlerType == 'sync')
        {
            return new Handlers\SyncHandler($inputToken, $apiUrl, $sslBundlePath);
        }

        throw new \ErrorException('understand-laravel handler misconfiguration:' . $handlerType);
    }

    /**
     * @param $level
     * @param $message
     * @param $context
     * @return bool
     */
    protected function shouldIgnoreEvent($level, $message, $context)
    {
        $ignoredEventTypes = (array)$this->app['config']->get('understand-laravel.ignored_logs');

        if (!$ignoredEventTypes) {
            return false;
        }

        return in_array($level, $ignoredEventTypes, true);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'understand.fieldProvider',
            'understand.logger',
            'understand.exceptionEncoder',
            'understand.exceptionLogger',
            'understand.eventLogger',
            'understand.tokenProvider',
            'understand.dataCollector',
        ];
    }
}