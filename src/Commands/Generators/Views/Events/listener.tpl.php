<@php

namespace {namespace};

use {event_import};

class {class}
{
    /**
     * Handle the event.
     */
    public function handle({event_class_name} $event): void
    {
        service('logger')->info('Event handled: ' . $event::NAME);

        // Add your logic here
    }
}