<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 HUBzero Foundation, LLC.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @author    Kevin Wojkovich <linuxfanatic91@gmail.com> 
 * @copyright Copyright 2005-2015 HUBzero Foundation, LLC.
 * @license   http://opensource.org/licenses/MIT MIT
 */

// No direct access
defined('_HZEXEC_') or die();

/**
 * Projects Mattermost plugin
 */
class plgProjectsChat extends \Hubzero\Plugin\Plugin
{
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 */
	protected $_autoloadLanguage = true;

	/**
	 * Custom params
	 *
	 * @var    object
	 */
	protected $_params = null;

	/**
	 * Component name
	 *
	 * @var  string
	 */
	protected $_option = 'com_projects';

	/**
	 * Store internal message
	 *
	 * @var	   array
	 */
	protected $_msg = NULL;

	/**
	 * Repository path
	 *
	 * @var	   array
	 */
	protected $_path = NULL;

	/**
	 * Event call to determine if this plugin should return data
	 *
	 * @return     array   Plugin name and title
	 */
	public function &onProjectAreas($alias = NULL)
	{
		$area = array(
			'name'    => 'chat',
			'alias'   => 'chat',
			'title'   => Lang::txt('PLG_PROJECTS_CHAT'),
			'submenu' => NULL,
			'show'    => true
		);
		return $area;
	}

	/**
	 * Event call to return count of items
	 *
	 * @param      object  $model 		Project
	 * @return     array   integer
	 */
	public function &onProjectCount( $model )
	{
		// New activity count
		$counts['new'] = $model->newCount();

		return $counts;
	}

	/**
	 * Event call to return data for a specific project
	 *
	 * @param      object  $model           Project model
	 * @param      string  $action			Plugin task
	 * @param      string  $areas  			Plugins to return data
	 * @return     array   Return array of html
	 */
	public function onProject($model, $action = '', $areas = NULL)
	{
		$returnhtml = true;

		$arr = array(
			'html'     =>'',
			'metadata' =>''
		);

		// Get this area details
		$this->_area = $this->onProjectAreas();

		// Check if our area is in the array of areas we want to return results for
		if (is_array($areas))
		{
			if (empty($this->_area) || !in_array($this->_area['name'], $areas))
			{
				return;
			}
		}
		// Check that project exists
		if (!$model->exists())
		{
			return $arr;
		}

		// Check authorization
		if (!$model->access('member'))
		{
			return $arr;
		}

		// Model
		$this->model = $model;

		// Are we returning HTML?
		if ($returnhtml)
		{
			// Set vars
			$this->_config     = $model->config();
			$this->_task       = Request::getVar('action', '');
			$this->_database   = App::get('db');
			$this->_uid        = User::get('id');

			switch ($this->_task)
			{
				case 'page':
				default:
					$arr['html'] = $this->page();
					break;
				case 'delete':
					$arr['html'] = $this->_delete();
					break;
				case 'save':
					$arr['html'] = $this->_save();
					break;
				case 'savecomment':
					$arr['html'] = $this->_saveComment();
					break;
				case 'deletecomment':
					$arr['html'] = $this->_deleteComment();
					break;
				case 'update':
					$arr['html'] = $this->updateFeed();
					break;
			}
		}

		// Return data
		return $arr;
	}

	/**
	 * Event call to get side content
	 *
	 * @return
	 */
	public function onProjectExtras( $model, $area )
	{
		// Check if our area is the one we want to return results for
		if ($area != 'feed')
		{
			return;
		}

		// No suggestions for read-only users
		if (!$model->access('content'))
		{
			return false;
		}

		// Allow to place custom modules on project pages
		$html = \Hubzero\Module\Helper::renderModules('projectpage');

		// Side blocks from other plugins?
		/*$sections = Event::trigger( 'projects.onProjectMiniList', array( $this->model));

		if (!empty($sections))
		{
			// Show subscription to feed (new)
			$subscribe = Event::trigger( 'projects.onProjectMember', array( $this->model));
			$html .= !empty($subscribe[0]) ? $subscribe[0] : NULL;

			foreach ($sections as $section)
			{
				$html .= !empty($section) ? $section : NULL;
			}
		}
		*/

		return $html;
	}

