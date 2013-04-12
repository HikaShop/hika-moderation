<?php

class KunenaHikaModerationHelper extends KunenaForumTopicHelper {

	static public function getModerationTopics($categories=false, $limitstart=0, $limit=0, $params=array()) {
		KUNENA_PROFILER ? KunenaProfiler::instance()->start('function '.__CLASS__.'::'.__FUNCTION__.'()') : null;
		$db = JFactory::getDBO ();
		$config = KunenaFactory::getConfig ();
		if ($limit < 1 && empty($params['nolimit'])) $limit = $config->threads_per_page;

		$reverse = isset($params['reverse']) ? (int) $params['reverse'] : 0;
		$orderby = isset($params['orderby']) ? (string) $params['orderby'] : 'tt.last_post_time DESC';
		$starttime = isset($params['starttime']) ? (int) $params['starttime'] : 0;
		$user = isset($params['user']) ? KunenaUserHelper::get($params['user']) : KunenaUserHelper::getMyself();
		$hold = isset($params['hold']) ? (string) $params['hold'] : 0;
		$moved = isset($params['moved']) ? (string) $params['moved'] : 0;
		$where = isset($params['where']) ? (string) $params['where'] : '';
		$userid = isset($params['userid']) ? (int) $params['userid'] : -1;

		if($userid < 0) {
			$user = JFactory::getUser();
			$userid = $user->id;
		}

		if (strstr('ut.last_', $orderby)) {
			$post_time_field = 'ut.last_post_time';
		} elseif (strstr('tt.first_', $orderby)) {
			$post_time_field = 'tt.first_post_time';
		} else {
			$post_time_field = 'tt.last_post_time';
		}

		$categories = KunenaForumCategoryHelper::getCategories($categories, $reverse);
		$catlist = array();
		foreach ($categories as $category) {
			$catlist += $category->getChannels();
		}
		if (empty($catlist)) {
			KUNENA_PROFILER ? KunenaProfiler::instance()->stop('function '.__CLASS__.'::'.__FUNCTION__.'()') : null;
			return array(0, array());
		}
		$catlist = implode(',', array_keys($catlist));

		$whereuser = array();
		if (!empty($params['started'])) $whereuser[] = 'ut.owner=1';
		if (!empty($params['replied'])) $whereuser[] = '(ut.owner=0 AND ut.posts>0)';
		if (!empty($params['posted'])) $whereuser[] = 'ut.posts>0';
		if (!empty($params['favorited'])) $whereuser[] = 'ut.favorite=1';
		if (!empty($params['subscribed'])) $whereuser[] = 'ut.subscribed=1';

		if ($config->keywords || $config->userkeywords) {
			$kwids = array();
			if (!empty($params['keywords'])) {
				$keywords = KunenaKeywordHelper::getByKeywords($params['keywords']);
				foreach ($keywords as $keyword) {
					$kwids[] = $keyword->$id;
				}
				$kwids = implode(',', $kwids);
			}
			//TODO: add support for keywords (example:)
			/* SELECT tt.*, COUNT(*) AS score FROM #__kunena_keywords_map AS km
			INNER JOIN #__kunena_topics` AS tt ON km.topic_id=tt.id
			WHERE km.keyword_id IN (1,2) AND km.user_id IN (0,62)
			GROUP BY topic_id
			ORDER BY score DESC, tt.last_post_time DESC */
		}

		$wheretime = ($starttime ? " AND {$post_time_field}>{$db->Quote($starttime)}" : '');
		$whereuser = ($whereuser ? " AND ut.user_id={$db->Quote($user->userid)} AND (".implode(' OR ',$whereuser).')' : '');
		$where = "tt.hold IN ({$hold}) AND tt.category_id IN ({$catlist}) {$whereuser} {$wheretime} {$where}";
		if (!$moved) $where .= " AND tt.moved_id='0'";

		// Get total count
		if ($whereuser)
			$query = "SELECT COUNT(*) FROM #__kunena_user_topics AS ut INNER JOIN #__kunena_topics AS tt ON tt.id=ut.topic_id WHERE {$where}";
		else
			$query = "SELECT COUNT(*) FROM #__kunena_topics AS tt WHERE {$where}";
		$db->setQuery ( $query );
		$total = ( int ) $db->loadResult ();
		if (KunenaError::checkDatabaseError() || !$total) {
			KUNENA_PROFILER ? KunenaProfiler::instance()->stop('function '.__CLASS__.'::'.__FUNCTION__.'()') : null;
			return array(0, array());
		}

		// If out of range, use last page
		if ($limit && $total < $limitstart)
			$limitstart = intval($total / $limit) * $limit;

		// Get items
		if ($whereuser)
			$query = "SELECT tt.*, (tt.moderation_affected = ".$userid.") as affectedToMe, ut.posts AS myposts, ut.last_post_id AS my_last_post_id, ut.favorite, tt.last_post_id AS lastread, 0 AS unread
				FROM #__kunena_user_topics AS ut
				INNER JOIN #__kunena_topics AS tt ON tt.id=ut.topic_id
				WHERE {$where} ORDER BY {$orderby}";
		else
			$query = "SELECT tt.*, (tt.moderation_affected = ".$userid.") as affectedToMe, ut.posts AS myposts, ut.last_post_id AS my_last_post_id, ut.favorite, tt.last_post_id AS lastread, 0 AS unread
				FROM #__kunena_topics AS tt
				LEFT JOIN #__kunena_user_topics AS ut ON tt.id=ut.topic_id AND ut.user_id={$db->Quote($user->userid)}
				WHERE {$where} ORDER BY {$orderby}";
		$db->setQuery ( $query, $limitstart, $limit );
		$results = (array) $db->loadAssocList ('id');
		if (KunenaError::checkDatabaseError()) {
			KUNENA_PROFILER ? KunenaProfiler::instance()->stop('function '.__CLASS__.'::'.__FUNCTION__.'()') : null;
			return array(0, array());
		}

		$topics = array();
		foreach ( $results as $id=>$result ) {
			$instance = new KunenaForumTopic ($result);
			$instance->exists(true);
			KunenaForumTopicHelper::$_instances [$id] = $instance;
			$topics[$id] = $instance;
		}
		unset ($results);
		KUNENA_PROFILER ? KunenaProfiler::instance()->stop('function '.__CLASS__.'::'.__FUNCTION__.'()') : null;
		return array($total, $topics);
	}
}