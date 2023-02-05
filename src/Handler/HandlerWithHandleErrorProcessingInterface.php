<?php

namespace Beter\Bundle\BeterLoggingBundle\Handler;

interface HandlerWithHandleErrorProcessingInterface
{
    public function setHandleExceptionHandler(?callable $callback): self;
    public function setMaxHandleErrorsBeforeDisabling(int $maxAmountOfHandleErrors): self;
}
