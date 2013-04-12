<?php
defined ( '_JEXEC' ) or die ();

define('HIKAMODERATION_J30', version_compare(JVERSION,'3.0.0','>=') ? true : false);

class plgKunenaHikamoderation extends JPlugin {

	protected $moderator = false;
	protected $user_id = 0;
	protected $users = array();
	protected $moderators = array();
	protected $categories = array();
	protected $categories_assignations = array();

	/**
	 *
	 */
	public function __construct(&$subject, $config) {
		// Do not load if Kunena version is not supported or Kunena is offline
		if(!(class_exists('KunenaForum') && KunenaForum::isCompatible('2.0') && KunenaForum::installed()))
			return;

		parent::__construct($subject, $config);

		$this->loadLanguage('plg_kunena_hikamoderation.sys', JPATH_ADMINISTRATOR ) || $this->loadLanguage('plg_kunena_hikamoderation.sys', KPATH_ADMIN);
		//$this->loadLanguage('plg_kunena_hikamoderation.sys', dirname(__FILE__).'/language/' );
		$this->path = dirname(__FILE__).'/hikamoderation';

		$validUsers = explode(',', $this->params->get('users', '42:admin'));
		$validIds = array();
		$this->users = array();
		foreach($validUsers as $data) {
			if(strpos($data, ':') !== false)
				list($id, $u) = explode(':', $data, 2);
			else {
				$id = (int)$data;
				$u = 'User ' . $id;
			}
			$validIds[] = (int)$id;
			$this->users[(int)$id] = $u;
		}

		$currentUser = JFactory::getUser();
		$this->moderator = false;
		$this->user_id = 0;
		if(in_array($currentUser->id, $validIds)) {
			$this->moderator = true;
			$this->user_id = $currentUser->id;
		}
		$this->moderators = $validIds;

		$catAssignations = array();
		$categories = explode(',', $this->params->get('categories', '1,2'));
		foreach($categories as &$category) {
			$user = 0;
			if(strpos($category, ':') !== false) {
				list($category, $user) = explode(':', $category, 2);
				$user = (int)trim($user);
				if($user > 0) {
					$catAssignations[$category] = $user;
				}
			}
		}
		$this->categories = $categories;
		$this->categories_assignations = $catAssignations;
	}

	/**
	 *
	 */
	public function onKunenaModerateIsModerator(&$moderator, $userid = 0) {
		if($userid == 0)
			$moderator = $this->moderator;
		else
			$moderator = in_array($userid, $this->moderators);
	}

	/*
	public function onKunenaGetProfile() {
		if (!$this->moderator || !$this->params->get('profile', 1))
			return;

		require_once $this->path.'/profile.php';
		return new KunenaProfileHikaModeration($this->params);
	}
	*/

	/**
	 *
	 */
	public function onKunenaGetActivity() {
		if (!$this->params->get('activity', 1))
			return;

		require_once $this->path.'/activity.php';
		return new KunenaActivityHikaModeration($this->params, $this->moderator, $this->categories, $this->categories_assignations, $this->moderators);
	}

