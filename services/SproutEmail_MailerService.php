<?php
namespace Craft;

/**
 * Mailer plugin manager service
 *
 * Class SproutEmail_MailerService
 *
 * @package Craft
 */
class SproutEmail_MailerService extends BaseApplicationComponent
{
	/**
	 * Sprout Email file configs
	 *
	 * @var array
	 */
	protected $configs;

	/**
	 * @var SproutEmailBaseMailer[]
	 */
	protected $mailers;

	/**
	 * Loads all mailers for later use
	 */
	public function init()
	{
		$this->mailers = $this->getMailers();
	}

	/**
	 * Returns whether or not the mailer is installed
	 *
	 * @param $name
	 *
	 * @return bool
	 */
	public function isInstalled($name)
	{
		$record = $this->getMailerRecordByName($name);

		return ($record && $record->name == $name);
	}

	/**
	 * Returns all the available email services that sprout email can use
	 *
	 * @param bool $installedOnly
	 * @param bool $includeMailersNotYetLoaded
	 *
	 * @return SproutEmailBaseMailer[]
	 */
	public function getMailers($installedOnly = false, $includeMailersNotYetLoaded = false)
	{
		if (is_null($this->mailers) || $includeMailersNotYetLoaded)
		{
			$responses = craft()->plugins->call('defineSproutEmailMailers');

			if ($responses)
			{
				foreach ($responses as $plugin => $mailers)
				{
					if (is_array($mailers) && count($mailers))
					{
						foreach ($mailers as $name => $mailer)
						{
							if (!$installedOnly || $mailer->isInstalled())
							{
								// Prioritize built in mailers
								$mailers = $this->mailers;

								if ($this->mailerExists($mailer->getId(), $mailers))
								{
									continue;
								}

								$this->mailers[$mailer->getId()] = $mailer;
							}
						}
					}
				}
			}
		}

		return $this->mailers;
	}

	/**
	 * Return a list of installed/registered mailers ready for use
	 *
	 * @return SproutEmailBaseMailer[]
	 */
	public function getInstalledMailers()
	{
		return $this->getMailers(true);
	}

	/**
	 * @param string $name
	 * @param bool   $includeMailersNotYetLoaded
	 *
	 * @return SproutEmailBaseMailer|null
	 */
	public function getMailerByName($name, $includeMailersNotYetLoaded = false)
	{
		if ($includeMailersNotYetLoaded)
		{
			$this->mailers = $this->getMailers(false, true);
		}

		return isset($this->mailers[$name]) ? $this->mailers[$name] : null;
	}

	/**
	 * @param $name
	 *
	 * @return SproutEmail_MailerRecord
	 */
	public function getMailerRecordByName($name)
	{
		return SproutEmail_MailerRecord::model()->findByAttributes(array('name' => $name));
	}

	/**
	 * @param $name
	 *
	 * @return Model|null
	 */
	public function getSettingsByMailerName($name)
	{
		$settings = null;

		if (is_null($this->configs))
		{
			$this->configs = craft()->config->get('sproutEmail');
		}

		if (isset($this->configs['apiSettings'][$name]))
		{
			$configs = $this->configs['apiSettings'][$name];
		}

		if (($mailer = $this->getMailerByName($name, true)))
		{
			$settings = new Model($mailer->defineSettings());
		}

		if ($mailer)
		{
			$record           = $this->getMailerRecordByName($name);
			$settingsFromDb   = isset($record->settings) ? $record->settings : array();
			$settingsFromFile = isset($configs) ? $configs : array();

			$settings->setAttributes(array_merge($settingsFromDb, $settingsFromFile));
		}

		return $settings;
	}

	/**
	 * Returns a link to the control panel section for the mailer passed in
	 *
	 * @param SproutEmailBaseMailer $mailer
	 *
	 * @deprecate Deprecated for 0.9.0 in favour of BaseMailer API
	 *
	 * @return string|\Twig_Markup
	 */
	public function getMailerCpSectionLink(SproutEmailBaseMailer $mailer)
	{
		$vars = array(
			'name'        => $mailer->getId(),
			'title'       => $mailer->getTitle(),
			'sproutemail' => UrlHelper::getCpUrl('sproutemail'),
		);

		$template = '<a href="{sproutemail}/{name}" title="{title}">{title}</a>';

		try
		{
			$link = craft()->templates->renderObjectTemplate($template, $vars);

			return TemplateHelper::getRaw($link);
		}
		catch (\Exception $e)
		{
			sproutEmail()->error('Unable to create Control Panel Section link for {name}', $vars);

			return $mailer->getTitle();
		}
	}

