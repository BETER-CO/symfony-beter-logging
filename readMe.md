# Symfony Beter Logging Bundle

Bulletproof logging for enterprise symfony projects.

monolog integration, pretty console handler, logstash via udp and tcp with deep symfony integration.

Features:
* uses monolog under the hood;
* adds [log context](doc/logging-with-context.md) for messages and exceptions;
* supports stdout/stderr with colors;
* supports logstash tcp and udp transport;

Related packages you need to try:
* [beter/exception-with-context](https://packagist.org/packages/beter/exception-with-context) is used by
  `symfony-beter-logging` package to bring support of context data storing in the exception.
  [Check the doc](doc/logging-with-context.md).

## Installation

The preferred way to install this extension is through [composer](https://getcomposer.org/).

Either run

```
composer require beter/symfony-beter-logging
```

or add

```
"beter/symfony-beter-logging": "~1.0.0" // add the latest version
```

to the require section of your composer.json.

## Configuration

To use this bundle, you have to configure monolog bundle in your application configuration.

### Configure Yii2 log component

```
# config/packages/monolog.yaml
parameters:
    # BasicProcessor parameters
    beter.logger.app.name: 'app.name'
    beter.logger.app.service: 'app.service'
    # Logstash handler parameters
    beter.logger.logstash_handler.bubble: true

    # Console formatter parameters
    beter.logger.console_formatter.colorize: false
        
monolog:
    handlers:
        standard_stream:
            type: service
            id: beter.logger.standard_stream_handler
        
        logstash:
            type: service
            id: beter.logger.logstash_handler
```
This defines a parameters and stack of handlers and each handler is called in the order that it's defined. All default 
parameters used by handlers and formatters you can see inside bundle config file by path: 
`BeterLoggingBundle/src/Resources/config/services.yaml`.
Also you can override each parameter on the project level.

For more information you can check the [`MonologBundle`](https://symfony.com/doc/current/logging.html) documentation

## Usage

Just inject default logger to service.

```
use Psr\Log\LoggerInterface;

public function index(LoggerInterface $logger)
{
    $logger->info('I just got the logger');
    $logger->error('An error occurred');

    $logger->critical('I left the oven on!', [
        // include extra "context" info in your logs
        'cause' => 'in_hurry',
    ]);

    // ...
}
```
