<?php namespace Understand\UnderstandLaravel5;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Str;
use Illuminate\Foundation\Application;
use Exception;
use Throwable;

class UnderstandLaravel5ServiceProvider extends UnderstandServiceProvider
{

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
        $configPath = __DIR__ . '/../../config/understand-laravel.php';
        $this->publishes([$configPath => config_path('understand-laravel.php')], 'config');
        $enabled = $this->app['config']->get('understand-laravel.enabled');

        if ($enabled)
        {
            $this->listenLaravelEvents();
        }

        if ($enabled && $this->app['config']->get('understand-laravel.sql_enabled'))
        {
            $this->listenQueryEvents();
        }
	}

	/**
	 * Register the service provider.
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

            $fieldProvider->setRouter($app['router']);
            $fieldProvider->setRequest($app['request']);
            $fieldProvider->setEnvironment($app->environment());
            $fieldProvider->setTokenProvider($app['understand.tokenProvider']);
            $fieldProvider->setDataCollector($app['understand.dataCollector']);
            $fieldProvider->setApp($app);

            return $fieldProvider;
        });

        $this->app->booting(function()
        {
            $loader = AliasLoader::getInstance();
            $loader->alias('UnderstandFieldProvider', 'Understand\UnderstandLaravel5\Facades\UnderstandFieldProvider');
        });
    }

    /**
     * Register exception and event logger
     *
     * @return void
     */
    protected function registerEventLoggers()
    {
        parent::registerEventLoggers();

        $this->app->booting(function()
        {
            $loader = AliasLoader::getInstance();
            $loader->alias('UnderstandExceptionLogger', 'Understand\UnderstandLaravel5\Facades\UnderstandExceptionLogger');
        });
    }

    /**
     * Register understand logger
     *
     * @return void
     */
    protected function registerLogger()
    {
        parent::registerLogger();

        $this->app->booting(function()
        {
            $loader = AliasLoader::getInstance();
            $loader->alias('UnderstandLogger', 'Understand\UnderstandLaravel5\Facades\UnderstandLogger');
        });
    }
    
    /**
     * Detect Laravel version
     * 
     * @param array $versions
     * @return type
     */
    protected function detectLaravelVersion(array $versions)
    {
        return Str::startsWith(Application::VERSION, $versions);
    }
    
    /**
     * Listen Laravel logs and queue events
     *
     * @return void
     */
    protected function listenLaravelEvents()
    {
        // only Laravel versions below L5.4 supports `illuminate.log`
        if ($this->detectLaravelVersion(['5.0', '5.1', '5.2', '5.3']))
        {
            $this->app['events']->listen('illuminate.log', function($level, $message, $context)
            {
                $this->handleEvent($level, $message, $context);
            });
        }
        else
        {
            // starting from L5.4 MessageLogged event class was introduced
            // https://github.com/laravel/framework/commit/57c82d095c356a0fe0f9381536afec768cdcc072
            $this->app['events']->listen('Illuminate\Log\Events\MessageLogged', function($log) 
            {

                $this->handleEvent($log->level, $log->message, $log->context);
            });
        }

        // starting from L5.2 JobProcessing event class was introduced
        // https://github.com/illuminate/queue/commit/ce2b5518902b1bcb9ef650c41900fc8c6392eb0c
        if ($this->app->runningInConsole())
        {
            if ($this->detectLaravelVersion(['5.0', '5.1']))
            {
                $this->app['events']->listen('illuminate.queue.after', function()
                {
                    $this->app['understand.tokenProvider']->generate();
                    $this->app['understand.dataCollector']->reset();
                });

                $this->app['events']->listen('illuminate.queue.failed', function()
                {
                    $this->app['understand.tokenProvider']->generate();
                    $this->app['understand.dataCollector']->reset();
                });
            }
            else
            {
                $this->app['events']->listen('Illuminate\Queue\Events\JobProcessing', function()
                {
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
        // only Laravel versions below L5.2 supports `illuminate.query`
        if ($this->detectLaravelVersion(['5.0', '5.1']))
        {
            // $this->events->fire('illuminate.query', [$query, $bindings, $time, $this->getName()]);
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
        // '5.5', '5.6', '5.7', '5.8'
        else if ( ! $this->detectLaravelVersion(['5.0', '5.1', '5.2', '5.3', '5.4']) && isset($context['exception']) && ($context['exception'] instanceof Exception || $context['exception'] instanceof Throwable))
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