	public function getRecipientLists($mailer)
	{
		$mailer = $this->getMailerByName($mailer);

		if ($mailer)
		{
			return $mailer->getRecipientLists();
		}

		return false;
	}

	/**
	 * @param $mailerName
	 * @param $emailId
	 * @param $campaignId
	 *
	 * @return array(content => '', actions => array())
	 * @throws Exception
	 */
	public function getPrepareModal($mailerName, $emailId, $campaignId)
	{
		$mailer = $this->getMailerByName($mailerName);

		if (!$mailer)
		{
			throw new Exception(Craft::t('No mailer with id {id} was found.', array('id' => $mailerName)));
		}

		$campaignEmail = sproutEmail()->campaignEmails->getCampaignEmailById($emailId);
		$campaign      = sproutEmail()->campaignTypes->getCampaignTypeById($campaignId);
		$response      = new SproutEmail_ResponseModel();

		if ($campaignEmail && $campaign)
		{
			try
			{
				$response->success = true;
				$response->content = $mailer->getPrepareModalHtml($campaignEmail, $campaign);

				return $response;
			}
			catch (\Exception $e)
			{
				$response->success = false;
				$response->message = $e->getMessage();

				return $response;
			}
		}
		else
		{
			$name              = $mailer->getTitle();
			$response->success = false;
			$response->message = "<h1>$name</h1><br><p>" . Craft::t('No actions available for this campaign entry.') . "</p>";
		}

		return $response;
	}

	/**
	 * @param $mailerName
	 * @param $emailId
	 * @param $campaignId
	 *
	 * @return array(content => '', actions => array())
	 * @throws Exception
	 */
	public function getPreviewModal($mailerName, $emailId, $campaignId)
	{
		$mailer = $this->getMailerByName($mailerName);

		if (!$mailer)
		{
			throw new Exception(Craft::t('No mailer with id {id} was found.', array('id' => $campaign->mailer)));
		}

		$campaignEmail = sproutEmail()->campaignEmails->getCampaignEmailById($emailId);
		$campaign      = sproutEmail()->campaignTypes->getCampaignTypeById($campaignId);
		$response      = new SproutEmail_ResponseModel();

		if ($campaignEmail && $campaign)
		{
			$response->content = $mailer->getPreviewModalHtml($campaignEmail, $campaign);

			return $response;
		}
		else
		{
			$name = $mailer->getTitle();

			$response->content = "<h1>$name</h1><br><p>" . Craft::t('No actions available for this campaign entry.') . "</p>";
		}

		return $response;
	}

	public function includeMailerModalResources()
	{
		craft()->templates->includeCssResource('sproutemail/css/modal.css');

		$mailers = $this->getInstalledMailers();

		if (count($mailers))
		{
			foreach ($mailers as $mailer)
			{
				$mailer->includeModalResources();
			}
		}
	}

	/**
	 * @param SproutEmail_CampaignTypeModel  $campaign
	 * @param SproutEmail_CampaignEmailModel $campaignEmail
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \Exception
	 */
	public function saveRecipientLists($mailer = 'defaultmailer', $campaignEmail)
	{
		sproutEmail()->campaignEmails->deleteRecipientListsByEmailId($campaignEmail->id);

		$lists = $mailer->prepareRecipientLists($campaignEmail);

		if ($lists && is_array($lists) && count($lists))
		{
			foreach ($lists as $list)
			{
				$record = SproutEmail_CampaignEmailRecipientListRecord::model()->findByAttributes(
					array(
						'emailId' => $campaignEmail->id,
						'list'    => $list->list
					)
				);
				$record = $record ? $record : new SproutEmail_CampaignEmailRecipientListRecord();

				$record->emailId = $list->emailId;
				$record->mailer  = $list->mailer;
				$record->list    = $list->list;

				try
				{
					$record->save();
				}
				catch (\Exception $e)
				{
					throw $e;
				}
			}
		}

		return true;
	}

	/**
	 * @param SproutEmail_CampaignEmailModel $campaignEmail
	 * @param SproutEmail_CampaignTypeModel  $campaign
	 *
	 * @return array
	 * @throws Exception
	 * @throws \Exception
	 */
	public function exportEmail(SproutEmail_CampaignEmailModel $campaignEmail, SproutEmail_CampaignTypeModel $campaign)
	{
		$mailer = $this->getMailerByName($campaign->mailer);

		if (!$mailer || !$mailer->isInstalled())
		{
			throw new Exception(Craft::t('No mailer with id {id} was found.', array('id' => $campaign->mailer)));
		}

		try
		{
			return $mailer->exportEmail($campaignEmail, $campaign);
		}
		catch (\Exception $e)
		{
			throw $e;
		}
	}

