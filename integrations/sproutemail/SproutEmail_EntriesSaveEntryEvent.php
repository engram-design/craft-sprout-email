<?php
namespace Craft;

class SproutEmail_EntriesSaveEntryEvent extends SproutEmailBaseEvent
{
	public function getName()
	{
		return 'entries.saveEntry';
	}

	public function getTitle()
	{
		return 'Save Entry';
	}

	public function getDescription()
	{
		return 'Triggered when an entry is saved.';
	}

	public function getOptionsHtml($context = array())
	{
		return craft()->templates->render('sproutemail/_events/saveEntry', $context);
	}

	public function prepareOptions()
	{
		return array(
			'entriesSaveEntrySectionIds'  => craft()->request->getPost('entriesSaveEntrySectionIds'),
			'entriesSaveEntryOnlyWhenNew' => craft()->request->getPost('entriesSaveEntryOnlyWhenNew'),
		);
	}

	public function validateOptions($options, EntryModel $entry, array $params = array())
	{
		$isNewEntry  = isset($params['isNewEntry']) && $params['isNewEntry'];
		$onlyWhenNew = isset($options['onlyWhenNew']) && $options['onlyWhenNew'];

		if (in_array($entry->getSection()->id, $options))
		{
			return (!$onlyWhenNew || $isNewEntry);
		}

		return false;
	}

	public function prepareParams(Event $event)
	{
		return array('value' => $event->params['entry'], 'isNewEntry' => $event->params['isNewEntry']);
	}

	public function prepareValue($value)
	{
		return $value;
	}
}
