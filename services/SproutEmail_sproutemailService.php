<?php
namespace Craft;

/**
 * SproutEmail service
 */
class SproutEmail_sproutemailService extends SproutEmail_EmailProviderService implements SproutEmail_EmailProviderInterfaceService
{
	/**
	 * N/A
	 * 
	 * @return multitype:|multitype:NULL
	 */	
	public function getSubscriberList()
	{
		return array();
	}
	
	/**
	 * Exports campaign (no send)
	 *
	 * @param array $campaign
	 * @param array $listIds
	 */
	public function exportCampaign($campaign = array(), $listIds = array())
	{
		die('Not supported for this provider.');
	}
	
	/**
	 * Exports campaign (with send)
	 *
	 * @param array $campaign
	 * @param array $listIds
	 */
	public function sendCampaign($campaign = array(), $listIds = array())
	{		
		$emailData = array(
				'fromEmail'         => $campaign->fromEmail,
				'fromName'          => $campaign->fromName,
				'subject'           => $campaign->subject,
				'body'              => $campaign->textBody,
				'htmlBody'          => $campaign->textBody
		);
		$recipients = explode("\r\n", $campaign->recipients);
		// Craft::dump($emailData);Craft::dump($recipients);die('<br/>To disable test mode and send emails, remove line 46 in ' . __FILE__);
		$emailModel = EmailModel::populateModel($emailData);
		
		foreach($recipients as $recipient)
		{
			$emailModel->toEmail = $recipient;
			craft()->email->sendEmail($emailModel);
		}
	}

	/**
	 * Save local recipient list
	 * @param object $campaign
	 * @param object $campaignRecord
	 * @return boolean
	 */
	public function saveRecipientList(SproutEmail_CampaignModel &$campaign, SproutEmail_CampaignRecord &$campaignRecord)
	{
		$recipientIds = array();
			
		// parse and create individual recipients as needed
		if( ! $recipients = array_filter(explode("\r\n", $campaign->recipients)))
		{
			$campaign->addError('recipients', 'You must add at least one valid email.');
			return false;
		}
	
		// validate emails
		foreach($recipients as $email)
		{
			$recipientRecord = new SproutEmail_RecipientRecord();
	
			$recipientRecord->email = $email;
			$recipientRecord->validate();
			if($recipientRecord->hasErrors())
			{
				$campaign->addError('recipients', 'Once or more of listed emails are not valid.');
				return false;
			}
		}
	
		$campaignRecord->recipients = implode("\r\n", $recipients);
		$campaignRecord->save();
		
		$this->cleanUpRecipientListOrphans($campaignRecord);
		
		return true;
	}
	
	public function cleanUpRecipientListOrphans(&$campaignRecord)
	{
		// since sproutemail service does not maintain recipient lists, all lists for this 
		// campaign are considered to be orphans
		return craft()->db->createCommand()->delete('sproutemail_campaign_recipient_lists', array('campaignId' => $campaignRecord->id));
	}
	
	/**
	 * Deletes recipients for specified campaign
	 * 
	 * @param SproutEmail_CampaignRecord $campaignRecord
	 * @return bool
	 */
	public function deleteRecipients(SproutEmail_CampaignRecord $campaignRecord)
	{
		// no external recipient list for this service
		return true;
	}
}