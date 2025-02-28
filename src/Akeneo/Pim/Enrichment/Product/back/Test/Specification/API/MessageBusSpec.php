<?php

declare(strict_types=1);

namespace Specification\Akeneo\Pim\Enrichment\Product\API;

use Akeneo\Pim\Enrichment\Product\API\Command\UpsertProductCommand;
use Akeneo\Pim\Enrichment\Product\API\MessageBus;
use Akeneo\Pim\Enrichment\Product\API\UnknownCommandException;
use Akeneo\Test\Pim\Enrichment\Product\Helper\DummyHandler;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Messenger\MessageBusInterface;

class MessageBusSpec extends ObjectBehavior
{
    function let(DummyHandler $handler1, DummyHandler $handler2)
    {
        $this->beConstructedWith([
            'Other' => $handler1,
            UpsertProductCommand::class => $handler2,
        ]);
    }

    function it_is_a_message_bus()
    {
        $this->shouldHaveType(MessageBus::class);
        $this->shouldImplement(MessageBusInterface::class);
    }

    function it_executes_the_correct_handler(DummyHandler $handler1, DummyHandler $handler2)
    {
        $command = new UpsertProductCommand(userId: 1, productIdentifier: 'foo');
        $handler1->__invoke(Argument::any())->shouldNotBeCalled();
        $handler2->__invoke($command)->shouldBeCalledOnce();

        $this->dispatch($command);
    }

    function it_throws_an_exception_when_the_command_cannot_be_handled(DummyHandler $handler1, DummyHandler $handler2)
    {
        $handler1->__invoke(Argument::any())->shouldNotBeCalled();
        $handler2->__invoke(Argument::any())->shouldNotBeCalled();

        $this->shouldThrow(UnknownCommandException::class)->during('dispatch', [new \stdClass()]);
    }
}
