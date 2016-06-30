<?php
namespace Craft;

/**
 * Class SproutEmail_EntryRecipientListRecord
 *
 * @package Craft
 * --
 * @property int    $entryId
 * @property string $mailer
 * @property string $list
 * @property string $type
 */
class SproutEmail_NotificationRecipientListRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName()
	{
		return 'sproutemail_notification_recipientlists';
	}

	/**
	 * @return array
	 */
	public function defineAttributes()
	{
		return array(
			'list'   => AttributeType::String
		);
	}

	public function defineRelations()
	{
		return array(
			'notification' => array(
				static::BELONGS_TO,
				'SproutEmail_NotificationEmailRecord',
				'notificationId',
				'required' => true,
				'onDelete' => static::CASCADE,
			)
		);
	}
}
