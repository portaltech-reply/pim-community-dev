<?php

declare(strict_types=1);

namespace Akeneo\Connectivity\Connection\Domain\Webhook\Event;

use Akeneo\Platform\Component\EventQueue\EventInterface;

/**
 * @copyright 2021 Akeneo SAS (http://www.akeneo.com)
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class EventsApiRequestSucceededEvent
{
    private string $connectionCode;

    /** @var EventInterface[] */
    private array $events;

    /**
     * @param EventInterface[] $events
     */
    public function __construct(string $connectionCode, array $events)
    {
        foreach ($events as $event) {
            if (!$event instanceof EventInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'Expected event of type "%s", got "%s".',
                    EventInterface::class,
                    get_debug_type($event),
                ));
            }
        }

        $this->connectionCode = $connectionCode;
        $this->events = $events;
    }

    public function getConnectionCode(): string
    {
        return $this->connectionCode;
    }

    /**
     * @return EventInterface[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
