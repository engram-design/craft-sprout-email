<?php

namespace barrelstrength\sproutemail\events\notificationevents;

use barrelstrength\sproutbase\app\email\base\NotificationEvent;

use craft\elements\User;
use craft\events\UserEvent;
use craft\services\Users;
use Craft;


/**
 * @property UserEvent $event
 */
class UsersActivate extends NotificationEvent
{
    /**
     * @inheritdoc
     */
    public function getEventClassName()
    {
        return Users::class;
    }

    /**
     * @inheritdoc
     */
    public function getEventName()
    {
        return Users::EVENT_AFTER_ACTIVATE_USER;
    }

    /**
     * @inheritdoc
     */
    public function getEventHandlerClassName()
    {
        return UserEvent::class;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return Craft::t('sprout-email', 'When a user is activated');
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return Craft::t('sprout-email', 'Triggered when a user is activated.');
    }

    /**
     * @inheritdoc
     */
    public function getEventObject()
    {
        return $this->event->user;
    }

    /**
     * @inheritdoc
     */
    public function getMockEventObject()
    {
        $criteria = User::find();

        return $criteria->one();
    }
}
