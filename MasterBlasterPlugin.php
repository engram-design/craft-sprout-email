<?php
namespace Craft;

class MasterBlasterPlugin extends BasePlugin
{
    public function getName() 
    {
        $pluginName = Craft::t('Master Blaster');

        // The plugin name override
        $plugin = craft()->db->createCommand()
            ->select('settings')
            ->from('plugins')
            ->where('class=:class', array(':class'=> 'MasterBlaster'))
            ->queryScalar();

        $plugin = json_decode( $plugin, true );
        $pluginNameOverride = $plugin['pluginNameOverride'];

        return ($pluginNameOverride) ? $pluginNameOverride : $pluginName;
    }

    public function getVersion()
    {
        return '0.4.0';
    }

    public function getDeveloper()
    {
        return 'Barrel Strength Design';
    }

    public function getDeveloperUrl()
    {
        return 'http://barrelstrengthdesign.com';
    }

    public function hasCpSection()
    {
        return true;
    }

    protected function defineSettings()
    {
        return array(
            'pluginNameOverride' => AttributeType::String,
        );
    }

    public function getSettingsHtml()
    {
        return craft()->templates->render('masterblaster/_settings/settings', array(
            'settings' => $this->getSettings()
        ));
    }

    /**
     * Register control panel routes
     */
    public function registerCpRoutes()
    {
        return array(
            'masterblaster\/campaigns\/new' =>
            'masterblaster/campaigns/_edit',

            'masterblaster\/campaigns\/edit\/(?P<campaignId>\d+)' =>
            'masterblaster/campaigns/_edit',
        		
        	'masterblaster\/notifications\/new' =>
        	'masterblaster/notifications/_edit',
        		
        	'masterblaster\/notifications\/edit\/(?P<campaignId>\d+)' =>
        	'masterblaster/notifications/_edit',
        		
        	'masterblaster\/events\/new' =>
        	'masterblaster/events/_edit',
        		
        	'masterblaster\/events\/edit\/(?P<eventId>\d+)' =>
        	'masterblaster/events/_edit',
        );
    }
    
    /**
     * Add default events after plugin is installed
     */
    public function onAfterInstall()
    {
    	$events = array(
    			array(
    					'registrar' => 'craft',
    					'event' => 'entries.saveEntry.new',
    					'description' => 'when a new entry is created'
    			),    			
    			array(
    					'registrar' => 'craft',
    					'event' => 'entries.saveEntry',
    					'description' => 'when an existing entry is updated'
    			),
    			array(
    					'registrar' => 'craft',
    					'event' => 'users.saveUser',
    					'description' => 'when user is saved'
    			),
				array(
						'registrar' => 'craft',
    					'event' => 'users.saveProfile',
    					'description' => 'when user profile is saved'
    			),
    			array(
    					'registrar' => 'commerceAddEventListener',
    					'event' => 'checkoutEnd',
    					'description' => 'Commerce: when an order is submitted'
    			),
    	);
    
    	foreach ($events as $event) 
    	{
    		craft()->db->createCommand()->insert('masterblaster_notification_events', $event);
    	}
    }
    
    /**
     * Initialize
     * @return void
     */
    public function init()
    {
    	parent::init();

    	// events fired by $this->raiseEvent 
        craft()->on('entries.saveEntry', array($this, 'onSaveEntry'));
        craft()->on('users.saveUser', array($this, 'onSaveUser'));
        craft()->on('users.saveProfile', array($this, 'onSaveProfile'));
        craft()->on('globals.saveGlobalContent', array($this, 'onSaveGlobalContent'));
        craft()->on('assets.saveFileContent', array($this, 'onSaveFileContent'));
        craft()->on('content.saveContent', array($this, 'onSaveContent'));

        $criteria = new \CDbCriteria();
        $criteria->condition = 'registrar!=:registrar';
        $criteria->params = array(':registrar' => 'craft');
        if( $events = MasterBlaster_NotificationEventRecord::model()->findAll($criteria))
        {
        	foreach($events as $event)
        	{
        		try 
        		{
        			craft()->plugins->call($event->registrar,array($event->event, $this->_get_closure()));
        		}
        		catch (\Exception $e)
        		{
        			die($e->getMessage());
        		}
        	}
        }
        

    }
    
