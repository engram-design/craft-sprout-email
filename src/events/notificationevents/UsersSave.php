<?php

namespace barrelstrength\sproutemail\events\notificationevents;

use barrelstrength\sproutbase\app\email\base\NotificationEvent;


use Craft;
use craft\elements\User;
use craft\events\ModelEvent;
use craft\helpers\ArrayHelper;
use craft\events\ElementEvent;

class UsersSave extends NotificationEvent
{
    public $whenNew = false;

    public $whenUpdated = false;

    public $groups;

    public $fieldValue;

    public $userGroupIds = [];

    /**
     * @inheritdoc
     */
    public function getEventClassName()
    {
        return User::class;
    }

    /**
     * @inheritdoc
     */
    public function getEventName()
    {
        return User::EVENT_AFTER_SAVE;
    }

    /**
     * @inheritdoc
     */
    public function getEventHandlerClassName()
    {
        return ModelEvent::class;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return Craft::t('sprout-email', 'When a user is saved');
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return Craft::t('sprout-email', 'Triggered when a user is saved.');
    }

    /**
     * @inheritdoc
     *
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function getSettingsHtml($settings = [])
    {
        if (!$this->groups) {
            $this->groups = $this->getAllGroups();
        }

        return Craft::$app->getView()->renderTemplate('sprout-base-email/_components/events/save-user', [
            'event' => $this
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getEventObject()
    {
        $event = $this->event ?? null;

        return $event->sender ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getMockEventObject()
    {
        $criteria = User::find();

        $ids = $this->userGroupIds;

        if (is_array($ids) && count($ids)) {
            $id = array_shift($ids);

            $criteria->groupId = $id;
        }

        return $criteria->one();
    }

    public function rules()
    {
        $rules = parent::rules();

        $rules[] = [
            'whenNew', 'required', 'when' => function() {
                return $this->whenUpdated == false;
            }
        ];

        $rules[] = [
            'whenUpdated', 'required', 'when' => function() {
                return $this->whenNew == false;
            }
        ];

        $rules[] = [['whenNew', 'whenUpdated'], 'validateWhenTriggers'];
        $rules[] = [['event'], 'validateEvent'];
        $rules[] = ['userGroupIds', 'default', 'value' => false];
        $rules[] = [['userGroupIds'], 'validateUserGroupIds'];

        return $rules;
    }

    public function validateWhenTriggers()
    {
        /**
         * @var ElementEvent $event
         */
        $event = $this->event ?? null;

        $isNewEntry = $event->isNew ?? false;

        $matchesWhenNew = $this->whenNew && $isNewEntry ?? false;
        $matchesWhenUpdated = $this->whenUpdated && !$isNewEntry ?? false;

        if (!$matchesWhenNew && !$matchesWhenUpdated) {
            $this->addError('event', Craft::t('sprout-email', 'When user is saved event does not match any scenarios.'));
        }

        // Make sure new entries are new.
        if (($this->whenNew && !$isNewEntry) && !$this->whenUpdated) {
            $this->addError('event', Craft::t('sprout-email', '"When user is created" is selected but the User is being updated.'));
        }

        // Make sure updated entries are not new
        if (($this->whenUpdated && $isNewEntry) && !$this->whenNew) {
            $this->addError('event', Craft::t('sprout-email', '"When user is updated" is selected but the User is new.'));
        }
    }

    public function validateEvent()
    {
        $event = $this->event ?? null;

        if (!$event) {
            $this->addError('event', Craft::t('sprout-email', 'ElementEvent does not exist.'));
        }

        if (get_class($event->sender) !== User::class) {
            $this->addError('event', Craft::t('sprout-email', 'Event Element does not match craft\elements\Entry class.'));
        }
    }

    public function validateUserGroupIds()
    {
        /**
         * @var ElementEvent $event
         */
        $event = $this->event ?? null;

        /**
         * @var User $element
         */
        $element = $event->sender;

        $currentUsersUserGroups = null;

        if (get_class($element) === User::class) {
            $currentUsersUserGroups = $element->getGroups();
        }

        if ($this->userGroupIds != false) {
            if ($this->isValidUserGroupIds($currentUsersUserGroups)) {

                $inGroup = false;

                // When saving a new user, we grab our groups from the post request
                // because _processUserGroupsPermissions() runs after saveUser()
                $newUserGroups = Craft::$app->request->getBodyParam('groups');

                if (!is_array($newUserGroups)) {
                    $newUserGroups = ArrayHelper::toArray($newUserGroups);
                }

                foreach ($this->userGroupIds as $groupId) {
                    if (array_key_exists($groupId, $currentUsersUserGroups) || in_array($groupId, $newUserGroups, false)) {
                        $inGroup = true;
                    }
                }

                if (!$inGroup) {
                    $this->addError('event', Craft::t('sprout-email', 'Saved user not in any selected User Group.'));
                }
            }
        } else {
            $this->addError('event', Craft::t('sprout-email', 'No User Group has been selected.'));
        }
    }

    private function isValidUserGroupIds($currentUsersUserGroups)
    {
        return $this->userGroupIds !== '*'
            AND count($this->userGroupIds) > 0
            AND count($currentUsersUserGroups);
    }

    /**
     * Returns an array of groups suitable for use in checkbox field
     *
     * @return array
     */
    public function getAllGroups()
    {
        try {
            $groups = Craft::$app->userGroups->getAllGroups();
        } catch (\Exception $e) {
            $groups = [];
        }

        $options = [];

        if (count($groups)) {
            foreach ($groups as $key => $group) {
                $options[] = [
                    'label' => $group->name,
                    'value' => $group->id
                ];
            }
        }

        return $options;
    }
}
