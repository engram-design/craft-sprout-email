<?php
namespace Craft;

class SproutEmail_CopyPasteService extends BaseApplicationComponent
{
	protected $settings;

	public function setSettings($settings)
	{
		$this->settings = $settings;
	}

	/**
	 * @param SproutEmail_CampaignEmailModel $campaignEmail
	 * @param SproutEmail_CampaignTypeModel  $campaignType
	 *
	 * @return SproutEmail_ResponseModel
	 */
	public function sendCampaignEmail(SproutEmail_CampaignEmailModel $campaignEmail, SproutEmail_CampaignTypeModel $campaignType)
	{
		$params = array(
			'email'     => $campaignEmail,
			'campaign'  => $campaignType,
			'recipient' => array(
				'firstName' => 'John',
				'lastName'  => 'Doe',
				'email'     => 'john@doe.com'
			),

			// @deprecate - in favor of `email` in v3
			'entry'     => $campaignEmail
		);

		$html = sproutEmail()->renderSiteTemplateIfExists($campaignType->templateCopyPaste, $params);
		$text = sproutEmail()->renderSiteTemplateIfExists($campaignType->templateCopyPaste . '.txt', $params);

		$vars = array(
			'html' => trim($html),
			'text' => trim($text),
		);

		$response          = new SproutEmail_ResponseModel();
		$response->success = true;

		$response->content = craft()->templates->render('sproutemail/settings/mailers/copypaste/prepare', $vars);

		return $response;
	}
}
