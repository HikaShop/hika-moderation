<?php
/**
 * Kunena Component
 * @package Kunena.Site
 * @subpackage Controllers
 *
 * @copyright (C) 2008 - 2012 Kunena Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link http://www.kunena.org
 **/
defined ( '_JEXEC' ) or die ();

require_once KPATH_SITE . '/controllers/topic.php';

/**
 *
 */
class KunenaControllerTopicmoderate extends KunenaControllerTopic {

	/**
	 *
	 */
	public function __construct($config = array()) {
		$moderator = false;

		JPluginHelper::importPlugin('kunena');
		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger('onKunenaModerateIsModerator', array(&$moderator));

		$this->catid = JRequest::getInt('catid', 0);
		$this->return = JRequest::getInt('return', $this->catid);
		$this->id = JRequest::getInt('id', 0);
		$this->mesid = JRequest::getInt('mesid', 0);

		$this->moderator = $moderator;
	}

	/**
	 *
	 */
	public function close() {
		if($this->moderator && JRequest::checkToken('request')) {
			$topic = KunenaForumTopicHelper::get($this->id);
			$topic->moderation_open = 0;
			$topic->save();
		}
		$this->redirectBack();
	}

	/**
	 *
	 */
	public function open() {
		if($this->moderator && JRequest::checkToken('request')) {
			$topic = KunenaForumTopicHelper::get($this->id);
			$topic->moderation_open = 1;
			$topic->save();
		}
		$this->redirectBack();
	}

	/**
	 *
	 */
	public function assign() {
		if($this->moderator && JRequest::checkToken('request')) {
			$assignTo = JRequest::getInt('moderate_affected', 0);
			$topic = KunenaForumTopicHelper::get($this->id);
			$topic->moderation_affected = $assignTo;
			$topic->save();
		}
		$this->redirectBack();
	}

	/**
	 *
	 */
	public function priority() {
		if($this->moderator && JRequest::checkToken('request')) {
			$priority = JRequest::getInt('moderate_priority', 0);
			$topic = KunenaForumTopicHelper::get($this->id);
			$topic->moderation_priority = $priority;
			$topic->save();
		}
		$this->redirectBack();
	}

	/**
	 *
	 */
	public function comment() {
		if($this->moderator && JRequest::checkToken('request')) {
			$comments = JRequest::getVar('comments', '');
			$topic = KunenaForumTopicHelper::get($this->id);
			$topic->moderation_comments = $comments;
			$topic->save();
		}
		$this->redirectBack();
	}

	/**
	 *
	 */
	public function ban() {
		if($this->moderator) {
			$topic = KunenaForumTopicHelper::get($this->id);

			$userid = JRequest::getInt('userid', 0);
			if(empty($userid))
				$userid = $topic->first_post_userid;

			$user = KunenaFactory::getUser($userid);
			if(!$user->exists() || !JRequest::checkToken('request')) {
				// $this->app->redirect ( $user->getUrl(false), COM_KUNENA_ERROR_TOKEN, 'error' );
				$this->app->enqueueMessage ( JText::_('COM_KUNENA_ERROR_TOKEN'), 'error' );
				$this->redirectBack();
				return;
			}

			$moderator = false;

			JPluginHelper::importPlugin('kunena');
			$dispatcher = JDispatcher::getInstance();
			$dispatcher->trigger('onKunenaModerateIsModerator', array(&$moderator, $user->userid));

			if($moderator) {
				$this->app->enqueueMessage ( JText::_('COM_KUNENA_ERROR_BAN_MODERATOR_USER'), 'error' );
				$this->redirectBack();
				return;
			}

			$ban = new KunenaModerateUserBan();
			$ban->loadByUserid($user->userid);
			if (!$ban->id) {
				$ban->ban($user->userid, null, 1);
				$success = $ban->save();
			}
			list($total, $messages) = KunenaForumMessageHelper::getLatestMessages(false, 0, 0, array('starttime'=> '-1','user' => $user->userid));
			foreach($messages as $mes) {
				$mes->publish(KunenaForum::DELETED);
			}
			$this->app->enqueueMessage ( JText::_('COM_KUNENA_MODERATE_DELETED_BAD_MESSAGES') );
		}
		$this->redirectBack();
	}
}

/**
 *
 */
class KunenaModerateUserBan extends KunenaUserBan {

	/**
	 *
	 */
	public function canBan() {
		return true;
	}
}