	/**
	 * Event call to get plugin notification
	 *
	 * @return
	 */
	public function onProjectNotification( $model, $area)
	{
		// Check if our area is the one we want to return results for
		if ($area != 'feed')
		{
			return;
		}

		$html = '';

		// Acting member
		$member = $model->member();
		if (!is_object($member->params))
		{
			$member->params = new \Hubzero\Config\Registry($member->params);
		}

		// Show welcome screen?
		$showWelcome = $member && is_object($member->params)
			&& $member->params->get('hide_welcome') == 0  ? 1 : 0;

		// Show welcome banner with suggestions
		if ($showWelcome)
		{
			// Get suggestions
			$suggestions = \Components\Projects\Helpers\Html::getSuggestions( $model );

			// Display welcome message
			$view = new \Hubzero\Plugin\View(
				array(
					'folder'  => 'projects',
					'element' => 'blog',
					'name'    => 'modules',
					'layout'  => '_welcome'
				)
			);
			$view->option 		= $this->_option;
			$view->suggestions  = $suggestions;
			$view->model	    = $model;
			$html 		       .= $view->loadTemplate();
		}

		return $html;
	}

	/**
	 * View of project updates
	 *
	 * @return     string
	 */
	public function page()
	{
		// Output HTML
		$view = new \Hubzero\Plugin\View(
			array(
				'folder'  => 'projects',
				'element' => 'blog',
				'name'    => 'view'
			)
		);

		$view->limit = intval($this->params->get('limit', 25));

		// Get activities
		$objAC = $this->model->table('Activity');
		$view->filters = array(
			'role'    => $this->model->member()->role,
			'limit'   => Request::getVar('limit', $view->limit, 'request')
		);

		// Get count
		$view->total = $objAC->getActivities($this->model->get('id'), $view->filters, 1, $this->_uid);

		// get activities
		$activities = $objAC->getActivities (
			$this->model->get('id'),
			$view->filters,
			0,
			$this->_uid
		);
		$view->activities = $this->_prepActivities(
			$activities,
			$view->filters,
			$view->limit
		);

		// Output html
		$view->params 		= $this->model->params;
		$view->option 		= $this->_option;
		$view->database 	= $this->_database;
		$view->model 		= $this->model;
		$view->uid 			= $this->_uid;
		$view->config 		= $this->_config;
		$view->title		= $this->_area['title'];

		return $view->loadTemplate();
	}

	/**
	 * Save new blog entry
	 *
	 * @return     void, redirect
	 */
	protected function _save()
	{
		// Check permission
		if (!$this->model->access('content'))
		{
			throw new Exception(Lang::txt('ALERTNOTAUTH'), 403);
			return;
		}

		// Incoming
		$managers = Request::getInt('managers_only', 0);
		$entry = trim(Request::getVar('blogentry', ''));

		// Text clean-up
		$entry = \Hubzero\Utility\Sanitize::stripScripts($entry);
		$entry = \Hubzero\Utility\Sanitize::stripImages($entry);

		// Instantiate project microblog entry
		$objM = new \Components\Projects\Tables\Blog($this->_database);
		if ($entry)
		{
			$objM->projectid      = $this->model->get('id');
			$objM->blogentry      = $entry;
			$objM->managers_only  = $managers;
			$objM->posted         = Date::toSql();
			$objM->posted_by      = $this->_uid;

			// Save new blog entry
			if (!$objM->store())
			{
				$this->setError($objM->getError());
			}
			else
			{
				$this->_msg = Lang::txt('PLG_PROJECTS_BLOG_NEW_BLOG_ENTRY_SAVED');
			}

			// Get new entry ID
			if (!$objM->id)
			{
				$objM->checkin();
			}

			// Record activity
			if ($objM->id)
			{
				$aid = $this->model->recordActivity(
					Lang::txt('COM_PROJECTS_SAID'),
					$objM->id,
					'', '', 'blog', 1
				);
			}

			// Store activity ID
			if ($aid)
			{
				$objM->activityid = $aid;
				$objM->store();
			}
		}

		// Pass error or success message
		if ($this->getError())
		{
			\Notify::message($this->getError(), 'error', 'projects');
		}
		elseif (!empty($this->_msg))
		{
			\Notify::message($this->_msg, 'success', 'projects');
		}

		// Redirect
		App::redirect(Route::url($this->model->link()));
		return;
	}

