<?php

namespace CodingLiki\Orm\EventSubSystem;

use CodingLiki\EventDispatcher\EventDispatcher;
use CodingLiki\EventDispatcher\EventDispatcherContainer;
use CodingLiki\EventDispatcher\Exceptions\NotKnownEventDispatcherException;
use CodingLiki\EventDispatcher\ListenerProviders\ArrayListenerProvider;
use Psr\EventDispatcher\EventDispatcherInterface;

class EventSubSystem
{
    private static ?ArrayListenerProvider    $listenerProvider = NULL;
    private static ?EventDispatcherInterface $eventDispatcher  = NULL;

    public static function getEventDispatcher(): EventDispatcherInterface
    {
        self::$eventDispatcher !== NULL ?: self::$eventDispatcher = self::initEventDispatcher();

        return self::$eventDispatcher;
    }

    public static function getListenerProvider(): ArrayListenerProvider
    {
        self::$listenerProvider !== NULL ?: self::$listenerProvider = new ArrayListenerProvider();

        return self::$listenerProvider;
    }

    public static function dispatch(object $event): object
    {
        return self::getEventDispatcher()->dispatch($event);
    }

    public static function subscribe(string $eventClass, callable $listener)
    {
        self::getListenerProvider()->addListener($eventClass, $listener);
    }

    private static function initEventDispatcher(): EventDispatcherInterface
    {
        try {
            return EventDispatcherContainer::get('orm');
        } catch (NotKnownEventDispatcherException $e) {
            $dispatcher = new EventDispatcher([self::getListenerProvider()]);
            EventDispatcherContainer::add('orm', $dispatcher);

            return $dispatcher;
        }
    }
}