	/**
	 * @param SproutEmail_CampaignEmailModel $campaignEmail
	 * @param SproutEmail_CampaignTypeModel  $campaign
	 *
	 * @return array
	 * @throws Exception
	 * @throws \Exception
	 */
	public function previewCampaignEmail(SproutEmail_CampaignEmailModel $campaignEmail, SproutEmail_CampaignTypeModel $campaign)
	{
		$mailer = $this->getMailerByName($campaign->mailer);

		if (!$mailer)
		{
			throw new Exception(Craft::t('No mailer with id {id} was found.', array('id' => $campaign->mailer)));
		}

		try
		{
			return $mailer->previewCampaignEmail($campaignEmail, $campaign);
		}
		catch (\Exception $e)
		{
			throw $e;
		}
	}

	/**
	 * Installs all available mailers and their settings if not already installed
	 */
	public function installMailers()
	{
		if ($this->mailers && count($this->mailers))
		{
			foreach ($this->mailers as $mailer)
			{
				$this->installMailer($mailer->getId());
			}
		}
	}

	/**
	 * Installs the mailer and its settings if not already installed
	 *
	 * @param $name
	 *
	 * @throws Exception
	 */
	public function installMailer($name)
	{
		$vars   = array('name' => $name);
		$mailer = $this->getMailerByName($name, true);

		if (!$mailer)
		{
			throw new Exception(Craft::t('The {name} mailer is not available for installation.', $vars));
		}

		if ($mailer->isInstalled())
		{
			sproutEmail()->info('The {name} mailer is already installed.', $vars);
		}
		else
		{
			$this->createMailerRecord($mailer);
		}
	}

	/**
	 * Installs the mailer and its settings if not already installed
	 *
	 * @param $name
	 *
	 * @throws Exception
	 */
	public function uninstallMailer($name)
	{
		$vars   = array('name' => $name);
		$mailer = $this->getMailerByName($name, true);

		$builtInMailers = array('copypaste', 'campaignmonitor', 'mailchimp');

		// Do not remove builtin mailers settings
		if (in_array($name, $builtInMailers))
		{
			return false;
		}

		if (!$mailer)
		{
			throw new Exception(Craft::t('The {name} mailer was not found.', $vars));
		}

		if (!$mailer->isInstalled())
		{
			sproutEmail()->info('The {name} mailer is not installed, no need to uninstall.', $vars);
		}
		else
		{
			$this->deleteMailerRecord($mailer->getId());
		}
	}

	/**
	 * Creates a new record for a mailer with its name and settings
	 *
	 * @param SproutEmailBaseMailer $mailer
	 *
	 * @return bool
	 */
	protected function createMailerRecord(SproutEmailBaseMailer $mailer)
	{
		$record = new SproutEmail_MailerRecord();

		$record->setAttribute('name', $mailer->getId());
		$record->setAttribute('settings', $mailer->getSettings());

		try
		{
			return $record->save();
		}
		catch (\Exception $e)
		{
			$vars = array(
				'name'    => $mailer->getId(),
				'message' => PHP_EOL . $e->getMessage(),
			);

			sproutEmail()->error(Craft::t('Unable to install the {name} mailer.{message}', $vars));
		}
	}

	/**
	 * Deletes a mailer record by name
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	protected function deleteMailerRecord($name)
	{
		$record = $this->getMailerRecordByName($name);

		if (!$record)
		{
			sproutEmail()->error(Craft::t('No {name} mailer record to delete.', array('name' => $name)));

			return false;
		}

		try
		{
			return $record->delete();
		}
		catch (\Exception $e)
		{
			$vars = array(
				'name'    => $name,
				'message' => PHP_EOL . $e->getMessage(),
			);

			sproutEmail()->error(Craft::t('Unable to delete the {name} mailer record.{message}', $vars));
		}
	}

	/*
	 * Check if a Mailer exists
	 *
	 * @param $mailerId key
	 * @param $mailers
	 * @return bool
	 */
	protected function mailerExists($mailerId, $mailers)
	{
		if ($mailers != null)
		{
			$mailerKeys = array_keys($mailers);

			if (in_array($mailerId, $mailerKeys))
			{
				return true;
			}
		}

		return false;
	}

	public function getCheckboxFieldValue($options)
	{
		$value = '*';

		if (isset($options))
		{
			if ($options == '')
			{
				// Uncheck all checkboxes
				$value = 'x';
			}
			else
			{
				$value = $options;
			}
		}

		return $value;
	}

	public function isArraySettingsMatch($array = array(), $options)
	{
		if ($options == '*')
		{
			return true;
		}

		if (is_array($options))
		{
			$intersect = array_intersect($array, $options);

			if (!empty($intersect))
			{
				return true;
			}
		}

		return false;
	}
}