	/**
	 * Delete blog entry
	 *
	 * @return     void, redirect
	 */
	protected function _delete()
	{
		// Check permission
		if (!$this->model->access('content'))
		{
			throw new Exception(Lang::txt('ALERTNOTAUTH'), 403);
			return;
		}

		// Incoming
		$tbl = trim(Request::getVar('tbl', 'activity'));
		$eid = Request::getInt('eid', 0);

		// Are we deleting a blog entry?
		if ($tbl == 'blog')
		{
			$objM = new \Components\Projects\Tables\Blog($this->_database);

			if ($eid && $objM->load($eid))
			{
				// Get associated commenting activities
				$objC = new \Components\Projects\Tables\Comment($this->_database);
				$activities = $objC->collectActivities($eid, $tbl);
				$activities[] = $objM->activityid;

				// Delete blog entry
				if ($objM->deletePost())
				{
					$this->_msg = Lang::txt('PLG_PROJECTS_BLOG_ENTRY_DELETED');

					// Delete all associated comments
					$comments = $objC->deleteComments($eid, $tbl);

					// Delete all associated activities
					foreach ($activities as $a)
					{
						$objAA = $this->model->table('Activity');
						$objAA->loadActivity($a, $this->model->get('id'));
						$objAA->deleteActivity();
					}
				}
			}
		}
		// Are we deleting activity?
		if ($tbl == 'activity')
		{
			$objAA = $this->model->table('Activity');
			$objAA->loadActivity($eid, $this->model->get('id'));

			if ($this->model->access('content') || $objAA->userid == $this->_uid)
			{
				// Get associated commenting activities
				$objC = new \Components\Projects\Tables\Comment($this->_database);
				$activities = $objC->collectActivities($eid, $tbl);

				if ($objAA->deleteActivity())
				{
					$this->_msg = Lang::txt('PLG_PROJECTS_BLOG_ENTRY_DELETED');

					// Delete all associated comments
					$comments = $objC->deleteComments($eid, $tbl);

					// Delete all associated activities
					foreach ($activities as $a)
					{
						$objAA = $this->model->table('Activity');
						$objAA->loadActivity($a, $this->model->get('id'));
						$objAA->deleteActivity();
					}
				}
			}
			else
			{
				// unauthorized
				$this->setError(Lang::txt('COM_PROJECTS_ERROR_ACTION_NOT_AUTHORIZED'));
			}
		}

		// Pass error or success message
		if ($this->getError())
		{
			\Notify::message($this->getError(), 'error', 'projects');
		}
		elseif (!empty($this->_msg))
		{
			\Notify::message($this->_msg, 'success', 'projects');
		}

		// Redirect
		App::redirect(Route::url($this->model->link('feed')));
		return;
	}

	/**
	 * Update activity feed (load more entries)
	 *
	 * @return     string
	 */
	public function updateFeed()
	{
		// Output HTML
		$view = new \Hubzero\Plugin\View(
			array(
				'folder'  => 'projects',
				'element' => 'blog',
				'name'    => 'activity'
			)
		);

		$objAC                  = $this->model->table('Activity');
		$view->filters 			= array();

		$view->total 			= $objAC->getActivities(
			$this->model->get('id'),
			$view->filters,
			1,
			$this->_uid
		);
		$view->limit 			= intval($this->params->get('limit', 25));
		$view->filters['limit'] = Request::getVar('limit', $view->limit, 'request');
		$view->option 			= $this->_option;
		$view->model 			= $this->model;

		$activities = $objAC->getActivities(
			$this->model->get('id'),
			$view->filters,
			0,
			$this->_uid
		);
		$view->activities = $this->_prepActivities(
			$activities,
			$view->filters,
			$view->limit
		);

		$view->uid 				= $this->_uid;
		$view->database 		= $this->_database;
		$view->title			= $this->_area['title'];
		return $view->loadTemplate();
	}

	/**
	 * Activity data in multiple projects (members/groups plugins)
	 *
	 * @param      array 	$filters    Query filters
	 * @param      integer  $limit 		Number of entries
	 *
	 * @return     array
	 */
	public function onShared($area, $model, $projects, $uid, $filters)
	{
		// Check if our area is the one we want to return results for
		if ($area != 'feed')
		{
			return;
		}

		$this->model     = $model;
		$this->_database = App::get('db');
		$this->_uid      = $uid;
		$this->miniView  = true;

		// Output HTML
		$view = new \Hubzero\Plugin\View(
			array(
				'folder'  => 'projects',
				'element' => 'blog',
				'name'    => 'activity',
				'layout'  => 'shared'
			)
		);
		$view->limit   = isset($filters['limit']) ? $filters['limit'] : 0;
		$view->filters = $filters;

		// Get and sort activities
		$objAC = $this->model->table('Activity');
		$activities = $objAC->getActivities(0, $filters, 0, $uid, $projects);
		$view->activities = $this->_prepActivities(
			$activities,
			$filters,
			$view->limit
		);

		// Get total
		$view->total = $objAC->getActivities(0, array(), 1, $uid, $projects);

		$view->uid   = $this->_uid;
		$view->model = $this->model;
		return $view->loadTemplate();
	}

