<?php

declare(strict_types=1);

namespace Beter\Bundle\BeterLoggingBundle\EventSubscriber;

use Beter\Bundle\BeterLoggingBundle\Exception\HandlerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CliSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        try {
            $command = $event->getCommand()->getName();
            $this->stopwatch->start($command);
        } catch (\Throwable $t) {
            $this->logger->error(
                new HandlerException('An error occurred during the context gathering', $event, $t)
            );
        }

        $this->logger->info('CLI command start', ['command' => $command]);
    }

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $context = [];
        try {
            $command = $event->getCommand()->getName();
            $context['command'] = $command;
            $context['exitStatus'] = $event->getExitCode();
            $context['execTimeSec'] = microtime(true) - 0;
            $context['memoryPeakUsageBytes'] = memory_get_peak_usage(true);
        } catch (\Throwable $t) {
            $this->logger->error(
                new HandlerException('An error occurred during the context gathering', $event, $t)
            );
        }

        $this->logger->info('CLI command error', $context);
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $context = [];
        try {
            $command = $event->getCommand()->getName();
            $context['command'] = $command;
            $context['exitStatus'] = $event->getExitCode();
            $context['execTimeSec'] = $this->stopwatch->stop($command);
            $context['memoryPeakUsageBytes'] = memory_get_peak_usage(true);
        } catch (\Throwable $t) {
            $this->logger->error(
                new HandlerException('An error occurred during the context gathering', $event, $t)
            );
        }

        $this->logger->info('CLI command end', $context);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onConsoleCommand',
            ConsoleEvents::ERROR => 'onConsoleError',
            ConsoleEvents::TERMINATE => 'onConsoleTerminate',
        ];
    }
}
