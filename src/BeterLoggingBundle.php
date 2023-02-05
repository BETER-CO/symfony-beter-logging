<?php

namespace Beter\Bundle\BeterLoggingBundle;

use Beter\Bundle\BeterLoggingBundle\DependencyInjection\BeterLoggingExtension;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class BeterLoggingBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension(): BeterLoggingExtension
    {
        return new BeterLoggingExtension();
    }
}