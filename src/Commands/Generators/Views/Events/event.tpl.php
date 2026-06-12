<@php

namespace {namespace};

use Jengo\Base\Events\AbstractEvent;

class {class} extends AbstractEvent
{
    public const NAME = '{event_name}';

    public function __construct(
        public array $data = []
    ) {}
}