<?php
namespace Craft;

class SproutEmail_SentEmailModel extends BaseElementModel
{
	public $saveAsNew;
	protected $fields;
	const ELEMENT_TYPE = 'SproutEmail_SentEmail';
	/**
	 * The element type this model is associated with
	 *
	 * @var string
	 */
	protected $elementType = 'SproutEmail_SentEmail';
	/**
	 * These have to be explicitly defined in order for the plugin to install
	 *
	 * @return array
	 */
	public function defineAttributes()
	{
		$defaults   = parent::defineAttributes();
		$attributes = array(
			'campaignEntryId'        => array(AttributeType::Number, 'required' => false),
			'campaignEntryId'        => array(AttributeType::Number, 'required' => false),
			'campaignNotificationId' => array(AttributeType::Number, 'required' => false),
			'title'           		 => array(AttributeType::String, 'required' => false),
			'emailSubject'           => array(AttributeType::String, 'required' => false),
			'fromEmail'              => array(AttributeType::String, 'required' => false),
			'fromName'               => array(AttributeType::String, 'required' => false),
			'toEmail'                => array(AttributeType::String, 'required' => false),
			'body'                   => array(AttributeType::String, 'required' => false),
			'htmlBody'               => array(AttributeType::Mixed,  'required' => false),
			'sender'                 => array(AttributeType::String, 'required' => false)
		);

		return array_merge($defaults, $attributes);
	}

	/**
	 * Returns the element's CP edit URL.
	 *
	 * @return string|false
	 */
	public function getCpEditUrl()
	{
		$url = UrlHelper::getCpUrl('sproutemail/sentemail/edit/'.$this->id);

		return $url;
	}
}