	/**
	 * Collect activity data
	 *
	 * @param      array 	$filters    Query filters
	 * @param      integer  $limit 		Number of entries
	 *
	 * @return     array
	 */
	protected function _prepActivities($activities, $filters, $limit)
	{
		$objAC = $this->model->table('Activity');

		// Instantiate some classes
		$objM = new \Components\Projects\Tables\Blog($this->_database);
		$objC = new \Components\Projects\Tables\Comment($this->_database);
		$objTD = new \Components\Projects\Tables\Todo($this->_database);

		// Collectors
		$shown = array();
		$newc = array();
		$skipped = array();
		$prep = array();

		// Loop through activities
		if (is_array($activities) && count($activities) > 0)
		{
			foreach ($activities as $a)
			{
				// Is this a comment?
				if ($a->class == 'quote')
				{
					// Get comment
					$c = $objC->getComments(NULL, NULL, $a->id);
					if (!$c)
					{
						continue;
					}

					// Bring up commented item
					$needle = array('id' => $c->parent_activity);
					$key = \Components\Projects\Helpers\Html::myArraySearch($needle, $activities);
					$shown[] = $a->id;
					if (!$key)
					{
						// get and add parent activity
						$filters['id'] = $c->parent_activity;
						$pa = $objAC->getActivities ($a->projectid, $filters, 0, $this->_uid);
						if ($pa && count($pa) > 0)
						{
							$a = $pa[0];
						}
					}
					else
					{
						$a = $activities[$key];
					}
					$a->new = isset($c->newcount) ? $c->newcount : 0;
				}

				if (!in_array($a->id, $shown))
				{
					$shown[] = $a->id;
					$class = $a->class ? $a->class : 'activity';

					// Display hyperlink
					if ($a->highlighted && $a->url)
					{
						$a->activity = str_replace($a->highlighted, '<a href="' . $a->url . '">'
							. $a->highlighted . '</a>', $a->activity);
					}

					// Set vars
					$ebody       = '';
					$eid         = $a->id;
					$etbl        = 'activity';
					$deletable   = 0;

					// Get blog entry
					if ($class == 'blog')
					{
						$blog = $objM->getEntries(
							$a->projectid,
							$bfilters = array('activityid' => $a->id),
							$a->referenceid
						);
						if (!$blog)
						{
							continue;
						}

						$ebody = $this->drawBodyText($blog[0]->blogentry);
						$eid 		= $a->referenceid;
						$etbl 		= 'blog';
						$deletable  = 1;
					}
					elseif ($class == 'todo')
					{
						$todo = $objTD->getTodos(
							$a->projectid,
							$tfilters = array('activityid' => $a->id),
							$a->referenceid
						);
						if (!$todo)
						{
							continue;
						}

						$content    = $todo[0]->details ? $todo[0]->details : $todo[0]->content;
						$ebody 		= $this->drawBodyText($content);
						$eid 		= $a->referenceid;
						$etbl 		= 'todo';
					}

					// Get/parse & save item preview if available
					$preview = empty($this->miniView) ? $this->getItemPreview($class, $a) : '';

					// Get comments
					if ($a->commentable)
					{
						$comments = $objC->getComments($eid, $etbl);
					}
					else
					{
						$comments = null;
					}

					// Is user allowed to delete item?
					$deletable = empty($this->miniView)
						&& $deletable
						&& $this->model->access('content')
						&& ($a->userid == $this->_uid or $this->model->access('manager'))
						? 1 : 0;

					$prep[] = array(
						'activity'   => $a,
						'eid'        => $eid,
						'etbl'       => $etbl,
						'body'       => $ebody,
						'deletable'  => $deletable,
						'comments'   => $comments,
						'class'      => $class,
						'preview'    => $preview
					);
				}
			}
		}

		return $prep;
	}

