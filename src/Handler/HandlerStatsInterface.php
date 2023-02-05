<?php

namespace Beter\Bundle\BeterLoggingBundle\Handler;

interface HandlerStatsInterface
{
    public function getAmountOfFailedHandleCalls(): int;

    public function getAmountOfSuccessfulHandleCalls(): int;

    public function getHandleExecTimes(): \SplQueue;

    public function getAmountOfDequeuedExecTimes(): int;

    public function hasExecTimeQueueBeenOverflowed(): int;
}