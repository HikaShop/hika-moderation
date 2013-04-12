<?php
defined ( '_JEXEC' ) or die ();

jimport('joomla.utilities.string');

class KunenaActivityHikaModeration extends KunenaActivity {

	/**
	 *
	 */
	protected $params = null;

	/**
	 *
	 */
	protected $moderator = false;
	protected $moderators = array();
	protected $categories = array();
	protected $assignations = array();

	/**
	 *
	 */
	public function __construct($params, $moderator, $categories, $assignations, $moderators) {
		$this->params = $params;
		$this->moderator = $moderator;
		$this->categories = $categories;
		$this->assignations = $assignations;
		$this->moderators = $moderators;
	}

	/**
	 *
	 */
	public function onBeforePost($message) {
		if ( $this->_checkPermissions($message) ) {
			$category = $message->getCategory();
			$topic = $message->getTopic();
			if(in_array($category->id, $this->categories)) {
				if($this->moderator) {
					$topic->moderation_open = 0;
				} else {
					$topic->moderation_open = 1;
					if(isset($this->assignations[$category->id])) {
						$topic->moderation_affected = $this->assignations[$category->id];
					}
				}
			} else {
				$topic->moderation_open = 0;
			}

			if(stripos($message->subject, 'urgent'))
				$topic->moderation_priority = 2;

			$this->spamCheck($message, true);
		}
		return true;
	}

	/**
	 *
	 */
	public function onBeforeReply($message) {
		if ( $this->_checkPermissions($message) ) {
			$category = $message->getCategory();
			$topic = $message->getTopic();
			if(in_array($category->id, $this->categories)) {
				if($this->moderator)
					$topic->moderation_open = 0;
				else
					$topic->moderation_open = 1;
			} else {
				$topic->moderation_open = 0;
			}
			$this->spamCheck($message, false);
		}
		return true;
	}

	/**
	 *
	 */
	public function onBeforeEdit($message) {
		if ( $this->_checkPermissions($message) ) {
			$category = $message->getCategory();
			$topic = $message->getTopic();
			if(in_array($category->id, $this->categories)) {
				if(!$this->moderator)
					$topic->moderation_open = 1;
			} else {
				$topic->moderation_open = 0;
			}

			$this->spamCheck($message, false, true);
		}
		return true;
	}

	/**
	 *
	 */
	private function spamCheck($message, $newTopic, $edit = false) {
		if($this->moderator)
			return;

		$spam = false;
		$topic = $message->getTopic();

		if(strpos($message->message, 'href=') !== false) {
			$user = KunenaFactory::getUser($message->userid);
			if($user->posts == 0 || ($edit && $user->posts == 1)) {
				if(strpos($message->message, '<a href=http') !== false) {
					$spam = true;
				}

				if($spam) {
					require_once KPATH_SITE . '/controllers/topicmoderate.php';
					$ban = new KunenaModerateUserBan();
					$ban->loadByUserid($user->userid);
					$ban->ban($user->userid, null, 1);
					$success = $ban->save();
				}
			}
		}

		if($spam) {
			$app = JFactory::getApplication();
			$app->enqueueMessage('Your message has been considered as spam. If it was an error, please contact spam-error@hikashop.com and give us your username', 'error');

			if($newTopic)
				$topic->hold = 2;
			$message->hold = 2;
		}
	}

	/**
	 *
	 */
	public function onAfterPost($message) {
	}

	/**
	 *
	 */
	public function onAfterReply($message) {
	}

	/**
	 *
	 */
	public function onAfterDelete($message) {
	}

	/**
	 *
	 */
	public function onAfterThankyou($target, $actor, $message) {
		//$infoTargetUser = (JText::_ ( 'COM_KUNENA_THANKYOU_GOT' ).': ' . KunenaFactory::getUser($target)->username );
		//$infoRootUser = ( JText::_ ( 'COM_KUNENA_THANKYOU_SAID' ).': ' . KunenaFactory::getUser($actor)->username );
	}

	/**
	 *
	 */
	function escape($var) {
		return htmlspecialchars ( $var, ENT_COMPAT, 'UTF-8' );
	}