	/**
	 * Display 'more' link if text is too long
	 *
	 * @param      string	$body   	Text body to shorten
	 * @param      object	$activity   Individual activity
	 * @return     HTML
	 */
	public function drawBodyText($body = NULL)
	{
		if (!$body)
		{
			return false;
		}

		$shorten = ($body && strlen($body) > 250) ? 1 : 0;
		$shortBody = $shorten ? \Hubzero\Utility\String::truncate($body, 250) : $body;

		// Embed links
		$body      = \Components\Projects\Helpers\Html::replaceUrls($body, 'external');
		$shortBody = \Components\Projects\Helpers\Html::replaceUrls($shortBody, 'external');

		// Emotions (new)
		$body      = \Components\Projects\Helpers\Html::replaceEmoIcons($body);
		$shortBody = \Components\Projects\Helpers\Html::replaceEmoIcons($shortBody);

		// Style body text
		$ebody  = '<div class="body';
		$ebody .= strlen($shortBody) > 50 ? ' newline' : ' sameline';
		$ebody .= '">' . preg_replace("/\n/", '<br />', trim($shortBody));
		if ($shorten)
		{
			$ebody .= ' <a href="#" class="more-content">' . Lang::txt('COM_PROJECTS_MORE') . '</a>';
		}
		$ebody .= '</div>';

		if ($shorten)
		{
			$ebody .= '<div class="fullbody hidden">' . preg_replace("/\n/", '<br />', trim($body)) . '</div>' ;
		}

		return $ebody;
	}

	/**
	 * Get preview
	 *
	 * @param      string	$type    	Item type (files, notes etc.)
	 * @param      object	$activity   Individual activity
	 * @return     HTML
	 */
	public function getItemPreview($type = NULL, $activity = NULL, $body = NULL, $reload = false)
	{
		$ref = $activity->referenceid;

		// Do we have a saved preview?
		if ($activity->preview && !$reload)
		{
			return $activity->preview;
		}

		if ($body)
		{
			return $this->drawBodyText($body);
		}

		if (!$ref || !$type)
		{
			return false;
		}

		$previewBody = NULL;

		switch ($type)
		{
			case 'files':
				$previewBody = $this->_getFilesPreview($ref);
				break;

			case 'notes':
				$previewBody = $this->_getNotesPreview($ref);
				break;
		}

		// Save preview
		if ($previewBody)
		{
			$objA = $this->model->table('Activity');
			$objA->saveActivityPreview($activity->id, $previewBody);
		}

		return $previewBody;
	}

	/**
	 * Get Note Previews
	 *
	 * @param      string	$ref   	 	 Reference to note
	 * @return     void, redirect
	 */
	protected function _getNotesPreview($ref = '')
	{
		// TBD
		return false;
	}

	/**
	 * Get File Previews
	 *
	 * @param      string 	$ref    Reference to files
	 * @return     void, redirect
	 */
	protected function _getFilesPreview($ref = '')
	{
		if (!$ref)
		{
			return false;
		}

		if (!$this->_path)
		{
			// Get project file path
			$this->_path = \Components\Projects\Helpers\Html::getProjectRepoPath($this->model->get('alias'));
		}

		// We do need project file path
		if (!$this->_path || !is_dir($this->_path))
		{
			return false;
		}

		$files 	  	 = explode(',', $ref);
		$selected 	 = array();
		$maxHeight   = 0;
		$minHeight   = 0;
		$minWidth    = 0;
		$maxWidth	 = 0;

		$imagepath = trim($this->_config->get('imagepath', '/site/projects'), DS);
		$to_path = DS . $imagepath . DS . strtolower($this->model->get('alias')) . DS . 'preview';

		foreach ($files as $item)
		{
			$parts = explode(':', $item);
			$file  = count($parts) > 1 ? $parts[1] : $parts[0];
			$hash  = count($parts) > 1 ? $parts[0] : NULL;

			if ($hash)
			{
				// Only preview mid-size images from now on
				$hashed = md5(basename($file) . '-' . $hash) . '.png';

				if (is_file(PATH_APP. $to_path . DS . $hashed))
				{
					$preview['image'] = $hashed;
					$preview['url']   = NULL;
					$preview['title'] = basename($file);

					// Get image properties
					list($width, $height, $type, $attr) = getimagesize(PATH_APP. $to_path . DS . $hashed);

					$preview['width'] = $width;
					$preview['height'] = $height;
					$preview['orientation'] = $width > $height ? 'horizontal' : 'vertical';
					// Record min and max width and height to build image grid
					if ($height >= $maxHeight)
					{
						$maxHeight = $height;
					}
					if ($height && $height <= $minHeight)
					{
						$minHeight = $height;
					}
					else
					{
						$minHeight = $height;
					}
					if ($width > $maxWidth)
					{
						$maxWidth = $width;
					}

					$selected[] = $preview;
				}
			}
		}

		// No files for preview
		if (empty($selected))
		{
			return false;
		}

		// Output HTML
		$view = new \Hubzero\Plugin\View(
			array(
				'folder'  => 'projects',
				'element' => 'blog',
				'name'    => 'preview',
				'layout'  => 'files'
			)
		);
		$view->maxHeight	= $maxHeight;
		$view->maxWidth		= $maxWidth;
		$view->minHeight	= ($minHeight > 400) ? 400 : $minHeight;
		$view->selected		= $selected;
		$view->option 		= $this->_option;
		$view->model 		= $this->model;
		return $view->loadTemplate();
	}

