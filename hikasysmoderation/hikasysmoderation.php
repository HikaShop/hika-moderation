<?php
defined ( '_JEXEC' ) or die ();

class plgSystemHikasysmoderation extends JPlugin {

	/**
	 *
	 */	
	public function onAfterRoute() {
		$view = JRequest::getWord ( 'func', JRequest::getWord ( 'view', 'home' ) );
		if($view == 'topicmoderate') {
			$app = JFactory::getApplication();
			if($app->isAdmin())
				return;

			$task = JRequest::getCmd ( 'task' );

			KunenaForum::setup();
			KunenaError::initialize();
			$ksession = KunenaFactory::getSession ( true );
			if ($ksession->userid > 0) {
				// Create user if it does not exist
				$kuser = KunenaUserHelper::getMyself ();
				if (! $kuser->exists ()) {
					$kuser->save ();
				}
				// Save session
				if (! $ksession->save ()) {
					JFactory::getApplication ()->enqueueMessage ( JText::_ ( 'COM_KUNENA_ERROR_SESSION_SAVE_FAILED' ), 'error' );
				}
			}

			require_once dirname(__FILE__) . '/topicmoderate.php';

			$controller = new KunenaControllerTopicmoderate();
			KunenaRoute::cacheLoad ();
			if(method_exists($controller, $task))
				$controller->$task();
			KunenaRoute::cacheStore ();
			$controller->redirect ();
			
			KunenaError::cleanup ();
			
			exit;
		}
	}
}