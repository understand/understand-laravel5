<?php namespace Understand\UnderstandLaravel5;

use Understand\UnderstandLaravel5\UniqueProcessIdentifier;
use \Illuminate\Session\Store AS SessionStore;
use \Illuminate\Routing\Router;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;

class FieldProvider
{

    /**
     * The registered field providers.
     *
     * @var array
     */
    protected $providers = [];

    /**
     * Default field
     *
     * @var array
     */
    protected $defaultProviders = [
        'getSessionId',
        'getRouteName',
        'getUrl',
        'getRequestMethod',
        'getServerIp',
        'getClientIp',
        'getClientUserAgent',
        'getEnvironment',
        'getFromSession',
        'getProcessIdentifier',
        'getUserId',
        'getGroupId',
        'getLaravelVersion',
        'getSqlQueries',
        'getArtisanCommandName',
        'getRunningInConsole',
        'getLoggerVersion',
    ];

    /**
     * Session store
     *
     * @var \Illuminate\Session\Store
     */
    protected $session;

    /**
     * Router
     *
     * @var Router
     */
    protected $router;

    /**
     * Server variable
     *
     * @var Request
     */
    protected $request;

    /**
     * Token provider
     *
     * @var UniqueProcessIdentifier
     */
    protected $tokenProvider;

    /**
     * Current environment
     *
     * @var string
     */
    protected $environment;

    /**
     * @var DataCollector
     */
    protected $dataCollector;

    /**
     * @var Application
     */
    protected $app;

    /**
     * @return void
     */
    public function __construct()
    {
        foreach ($this->defaultProviders as $defaultProviderName)
        {
            $this->extend($defaultProviderName, [$this, $defaultProviderName]);
        }
    }

    /**
     * @param Application $app
     */
    public function setApp(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Set session store
     *
     * @param type $service
     */
    public function setSessionStore(SessionStore $service)
    {
        $this->session = $service;
    }

    /**
     * Set router
     *
     * @param Router $router
     */
    public function setRouter(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Set request
     *
     * @param Request $request
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Set current environment
     *
     * @param string $environment
     */
    public function setEnvironment($environment)
    {
        $this->environment = $environment;
    }

    /**
     * @param DataCollector $dataCollector
     */
    public function setDataCollector(DataCollector $dataCollector)
    {
        $this->dataCollector = $dataCollector;
    }

    /**
     * Register a custom HTML macro.
     *
     * @param string $name
     * @param  mixed  $macro
     * @return void
     */
    public function extend($name, $provider)
    {
        $this->providers[$name] = $provider;
    }

    /**
     * Set token provider
     *
     * @param UniqueProcessIdentifier $tokenProvider
     */
    public function setTokenProvider(TokenProvider $tokenProvider)
    {
        $this->tokenProvider = $tokenProvider;
    }

    /**
     * Return resolved field-value array
     *
     * @param array $callbacks
     * @param array $log
     * @return array
     */
    public function resolveValues(array $callbacks, array $log)
    {
        $data = [];

        foreach ($callbacks as $fieldName => $caller)
        {
            if (!is_array($caller))
            {
                $caller = [$caller];
            }

            $callback = array_get($caller, 0);
            $args = [$log];

            $value = call_user_func_array($callback, $args);

            $data[$fieldName] = $value;
        }

        return $data;
    }

    /**
     * Handle class calls
     *
     * @param string $name
     * @param  mixed $params
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($name, $params)
    {
        if (isset($this->providers[$name]))
        {
            return call_user_func_array($this->providers[$name], $params);
        }

        throw new \BadMethodCallException("Method {$name} does not exist.");
    }

    /**
     * Return hashed version of session id
     *
     * @return string
     */
    protected function getSessionId()
    {
        if ( ! $this->session)
        {
            return null;
        }

        $sessionId = $this->session->getId();

        // by default we provide only hashed version of session id
        $hashed = sha1($sessionId);

        return $hashed;
    }

    /**
     * @return string
     */
    protected function getLaravelVersion()
    {
        return Application::VERSION;
    }

    /**
     * @return array
     */
    protected function getSqlQueries()
    {
        if ($this->dataCollector)
        {
            return $this->dataCollector->getByKey('sql_queries');
        }
    }

    /**
     * Return current route name
     *
     * @return string
     */
    protected function getRouteName()
    {
        if ( ! $this->router)
        {
            return null;
        }

        return $this->router->getCurrentRoute()->getName();
    }

    /**
     * Return current url
     *
     * @return string
     */
    protected function getUrl()
    {
        if ( ! $this->request)
        {
            return null;
        }

        $url = $this->request->path();

        if ( ! starts_with($url, '/'))
        {
            $url = '/' . $url;
        }

        $queryString = $this->request->getQueryString();

        if ($queryString)
        {
            $url .= '?' . $queryString;
        }

        return $url;
    }

    /**
     * Return request method
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        if ( ! $this->request)
        {
            return null;
        }

        return $this->request->method();
    }

    /**
     * Return server ip address
     *
     * @return string
     */
    protected function getServerIp()
    {
        if ( ! $this->request)
        {
            return null;
        }

        return $this->request->server->get('SERVER_ADDR');
    }

    /**
     * Return client ip
     *
     * @return string
     */
    protected function getClientIp()
    {
        if ( ! $this->request)
        {
            return null;
        }

        return $this->request->getClientIp();
    }

    /**
     * Return client user agent string
     *
     * @return string
     */
    protected function getClientUserAgent()
    {
        if ( ! $this->request)
        {
            return null;
        }

        return $this->request->server->get('HTTP_USER_AGENT');
    }

    /**
     * Return current enviroment
     *
     * @return string
     */
    protected function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Retrive parameter from current session
     *
     * @param string $key
     * @return string
     */
    protected function getFromSession($key)
    {
        if ( ! $this->session)
        {
            return null;
        }

        return $this->session->get($key);
    }

    /**
     * Return group id
     *
     * @param array $log
     * @return string
     */
    protected function getGroupId(array $log)
    {
        $parts = [];

        foreach(['class', 'file', 'line'] as $field)
        {
            $parts[] = isset($log[$field]) ? (string)$log[$field] : null;
        }

        return sha1(implode('#', $parts));
    }

    /**
     * Return current active user id
     *
     * @return int
     */
    protected function getUserId()
    {
        try
        {
            if (class_exists('\Auth') && ($userId = \Auth::id()))
            {
                return $userId;
            }
        }
        catch (\Exception $e)
        {}

        try
        {
            if (class_exists('\Sentinel') && ($user = \Sentinel::getUser()))
            {
                return $user->id;
            }
        }
        catch (\Exception $e)
        {}

        try
        {
            if (class_exists('\Sentry') && ($user = \Sentry::getUser()))
            {
                return $user->id;
            }
        }
        catch (\Exception $e)
        {}
    }

    /**
     * @return string
     */
    protected function getArtisanCommandName()
    {
        if ($this->app->runningInConsole() && isset($_SERVER['argv']))
        {
            return implode(' ', $_SERVER['argv']);
        }
    }

    /**
     * @return bool
     */
    protected function getRunningInConsole()
    {
        return $this->app->runningInConsole();
    }

    /**
     * @return float
     */
    protected function getLoggerVersion()
    {
        return Logger::VERSION;
    }

    /**
     * Return process identifier token
     *
     * @return string
     */
    protected function getProcessIdentifier()
    {
        return $this->tokenProvider->getToken();
    }

}