	/**
	 *
	 */
	public function getUserMedals($userid) {
		if ($userid == 0 || !$this->moderator) {
		//	return false;
			return $this->guestGetUserMedals($userid);
		}

		return $this->moderatorGetUserMedals($userid);
	}

	/**
	 *
	 */
	protected function moderatorGetUserMedals($userid) {
		if ($userid == 0 || !$this->moderator)
			return false;

		if (!$this->params->get('badges', 0))
			return;

		$medals = array ();
		$db = JFactory::getDBO();
		$db->setQuery('SELECT component, level, (maintenance > '.time().') as `alive` FROM `#__update_license` WHERE userid='.(int)$userid.' AND type=\'live\'');
		$dbLicenses = $db->loadObjectList();
		$licenses = array();
		foreach($dbLicenses as $dbLicense) {
			$k = $dbLicense->component . ' ' . $dbLicense->level;
			if(empty($licenses[$k])) {
				$licenses[$k] = array('valid' => 0, 'invalid' => 0);
			}
			if($dbLicense->alive) {
				$licenses[$k]['valid']++;
			} else {
				$licenses[$k]['invalid']++;
			}
		}

		foreach($licenses as $license => $report) {
			if($report['valid'] > 0) {
				if($report['valid'] > 1) {
					$medals[] = '<span class="kicon-button kbuttonlicense"><span class="license"><span>' . $license . ' x'.$report['valid'].'</span></span></span>';
				} else {
					$medals[] = '<span class="kicon-button kbuttonlicense"><span class="license"><span>' . $license . '</span></span></span>';
				}
			}
			if($report['invalid'] > 0) {
				if($report['invalid'] > 1) {
					$medals[] = '<span class="kicon-button kbuttononline-no"><span class="online-no"><span>' . $license . ' x'.$report['invalid'].'</span></span></span>';
				} else {
					$medals[] = '<span class="kicon-button kbuttononline-no"><span class="online-no"><span>' . $license . '</span></span></span>';
				}
			}
		}

		return $medals;
	}

	/**
	 *
	 */
	protected function guestGetUserMedals($userid) {
		if (!$this->params->get('badges', 0))
			return;

		$moderator = in_array($userid, $this->moderators);
		$medals = array ();

		if(!$moderator) {
			$db = JFactory::getDBO();
			$db->setQuery('SELECT component, level FROM `#__update_license` WHERE userid='.(int)$userid.' AND type=\'live\' AND (maintenance > '.time().') GROUP BY component, level');
			$dbLicenses = $db->loadObjectList();
			foreach($dbLicenses as $license) {
				$medals[] = '<span class="kicon-button kbuttonlicense"><span class="license"><span>' . $license->component . ' ' . $license->level . '</span></span></span>';
			}
		} else {
			$medals[] = '<span class="kicon-button kbuttononline-no"><span class="online-no"><span>' . JText::_('MODERATOR') . '</span></span></span>';
		}
		return $medals;
	}

	/**
	 *
	 */
	private function _checkPermissions($message) {
		$category = $message->getCategory();
		$accesstype = $category->accesstype;
		if ($accesstype != 'joomla.group' && $accesstype != 'joomla.level') {
			return false;
		}
		if (version_compare(JVERSION, '1.6','>')) {
			// FIXME: Joomla 1.6 can mix up groups and access levels
			if ($accesstype == 'joomla.level' && $category->access <= 2) {
				return true;
			} elseif ($category->pub_access == 1 || $category->pub_access == 2) {
				return true;
			} elseif ($category->admin_access == 1 || $category->admin_access == 2) {
				return true;
			}
			return false;
		} else {
			// Joomla access levels: 0 = public,  1 = registered
			// Joomla user groups:  29 = public, 18 = registered
			if ($accesstype == 'joomla.level' && $category->access <= 1) {
				return true;
			} elseif ($category->pub_access == 0 || $category->pub_access == - 1 || $category->pub_access == 18 || $category->pub_access == 29) {
				return true;
			} elseif ($category->admin_access == 18 || $category->admin_access == 29) {
				return true;
			}
			return false;
		}
	}
}