	/**
	 * Save comment
	 *
	 * @return     void, redirect
	 */
	protected function _saveComment()
	{
		// Check permission
		if (!$this->model->access('content'))
		{
			throw new Exception(Lang::txt('ALERTNOTAUTH'), 403);
			return;
		}

		// Incoming
		$itemid          = Request::getInt('itemid', 0, 'post');
		$tbl             = trim(Request::getVar('tbl', 'activity', 'post'));
		$comment         = trim(Request::getVar('comment', '', 'post'));
		$parent_activity = Request::getInt('parent_activity', 0, 'post');

		// Clean-up
		$comment = \Hubzero\Utility\Sanitize::stripScripts($comment);
		$comment = \Hubzero\Utility\Sanitize::stripImages($comment);

		// Instantiate comment
		$objC = new \Components\Projects\Tables\Comment($this->_database);
		if ($comment)
		{
			$objC->itemid 			= $itemid;
			$objC->tbl 				= $tbl;
			$objC->parent_activity  = $parent_activity;
			$objC->comment 			= $comment;
			$objC->created 			= Date::toSql();
			$objC->created_by 		= $this->_uid;
			if (!$objC->store())
			{
				$this->setError($objC->getError());
			}
			else
			{
				$this->_msg = Lang::txt('PLG_PROJECTS_BLOG_COMMENT_POSTED');
			}
			// Get new entry ID
			if (!$objC->id)
			{
				$objC->checkin();
			}

			// Record activity
			if ($objC->id)
			{
				$what = $tbl == 'blog'
					? Lang::txt('COM_PROJECTS_BLOG_POST')
					: Lang::txt('COM_PROJECTS_AN_ACTIVITY');
				$what = $tbl == 'todo' ? Lang::txt('COM_PROJECTS_TODO_ITEM') : $what;
				$url  = $tbl == 'todo' ? Route::url($this->model->link('todo') . '&action=view&todoid=' . $itemid) : Route::url($this->model->link('feed')) . '#tr_' . $parent_activity; // same-page link
				$aid  = $this->model->recordActivity(
					Lang::txt('COM_PROJECTS_COMMENTED') . ' ' . Lang::txt('COM_PROJECTS_ON') . ' ' . $what,
					$objC->id, $what, $url, 'quote', 0
				);
			}

			// Store activity ID
			if ($aid)
			{
				$objC->activityid = $aid;
				$objC->store();
			}
		}

		// Pass error or success message
		if ($this->getError())
		{
			\Notify::message($this->getError(), 'error', 'projects');
		}
		elseif (!empty($this->_msg))
		{
			\Notify::message($this->_msg, 'success', 'projects');
		}

		// Redirect
		App::redirect(Route::url($this->model->link()));
		return;
	}

	/**
	 * Delete comment
	 *
	 * @return     void, redirect
	 */
	protected function _deleteComment()
	{
		// Check permission
		if (!$this->model->access('content'))
		{
			throw new Exception(Lang::txt('ALERTNOTAUTH'), 403);
			return;
		}

		// Incoming
		$cid  = Request::getInt('cid', 0);

		// Instantiate comment
		$objC = new \Components\Projects\Tables\Comment($this->_database);

		if ($objC->load($cid))
		{
			$activityid = $objC->activityid;

			// delete comment
			if ($objC->deleteComment())
			{
				$this->_msg = Lang::txt('PLG_PROJECTS_BLOG_COMMENT_DELETED');
			}

			// delete associated activity
			$objAA = $this->model->table('Activity');
			if ($activityid && $objAA->load($activityid))
			{
				$objAA->deleteActivity();
			}
		}

		// Pass error or success message
		if ($this->getError())
		{
			\Notify::message($this->getError(), 'error', 'projects');
		}
		elseif (!empty($this->_msg))
		{
			\Notify::message($this->_msg, 'success', 'projects');
		}

		// Redirect
		App::redirect(Route::url($this->model->link()));
		return;
	}
}