    /**
     * Anonymous function for plugin integration
     * @return function
     */
    private function _get_closure()
    {
    	/**
    	 * Event handler closure
    	 * @var String [required] - event fired
    	 * @var BaseModel [required] - the entity to be used for data extraction
    	 * @var Bool [optional] - event status; if passed, the function will exit on false and process on true; defaults to true
    	 */
    	return function($event, $entity, $success = TRUE)
    	{
    		// if ! $success, return
    		if( ! $success)
    		{
    			return false;
    		}

    		// validate
    		$criteria = new \CDbCriteria();
    		$criteria->condition = 'event=:event';
    		$criteria->params = array(':event' => (string) $event);     	 	

    		if( ! $event_notification = MasterBlaster_NotificationEventRecord::model()->find($criteria))
    		{
    			return false;
    		}
    		    	
    		// process $entity
    		// get registered entries
    		if($res = craft()->masterBlaster_notifications->getEventNotifications( (string) $event, $entity))
    		{
    	
    			foreach($res as $campaign)
    			{    				
    				if( ! $campaign->recipients)
    				{
    					return false;
    				}

    				// set $_POST vars
    				$post = (object) $_POST;
    				 
    				$opts = json_decode($event_notification->options);
    				 
    				if($opts && $opts->handler)
    				{
    					list($class, $function) = explode('::', $opts->handler);

    					$options = unserialize($campaign->campaignNotificationEvent[0]->options);

    					$base_classes = json_decode($opts->handler_base_classes);

						if($base_classes && ! empty($base_classes))
						{
							foreach($base_classes as $base)
							{
								require_once($base);
							}
						}

						require_once($opts->handler_location);
						
    					$obj = new $class();    					
    					if( ! method_exists($obj, $function))
    					{
    						return false;
    					}
    					
    					if( ! $obj->$function($event, $entity, $options))
    					{
    						return true;
    					}
    				}

    				try
    				{
    					$campaign->textBody = craft()->templates->renderString($campaign->textBody, array('item' => $entity, '_post' => $post));
    				}
    				catch (\Exception $e)
    				{
    					return false; // fail silently for now; something is wrong with the tpl
    				}
    				 
    				$recipientLists = array();
    				foreach($campaign->recipientList as $list)
    				{
    					$recipientLists[] = $list->emailProviderRecipientListId;
    				}
    				$service = 'masterBlaster_' . $campaign->emailProvider;
    				craft()->{$service}->sendCampaign($campaign, $recipientLists);
    			}
    		}
    	};
    }

    /**
     * Available variables:
     * all entries in 'craft_content' table
     * to access: entry.id, entry.body, entry.locale, etc.
     * @param Event $event
     */
    public function onSaveEntry(Event $event)
    {    	
    	switch($event->params['isNewEntry'])
    	{
    		case true:
    			$event_type = 'entries.saveEntry.new';
    			break;
    		default:
    			$event_type = 'entries.saveEntry';
    			break;
    	}
		$this->_processEvent($event_type, $event->params['entry']);
    }

    /**
     * Available variables:
     * all entries in 'craft_users' table
     * to access: entry.id, entry.firstName, etc.
     * @param Event $event
     */
    public function onSaveUser(Event $event)
    {
        $this->_processEvent('users.saveUser', $event->params['user']);
    }

    /**
     * Available variables:
     * all entries in 'craft_users' table
     * to access: entry.id, entry.firstName, etc.
     * @param Event $event
     */
    public function onSaveProfile(Event $event)
    {
    	$this->_processEvent('users.saveProfile', $event->params['user']);
    }

    // not implemented
    public function onSaveGlobalContent(Event $event)
    {
    	
        $this->_processEvent('globals.saveGlobalContent', $event->params['globalSet']);
    }

    // not implemented
    public function onSaveFileContent(Event $event)
    {
        $this->_processEvent('assets.saveFileContent', $event->params['file']);
    }

    /**
     * Available variables:
     * all entries in 'craft_content' table
     * to access: entry.id, entry.body, entry.locale, etc.
     * @param Event $event
     */
    public function onSaveContent(Event $event)
    {
    	switch($event->params['isNewContent'])
    	{
    		case true:
    			$event_type = 'content.saveContent.new';
    			break;
    		default:
    			$event_type = 'content.saveContent';
    			break;
    	}
        $this->_processEvent($event_type, $event->params['content']);
    }
    
    /**
     * Handle system event
     * @param string $eventType
     * @param obj $entry
     * @return boolean
     */
    private function _processEvent($eventType, $entry)
    {
    	// get registered entries
    	if($res = craft()->masterBlaster_notifications->getEventNotifications($eventType, $entry))
    	{   
    		foreach($res as $campaign)
    		{
    			if( ! $campaign->recipients)
    			{
    				return false;
    			}

                // @TODO - probably want to tighten up this code.  Would it 
                // be better to switch to do a string replace and only make
                // key variables available here? 
                // entry.author, entry.author.email, entry.title
                //
                // Add ReplyTo Email
    			
                try
                {
                    $campaign->subject = craft()->templates->renderString($campaign->subject, array('entry' => $entry));
                }
                catch (\Exception $e)
                {
                    return false; // something is wrong with the subject line
                }

                try
                {
                    $campaign->fromName = craft()->templates->renderString($campaign->fromName, array('entry' => $entry));
                }
                catch (\Exception $e)
                {
                    return false; // something is wrong with the subject line
                }

    			try
    			{
    				$campaign->textBody = craft()->templates->renderString($campaign->textBody, array('entry' => $entry));
    			}
    			catch (\Exception $e)
    			{
    				return false; // something is wrong with the tpl
    			}

    			$service = 'masterBlaster_' . $campaign->emailProvider;
    			craft()->{$service}->sendCampaign($campaign);
    		}
    	}
    }
    
}