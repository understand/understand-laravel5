<?php

namespace Understand\UnderstandLaravel5;

use Exception;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Str;
use Understand\UnderstandLaravel5\Handlers\MonologHandler;
use UnderstandMonolog\Handler\UnderstandAsyncHandler;
use UnderstandMonolog\Handler\UnderstandSyncHandler;

class UnderstandLumenServiceProvider extends UnderstandServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->configure('understand-laravel');

        $enabled = $this->app['config']->get('understand-laravel.enabled');

        if ($enabled)
        {
            $this->listenLumenEvents();
        }

        if ($enabled && $this->app['config']->get('understand-laravel.sql_enabled'))
        {
            $this->listenQueryEvents();
        }
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->registerConfig();
        $this->registerFieldProvider();
        $this->registerDataCollector();
        $this->registerTokenProvider();
        $this->registerLogger();
        $this->registerExceptionEncoder();
        $this->registerEventLoggers();
    }

    /**
     * Register field provider
     *
     * @return void
     */
    protected function registerFieldProvider()
    {
        $this->app->bind('understand.fieldProvider', function($app)
        {
            $fieldProvider = new FieldProvider();

            if ($app['config']['session.driver'])
            {
                $fieldProvider->setSessionStore($app['session.store']);
            }

            // router is available only from Lumen 5.5
            if (array_has($app->availableBindings, 'router'))
            {
                $fieldProvider->setRouter($app['router']);
            }

            $fieldProvider->setRequest($app['request']);
            $fieldProvider->setEnvironment($app->environment());
            $fieldProvider->setTokenProvider($app['understand.tokenProvider']);
            $fieldProvider->setDataCollector($app['understand.dataCollector']);
            $fieldProvider->setApp($app);

            return $fieldProvider;
        });

        if (! class_exists('UnderstandFieldProvider'))
        {
            class_alias('Understand\UnderstandLaravel5\Facades\UnderstandFieldProvider', 'UnderstandFieldProvider');
        }
    }

    /**
     * Register exception and event logger
     *
     * @return void
     */
    protected function registerEventLoggers()
    {
        parent::registerEventLoggers();

        if (! class_exists('UnderstandExceptionLogger'))
        {
            class_alias('Understand\UnderstandLaravel5\Facades\UnderstandExceptionLogger', 'UnderstandExceptionLogger');
        }
    }

    /**
     * Register understand logger
     *
     * @return void
     */
    protected function registerLogger()
    {
        parent::registerLogger();

        if (! class_exists('UnderstandLogger'))
        {
            class_alias('Understand\UnderstandLaravel5\Facades\UnderstandLogger', 'UnderstandLogger');
        }
    }

    /**
     * Register monolog handler
     *
     * @return void
     */
    protected function registerMonologHandler()
    {
        $this->app['Psr\Log\LoggerInterface']->pushHandler(new MonologHandler());
    }

    /**
     * Detect Lumen version
     *
     * @param array $versions
     * @return type
     */
    protected function detectLumenVersion(array $versions)
    {
        $re = '/Lumen \((.*)\) \(.*\)/m';

        $version = $this->app->version();

        preg_match($re, $version, $matches);

        return Str::startsWith($matches[1], $versions);
    }

    /**
     * Listen Laravel logs and queue events
     *
     * @return void
     */
    protected function listenLumenEvents()
    {
        // Lumen < 5.6 uses Monolog, so we need to manually raise event
        if ($this->detectLumenVersion(['5.0', '5.1', '5.2', '5.3', '5.4', '5.5']))
        {
            $this->registerMonologHandler();

            // the illuminate.log event is raised
            // by our MonologHandler, not by Lumen
            $this->app['events']->listen('illuminate.log', function ($log)
            {
                $this->handleEvent($log['level'], $log['message'], $log['context']);
            });
        }
        else
        {
            // starting from Lumen 5.6 MessageLogged event class was introduced
            $this->app['events']->listen('Illuminate\Log\Events\MessageLogged', function ($log)
            {
                $this->handleEvent($log->level, $log->message, $log->context);
            });
        }

        // starting from L5.2 JobProcessing event class was introduced
        // https://github.com/illuminate/queue/commit/ce2b5518902b1bcb9ef650c41900fc8c6392eb0c
        if ($this->app->runningInConsole()) {
            if ($this->detectLumenVersion(['5.0', '5.1'])) {
                $this->app['events']->listen('illuminate.queue.after', function () {
                    $this->app['understand.tokenProvider']->generate();
                    $this->app['understand.dataCollector']->reset();
                });

                $this->app['events']->listen('illuminate.queue.failed', function () {
                    $this->app['understand.tokenProvider']->generate();
                    $this->app['understand.dataCollector']->reset();
                });
            } else {
                $this->app['events']->listen('Illuminate\Queue\Events\JobProcessing', function () {
                    $this->app['understand.tokenProvider']->generate();
                    $this->app['understand.dataCollector']->reset();
                });
            }
        }
    }

    /**
     * Listen Query events
     *
     * @return void
     */
    protected function listenQueryEvents()
    {
        // only Lumen versions below L5.2 supports `illuminate.query`
        if ($this->detectLumenVersion(['5.0', '5.1']))
        {
            $this->app['events']->listen('illuminate.query', function($query, $bindings, $time)
            {
                $this->app['understand.dataCollector']->setInArray('sql_queries', [
                    'query' => $query,
                    'bindings' => $bindings,
                    'time' => $time,
                ]);
            });
        }
        else
        {
            // https://laravel.com/api/5.3/Illuminate/Database/Events/QueryExecuted.html
            $this->app['events']->listen('Illuminate\Database\Events\QueryExecuted', function($event)
            {
                $this->app['understand.dataCollector']->setInArray('sql_queries', [
                    'query' => $event->sql,
                    'bindings' => $event->bindings,
                    'time' => $event->time,
                ]);
            });
        }
    }

    /**
     * Handle a new log event
     *
     * @param string $level
     * @param mixed $message
     * @param array $context
     * @return void
     */
    protected function handleEvent($level, $message, $context)
    {
        if ($this->shouldIgnoreEvent($level, $message, $context))
        {
            return;
        }

        // `\Log::info`, `\Log::debug` and NOT `\Exception` or `\Throwable`
        if (in_array($level, ['info', 'debug']) && ! ($message instanceof Exception || $message instanceof Throwable))
        {
            $this->app['understand.eventLogger']->logEvent($level, $message, $context);
        }
        // `\Log::notice`, `\Log::warning`, `\Log::error`, `\Log::critical`, `\Log::alert`, `\Log::emergency` and `\Exception`, `\Throwable`
        else if (isset($context['exception']) && ($context['exception'] instanceof Exception || $context['exception'] instanceof Throwable))
        {
            $exception = $context['exception'];
            unset($context['exception']);

            $this->app['understand.exceptionLogger']->logError($level, $exception, $context);
        }
        else
        {
            $this->app['understand.exceptionLogger']->logError($level, $message, $context);
        }
    }
}