	/**
	 *
	 */
	public function onKunenaGetButtons($type, &$buttons, $view) {
		if(!$this->moderator)
			return;

		$catid = $view->state->get('item.catid');

		switch($type) {
			case 'message.action':
				$id = $view->topic->id;
				break;
			case 'topic.action':
				$id = $view->state->get('item.id');
				break;
			default:
				return;
		}

		$token = (HIKAMODERATION_J30 ? JSession::getFormToken() : JUtility::getToken());

		if($type == 'message.action') {
			//
			// Message
			//
			$deleteBtns = $buttons->get('delete');
			$task = 'index.php?option=com_kunena&view=topicmoderate&task=%s&catid='.$catid.'&id='.$id.'&msgid='.$view->message->id.'&userid='.$view->message->userid.'&' . $token . '=1';
			if(!in_array($view->message->userid, $this->moderators)) 
				$deleteBtns .= ' ' . $view->getButton(sprintf($task, 'ban'), 'ban', 'topic', 'moderation');
			$buttons->set('delete', $deleteBtns);
		}

		if(!in_array($catid, $this->categories)) {
			return;
		}

		$doc = JFactory::getDocument();
		static $cssAdded = false;
		if(!$cssAdded) {
			$cssAdded = true;
			$doc->addStyleDeclaration('
#Kunena .kbuttoncomm span.openmoderation,
#Kunena .kbuttoncomm span.closemoderation {
	background-position: 0px -400px;
}
#Kunena .kbuttonuser span.openmoderation,
#Kunena .kbuttonuser span.closemoderation {
	background-position: 0px -300px;
}
#Kunena .kbuttonmod span.ban {
	background-position: 0px -40px;
}
#Kunena .klist-actions form {
	display:inline;
	margin-right:5px;
}
#Kunena .klist-actions select {
	font-size:10px;
}
#Kunena .klist-actions input {
	font-size:9px;
	border:1px solid black;
	margin:0px;
}
#Kunena .klist-actions input:hover,
#Kunena .klist-actions input:focus {
    background-color: rgb(96, 159, 191);
    border-color: rgb(83, 136, 180);
    color:#ffffff;
}
');
		}

		//
		// Topic
		//
		if($type == 'topic.action') {

			$task = 'index.php?option=com_kunena&view=topicmoderate&task=%s&catid='.$catid.'&id='.$id.'&' . $token . '=1';
			
			//
			//
			$replyBtns = $buttons->get('reply');
			if($view->topic->moderation_open) {
				$replyBtns .= ' ' . $view->getButton(sprintf($task, 'close'), 'closemoderation', 'topic', 'communication');
			} else {
				$replyBtns .= ' ' . $view->getButton(sprintf($task, 'open'), 'openmoderation', 'topic', 'user');
			}
			$buttons->set('reply', $replyBtns);

			//
			//
			$moderateBtns = $buttons->get('moderate');
			$users = array('<option value="0">-</option>');
			foreach($this->users as $k => $u) {
				if($view->topic->moderation_affected == $k)
					$users[] = '<option selected="selected" value="'.$k.'">'.$u.'</option>';
				else
					$users[] = '<option value="'.$k.'">'.$u.'</option>';
			}
			$moderateBtns .= ' <form method="post" action="'.sprintf($task, 'assign').'"><select name="moderate_affected">'.implode('', $users).'</select> <input type="submit" value="Assign"/></form>';

			$prioritiesText = array( -1 => 'Low', 0 => 'Normal', 1 => 'High', 2 => 'Urgent' );
			$priorities = array();
			foreach($prioritiesText as $i => $text) {
				if($view->topic->moderation_priority == $i)
					$priorities[] = '<option selected="selected" value="'.$i.'">'.$text.'</option>';
				else
					$priorities[] = '<option value="'.$i.'">'.$text.'</option>';
			}
			$moderateBtns .= ' <form method="post" action="'.sprintf($task, 'priority').'"><select name="moderate_priority">'.implode('', $priorities).'</select> <input type="submit" value="Change"/></form>';
			$buttons->set('moderate', $moderateBtns);

			//
			//
			$commentsBlock = '<form method="post" action="'.sprintf($task, 'comment').'">'.
				'<table class="klist-actions" style="margin-top:5px;margin-bottom:5px;width:100%;border:0px"><tr><td width="100%"><textarea name="comments" style="width:100%;border:1px solid grey;height:7em;" class="ksmall" placeholder="moderator comments">'.
				str_replace(array('&','<','>'),array('&amp;','&lt;','&gt;'), $view->topic->moderation_comments).
				'</textarea></td><td width="30px"><input type="submit" value="save"/></td></tr></table></form>';
			$buttons->set('comments', $commentsBlock);
		}
	}

	/**
	 *
	 */
	public function onKunenaPrepare($str, &$opt1, &$params, $int) {
		/*
		kunena.topics - list topics
		kunena.topic - display one topic
		kunena.messages - list messages
		kunena.user - display one user
		*/

		if(!$this->moderator) {
			return;
		}

		static $check = false;
		if(!$check) {
			$this->checkReading();
			$check = true;
		}

		if($str == 'kunena.topics') {
			$prioritiesText = array(
				-1 => '<span class="priorityIcon low"></span>',
				0 => '',
				1 => '<span class="priorityIcon high"></span>',
				2 => '<span class="priorityIcon urgent"></span>'
			);

			foreach($opt1 as &$opt) {
				$txt = '';
				if($opt->moderation_open) {
					$txt .= ' ' . $prioritiesText[$opt->moderation_priority] . ' ';
					if(!empty($opt->moderation_affected)) {
						if($opt->moderation_affected == $this->user_id) {
							$opt->moderation_css = 'moderationAffectedToMe';
						} else {
							$txt .= ' (' . $this->users[$opt->moderation_affected] . ')';
							$opt->moderation_css = 'moderationAffectedNotMe';
						}
					}
					$opt->moderation_text = $txt;
				}
				if(!empty($opt->moderation_reading) && $opt->moderation_reading != $this->user_id) {
					$txt .= ' <span class="moderationReading"></span> ' . $this->users[$opt->moderation_reading];
					$opt->moderation_text = $txt;
				}
				unset($opt);
			}
		}
	}

	/**
	 *
	 */
	public function onKunenaGetTopics($layout, $mode_custom, &$topics, &$total, $model) {
		if($mode_custom !== 'moderation') {
			return;
		}
		if(!$this->moderator) {
			$app = JFactory::getApplication();
			$app->redirect('index.php');
		}

		require_once $this->path.'/helper.php';

		$catid = $model->getState ( 'item.id' );
		$limitstart = $model->getState ( 'list.start' );
		$limit = $model->getState ( 'list.limit' );
		$time = $model->getState ( 'list.time' );
		if ($time < 0) {
			$time = 0;
		} elseif ($time == 0) {
			$time = KunenaFactory::getSession ()->lasttime;
		} else {
			$time = JFactory::getDate ()->toUnix () - ($time * 3600);
		}

		$latestcategory = $model->getState ( 'list.categories' );
		$latestcategory_in = $model->getState ( 'list.categories.in' );

		$currentUser = JFactory::getUser();

		$params = array (
			'reverse' => ! $latestcategory_in,
			'orderby' => 'affectedToMe DESC, moderation_priority DESC, tt.last_post_time ASC',
			'starttime' => $time,
			'hold' => 0,
			'where' => 'AND tt.moderation_open=1 AND tt.first_post_userid>0',
			'userid' => $currentUser->id
		);

		list ( $total, $topics ) = KunenaHikaModerationHelper::getModerationTopics($latestcategory, $limitstart, $limit, $params);
	}

	/**
	 *
	 */
	protected function checkReading() {
		if(!$this->moderator)
			return;

		$option = JRequest::getCmd('option');
		if($option != 'com_kunena')
			return;
		
		$ctrl = JRequest::getCmd('view');
		if(empty($ctrl))
			$ctrl = JRequest::getCmd('ctrl');

		$task = JRequest::getCmd('layout');
		if(empty($task))
			$task = JRequest::getCmd('task');

		$db = JFactory::getDBO();

		if($ctrl == 'topics' && $task == 'user') {
			$mode = JRequest::getCmd('mode');
			$modetype = JRequest::getCmd('modetype');
			if($mode == 'plugin' && $modetype == 'moderation') {
				$db->setQuery('UPDATE `#__kunena_topics` SET moderation_reading = 0, moderation_time = 0 WHERE (moderation_reading = ' . $this->user_id . ') OR (moderation_time > 0 AND moderation_time < ' . (time() - 60*15). ')');
				$db->query();
			}
		}

		if($ctrl == 'topic') {
			$catid = JRequest::getInt('catid');
			$id = JRequest::getInt('id');
			$db->setQuery('SELECT * FROM `#__kunena_topics` WHERE category_id = ' . $catid . ' AND id = ' . $id);
			$currentTopic = $db->loadObject();

			if(empty($currentTopic->moderation_reading) || !isset($this->users[(int)$currentTopic->moderation_reading])) {
				$db->setQuery('UPDATE `#__kunena_topics` SET moderation_reading = ' . $this->user_id . ', moderation_time = '.time().' WHERE category_id = ' . $catid . ' AND id = ' . $id);
				$db->query();
			} else if($currentTopic->moderation_reading != $this->user_id) {
				$userName = $this->users[(int)$currentTopic->moderation_reading];
				$app = JFactory::getApplication();
				$app->enqueueMessage(ucfirst($userName) . ' is currently reading this topic', 'error');
			}
		}
	}
}