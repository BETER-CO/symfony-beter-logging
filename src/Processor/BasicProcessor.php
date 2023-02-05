<?php

namespace Beter\Bundle\BeterLoggingBundle\Processor;

use Beter\Bundle\BeterLoggingBundle\Exception\InvalidConfigException;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class BasicProcessor implements ProcessorInterface
{
    protected string $envName;
    protected string $app;
    protected string $service;
    protected string $execType;
    protected string $host;

    /**
     * @param string $envName name of the current environment, dev, test, stage, etc
     * @param string $app application name
     * @param string $service service name, service is usually a part of application
     * @param string $execType application type (cli, web, etc).
     * @param string $host hostname of the system
     * @throws InvalidConfigException
     */
    public function __construct(string $envName, string $app, string $service, string $execType, string $host)
    {
        if (empty($envName)) {
            throw new InvalidConfigException('envName must be a non-empty string');
        }

        if (empty($app)) {
            throw new InvalidConfigException('app must be a non-empty string');
        }

        if (empty($service)) {
            throw new InvalidConfigException('service must be a non-empty string');
        }

        if (empty($execType)) {
            throw new InvalidConfigException('execType must be a non-empty string');
        }

        if (empty($host)) {
            throw new InvalidConfigException('host must be a non-empty string');
        }

        $this->envName = $envName;
        $this->app = $app;
        $this->service = $service;
        $this->execType = $execType;
        $this->host = $host;
    }

    /**
     * {@inheritDoc}
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['env'] = $this->envName;
        $record->extra['app'] = $this->app;
        $record->extra['service'] = $this->service;
        $record->extra['execType'] = $this->execType;
        $record->extra['host'] = $this->host;

        return $record;
    }
}