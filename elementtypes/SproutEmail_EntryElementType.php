<?php
namespace Craft;

class SproutEmail_EntryElementType extends BaseElementType
{
	/**
	 * @return string
	 */
	public function getName()
	{
		return Craft::t('Sprout Email Entry');
	}

	/**
	 * @return bool
	 */
	public function hasContent()
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function hasTitles()
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function isLocalized()
	{
		return false;
	}

	/**
	 * @inheritDoc IElementType::hasStatuses()
	 *
	 * @return bool
	 */
	public function hasStatuses()
	{
		return true;
	}

	public function getStatuses()
	{
		return array(
			SproutEmail_EntryModel::READY    => Craft::t('Ready'),
			SproutEmail_EntryModel::PENDING  => Craft::t('Pending'),
			SproutEmail_EntryModel::DISABLED => Craft::t('Disabled'),
			SproutEmail_EntryModel::ARCHIVED => Craft::t('Archived'),
		);
	}

	/**
	 * Returns this element type's sources.
	 *
	 * @param string|null $context
	 *
	 * @return array|false
	 */
	public function getSources($context = null)
	{
		// Grab all of our Notifications
		$notifications   = sproutEmail()->campaigns->getCampaigns('notification');
		$notificationIds = array();

		$sources = array(
			'*'             => array(
				'label'     => Craft::t('All Emails'),
			),
		);

		if (count($notifications))
		{
			// Create a list of Notification IDs we can use as criteria to filter by
			foreach ($notifications as $notification)
			{
				$notificationIds[] = $notification->id;
			}

			$sources['notifications'] = array(
				'label'             => Craft::t('Notifications'),
				'criteria'          => array(
					'campaignId'    => $notificationIds
				)
			);
 		}

		// Prepare the data for our sources sidebar
		$campaigns = sproutEmail()->campaigns->getCampaigns('email');

		if (count($campaigns))
		{
			$sources[] = array('heading' => 'Campaigns');

			foreach ($campaigns as $campaign)
			{
				$key = 'campaign:'.$campaign->id;

				$sources[$key] = array(
					'label'    => $campaign->name,
					'data'     => array('campaignId' => $campaign->id),
					'criteria' => array('campaignId' => $campaign->id)
				);
			}
		}

		return $sources;
	}

	/**
	 * @param ElementCriteriaModel $criteria
	 * @param array                $disabledElementIds
	 * @param array                $viewState
	 * @param null|string          $sourceKey
	 * @param null|string          $context
	 * @param                      $includeContainer
	 * @param                      $showCheckboxes
	 *
	 * @return string
	 */
	public function getIndexHtml(
		$criteria,
		$disabledElementIds,
		$viewState,
		$sourceKey,
		$context,
		$includeContainer,
		$showCheckboxes
	) {
		if ($context == 'index')
		{
			$criteria->offset = 0;
			$criteria->limit  = null;

			$source = $this->getSource($sourceKey, $context);

			return craft()->templates->render(
				'sproutemail/entries/_entryindex', array(
					'context'            => $context,
					'elementType'        => new ElementTypeVariable($this),
					'disabledElementIds' => $disabledElementIds,
					'elements'           => $criteria->find(),
				)
			);
		}

		return parent::getIndexHtml($criteria, $disabledElementIds, $viewState, $sourceKey, $context, $includeContainer, $showCheckboxes);
	}

	/**
	 * Returns the attributes that can be shown/sorted by in table views.
	 *
	 * @param string|null $source
	 *
	 * @return array
	 */
	public function defineTableAttributes($source = null)
	{
		return array(
			'title'       => Craft::t('Title'),
			'dateCreated' => Craft::t('Date Created'),
			'dateUpdated' => Craft::t('Date Updated'),
		);
	}

	/**
	 * Defines any custom element criteria attributes for this element type.
	 *
	 * @return array
	 */
	public function defineCriteriaAttributes()
	{
		return array(
			'title'       => AttributeType::String,
			'campaignId'  => AttributeType::Number,
		);
	}

	/**
	 * @inheritDoc IElementType::getElementQueryStatusCondition()
	 *
	 * @param DbCommand $query
	 * @param string    $status
	 *
	 * @return array|false|string|void
	 */
	public function getElementQueryStatusCondition(DbCommand $query, $status)
	{
		switch ($status)
		{
			case SproutEmail_EntryModel::DISABLED:
			{
				return 'campaigns.template IS NULL';
			}

			case SproutEmail_EntryModel::PENDING:
			{
				return array(
					'and',
					'elements.enabled = 0',
					'campaigns.template IS NOT NULL',
					'entries.sent = 0',
				);
			}

			case SproutEmail_EntryModel::READY:
			{
				return array(
					'and',
					'elements.enabled = 1',
					'elements_i18n.enabled = 1',
					'campaigns.template IS NOT NULL',
					'entries.sent = 0',
				);
			}

			case SproutEmail_EntryModel::ARCHIVED:
			{
				return 'entries.sent = 1';
			}
		}
	}

	/**
	 * Defines which model attributes should be searchable.
	 *
	 * @return array
	 */
	public function defineSearchableAttributes()
	{
		return array(
			'title',
		);
	}

	/**
	 * Modifies an element query targeting elements of this type.
	 *
	 * @param DbCommand            $query
	 * @param ElementCriteriaModel $criteria
	 *
	 * @return mixed
	 */
	public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
	{
		$query
			->addSelect(
				'entries.id
				, entries.subjectLine as subjectLine
				, entries.campaignId as campaignId
				, entries.fromName as fromName
				, entries.fromEmail as fromEmail
				, entries.replyTo as replyTo
				, entries.sent as sent
				, campaigns.type as type'
			)
			->join('sproutemail_campaigns_entries entries', 'entries.id = elements.id')
			->join('sproutemail_campaigns campaigns', 'campaigns.id = entries.campaignId');

		if ($criteria->campaignId)
		{
			$query->andWhere(DbHelper::parseParam('entries.campaignId', $criteria->campaignId, $query->params));
		}
	}

	/**
	 * Populates an element model based on a query result.
	 *
	 * @param array $row
	 *
	 * @return array
	 */
	public function populateElementModel($row)
	{
		return SproutEmail_EntryModel::populateModel($row);
	}
}