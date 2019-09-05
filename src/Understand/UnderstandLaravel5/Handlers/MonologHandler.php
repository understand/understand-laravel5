<?php namespace Understand\UnderstandLaravel5\Handlers;

use Monolog\Handler\AbstractHandler;
use Monolog\Logger;
use Laravel\Lumen\Application;

class MonologHandler extends AbstractHandler
{
    /**
     * Mapping between numeric constant and string
     *
     * @var array
     */
    protected $levelMap = [
        Logger::DEBUG     => 'debug',
        Logger::INFO      => 'info',
        Logger::NOTICE    => 'notice',
        Logger::WARNING   => 'warning',
        Logger::ERROR     => 'error',
        Logger::CRITICAL  => 'critical',
        Logger::ALERT     => 'alert',
        Logger::EMERGENCY => 'emergency',
    ];

    /**
     * @param string|int $level  The minimum logging level at which this handler will be triggered
     * @param bool       $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    /**
     * Handle the logging of the record.
     *
     * @param array $record
     * @return bool|void
     */
    public function handle(array $record)
    {
        $record['level'] = $this->levelMap[$record['level']];

        // raise event so the record is captured by the ServiceProvider
        app('events')->fire('illuminate.log', [$record]);
    }
}