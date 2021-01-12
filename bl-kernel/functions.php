<?php defined('BLUDIT') or die('Bludit CMS.');

/*	Create a new page === Bludit v4

	@args			array			The array $args supports all the keys from the variable $dbFields of the class pages.class.php. If you don't pass all the keys, the default values are used.
	@returns		string/boolean	Returns the page key if the page is successfully created, FALSE otherwise
*/
function createPage($args) {
	global $pages;
	global $syslog;

	// The user is always the one logged
	$args['username'] = Session::get('username');
	if (empty($args['username'])) {
		Log::set(__FUNCTION__.LOG_SEP.'Empty username.', LOG_TYPE_ERROR);
		return false;
	}

	$key = $pages->add($args);
	if ($key) {
		// Call the plugins after page created
		execPluginsByHook('afterPageCreate', array($key));

		// Reindex categories and tags
		reindexCategories();
		reindexTags();

		// Add to syslog
		$syslog->add(array(
			'dictionaryKey'=>'new-content-created',
			'notes'=>(empty($args['title'])?$key:$args['title'])
		));

		Log::set(__FUNCTION__.LOG_SEP.'Page created.', LOG_TYPE_INFO);
		return $key;
	}

	Log::set(__FUNCTION__.LOG_SEP.'Something happened when you tried to create the page.', LOG_TYPE_ERROR);
	deletePage($key);
	return false;
}

/*	Edit a page === Bludit v4

	@args			array			The array $args supports all the keys from the variable $dbFields of the class pages.class.php. If you don't pass all the keys, the default values are used.
	@args['key']	string			The key of the page to be edited
	@returns		string/boolean	Returns the page key if the page is successfully edited, FALSE otherwise
*/
function editPage($args) {
	global $pages;
	global $syslog;

	// Check if the key is not empty
	if (empty($args['key'])) {
		Log::set(__FUNCTION__.LOG_SEP.'Empty page key.', LOG_TYPE_ERROR);
		return false;
	}

	// Check if the page key exist
	if (!$pages->exists($args['key'])) {
		Log::set(__FUNCTION__.LOG_SEP.'Page key doesn\'t exist: '.$args['key'], LOG_TYPE_ERROR);
		return false;
	}

	$key = $pages->edit($args);
	if ($key) {
		// Call the plugins after page modified
		execPluginsByHook('afterPageModify', array($key));

		// Reindex categories and tags
		reindexCategories();
		reindexTags();

		// Add to syslog
		$syslog->add(array(
			'dictionaryKey'=>'content-edited',
			'notes'=>empty($args['title'])?$key:$args['title']
		));

		Log::set(__FUNCTION__.LOG_SEP.'Page edited.', LOG_TYPE_INFO);
		return $key;
	}

	Log::set(__FUNCTION__.LOG_SEP.'Something happened when you tried to edit the page.', LOG_TYPE_ERROR);
	return false;
}

/*	Delete a page === Bludit v4

	@key			string			The key of the page to be deleted
	@returns		string/boolean	Returns TRUE if the page is successfully deleted, FALSE otherwise
*/
function deletePage($key) {
	global $pages;
	global $syslog;

	if ($pages->delete($key)) {
		// Call the plugins after page deleted
		execPluginsByHook('afterPageDelete', array($key));

		// Reindex categories and tags
		reindexCategories();
		reindexTags();

		// Add to syslog
		$syslog->add(array(
			'dictionaryKey'=>'content-deleted',
			'notes'=>$key
		));

		Log::set(__FUNCTION__.LOG_SEP.'Page deleted.', LOG_TYPE_INFO);
		return true;
	}

	Log::set(__FUNCTION__.LOG_SEP.'Something happened when you tried to delete the page.', LOG_TYPE_ERROR);
	return false;
}

/*	Create a new category === Bludit v4

	@args			array			The array $args supports all the keys from the variable $dbFields of the class categories.class.php. If you don't pass all the keys, the default values are used.
	@returns		string/boolean	Returns the category key if the category is successfully created, FALSE otherwise
*/
function createCategory($args) {
	global $categories;
	global $syslog;

	if (Text::isEmpty($args['name'])) {
		Log::set(__FUNCTION__.LOG_SEP.'The category name is empty.', LOG_TYPE_ERROR);
		return false;
	}

	$key = $categories->add($args);
	if ($key) {
		$syslog->add(array(
			'dictionaryKey'=>'new-category-created',
			'notes'=>$args['name']
		));

		Log::set(__FUNCTION__.LOG_SEP.'Category created.', LOG_TYPE_INFO);
		return $key;
	}

	Log::set(__FUNCTION__.LOG_SEP.'The category already exists or some issue saving the database.', LOG_TYPE_ERROR);
	return false;
}

/*	Create a new user === Bludit v4
	This function should check everthing, such as empty username, emtpy password, password lenght, etc

	@args			array			The array $args supports all the keys from the variable $dbFields of the class pages.class.php. If you don't pass all the keys, the default values are used.
	@returns		string/boolean	Returns the page key if the page is successfully created, FALSE otherwise
*/
function createUser($args) {
	global $users;
	global $syslog;

	$args['username'] = Text::removeSpecialCharacters($args['username']);

	if (Text::isEmpty($args['username'])) {
		Log::set(__FUNCTION__.LOG_SEP.'Empty username.', LOG_TYPE_ERROR);
		return false;
	}

	if (Text::length($args['password']) < PASSWORD_LENGTH) {
		Log::set(__FUNCTION__.LOG_SEP.'The password is to short.', LOG_TYPE_ERROR);
		return false;
	}

	$key = $users->add($args);
	if ($key) {
		$syslog->add(array(
			'dictionaryKey'=>'new-user-created',
			'notes'=>$args['username']
		));

		Log::set(__FUNCTION__.LOG_SEP.'User created.', LOG_TYPE_INFO);
		return true;
	}

	Log::set(__FUNCTION__.LOG_SEP.'The user already exists or some issue saving the database.', LOG_TYPE_ERROR);
	return false;
}

// Re-index database of categories
// If you create/edit/remove a page is necessary regenerate the database of categories
function reindexCategories() {
	global $categories;
	return $categories->reindex();
}

// Re-index database of tags
// If you create/edit/remove a page is necessary regenerate the database of tags
function reindexTags() {
	global $tags;
	return $tags->reindex();
}

// Generate the page 404 Not found
function buildErrorPage() {
	global $site;
	global $L;

	try {
		$pageNotFoundKey = $site->pageNotFound();
		$pageNotFound = New Page($pageNotFoundKey);
	} catch (Exception $e) {
		$pageNotFound = New Page(false);
		$pageNotFound->setField('title', 	$L->get('page-not-found'));
		$pageNotFound->setField('content', 	$L->get('page-not-found-content'));
		$pageNotFound->setField('username', 	'admin');
	}

	return $pageNotFound;
}

// This function is only used from the rule 69.pages.php, DO NOT use this function!
// This function generate a particular page from the current slug of the url
// If the slug has not a page associated returns FALSE and set not-found as true
function buildThePage() {
	global $url;

	try {
		$pageKey = $url->slug();
		$page = New Page($pageKey);
	} catch (Exception $e) {
		$url->setNotFound();
		return false;
	}

	if ($page->draft() || $page->scheduled() || $page->autosave()) {
		if ($url->parameter('preview')!==md5($page->uuid())) {
			$url->setNotFound();
			return false;
		}
	}

	return $page;
}

// This function is only used from the rule 69.pages.php, DO NOT use this function!
function buildPagesForHome() {
	return buildPagesFor('home');
}

// This function is only used from the rule 69.pages.php, DO NOT use this function!
function buildPagesByCategory() {
	global $url;

	$categoryKey = $url->slug();
	return buildPagesFor('category', $categoryKey, false);
}

// This function is only used from the rule 69.pages.php, DO NOT use this function!
function buildPagesByTag() {
	global $url;

	$tagKey = $url->slug();
	return buildPagesFor('tag', false, $tagKey);
}

// This function is only used from the rule 69.pages.php, DO NOT use this function!
// Generate the global variables $content / $content, defined on 69.pages.php
// This function is use for buildPagesForHome(), buildPagesByCategory(), buildPagesByTag()
function buildPagesFor($for, $categoryKey=false, $tagKey=false) {
	global $pages;
	global $categories;
	global $tags;
	global $site;
	global $url;

	// Get the page number from URL
	$pageNumber = $url->pageNumber();

	if ($for=='home') {
		$onlyPublished = true;
		$numberOfItems = $site->itemsPerPage();
		$list = $pages->getList($pageNumber, $numberOfItems, $onlyPublished);

		// Include sticky pages only in the first page
		if ($pageNumber==1) {
			$sticky = $pages->getStickyDB();
			$list = array_merge($sticky, $list);
		}
	}
	elseif ($for=='category') {
		$numberOfItems = $site->itemsPerPage();
		$list = $categories->getList($categoryKey, $pageNumber, $numberOfItems);
	}
	elseif ($for=='tag') {
		$numberOfItems = $site->itemsPerPage();
		$list = $tags->getList($tagKey, $pageNumber, $numberOfItems);
	}

	// There are not items, invalid tag, invalid category, out of range, etc...
	if ($list===false) {
		$url->setNotFound();
		return false;
	}

	$content = array();
	foreach ($list as $pageKey) {
		try {
			$page = new Page($pageKey);
			if ( 	($page->type()=='published') ||
				($page->type()=='sticky') ||
				($page->type()=='static')
			) {
				array_push($content, $page);
			}
		} catch (Exception $e) {
			// continue
		}
	}

	return $content;
}

// Returns an array with all the static pages as Page-Object
// The static pages are order by position all the time
function buildStaticPages() {
	global $pages;

	$list = array();
	$pagesKey = $pages->getStaticDB();
	foreach ($pagesKey as $pageKey) {
		try {
			$page = new Page($pageKey);
			array_push($list, $page);
		} catch (Exception $e) {
			// continue
		}
	}

	return $list;
}

// Returns the Page-Object if exists, FALSE otherwise
function buildPage($pageKey) {
	try {
		$page = new Page($pageKey);
		return $page;
	} catch (Exception $e) {
		return false;
	}
}

// Returns an array with all the parent pages as Page-Object
// The pages are order by the settings on the system
function buildParentPages() {
	global $pages;

	$list = array();
	$pagesKey = $pages->getPublishedDB();
	foreach ($pagesKey as $pageKey) {
		try {
			$page = new Page($pageKey);
			if ($page->isParent()) {
				array_push($list, $page);
			}
		} catch (Exception $e) {
			// continue
		}
	}

	return $list;
}

// Returns the Plugin-Object if is enabled and installed, FALSE otherwise
function getPlugin($pluginClassName) {
	global $plugins;

	if (pluginActivated($pluginClassName)) {
		return $plugins['all'][$pluginClassName];
	}
	return false;
}

// Check if the plugin is activated / installed
// Returns TRUE if the plugin is activated / installed, FALSE otherwise
function pluginActivated($pluginClassName) {
		global $plugins;

        if (isset($plugins['all'][$pluginClassName])) {
                return $plugins['all'][$pluginClassName]->installed();
        }
        return false;
}

// Activate / install the plugin
// Returns TRUE if the plugin is successfully activated, FALSE otherwise
function activatePlugin($pluginClassName) {
	global $plugins;
	global $syslog;
	global $L;

	// Check if the plugin exists
	if (isset($plugins['all'][$pluginClassName])) {
		$plugin = $plugins['all'][$pluginClassName];
		if ($plugin->install()) {
			// Add to syslog
			$syslog->add(array(
				'dictionaryKey'=>'plugin-activated',
				'notes'=>$plugin->name()
			));

			// Create an alert
			Alert::set($L->g('plugin-activated'));
			return true;
		}
	}
	return false;
}

// Deactivate / uninstall the plugin
// Returns TRUE if the plugin is successfully deactivated, FALSE otherwise
function deactivatePlugin($pluginClassName) {
	global $plugins;
	global $syslog;
	global $L;

	// Check if the plugin exists
	if (isset($plugins['all'][$pluginClassName])) {
		$plugin = $plugins['all'][$pluginClassName];

		if ($plugin->uninstall()) {
			// Add to syslog
			$syslog->add(array(
				'dictionaryKey'=>'plugin-deactivated',
				'notes'=>$plugin->name()
			));

			// Create an alert
			Alert::set($L->g('plugin-deactivated'));
			return true;
		}
	}
	return false;
}

function deactivateAllPlugin() {
	global $plugins;
	global $syslog;
	global $L;

	// Check if the plugin exists
	foreach ($plugins['all'] as $plugin) {
		if ($plugin->uninstall()) {
			// Add to syslog
			$syslog->add(array(
				'dictionaryKey'=>'plugin-deactivated',
				'notes'=>$plugin->name()
			));
		}
	}
	return false;
}

function changePluginsPosition($pluginClassList) {
	global $plugins;
	global $syslog;
	global $L;

	foreach ($pluginClassList as $position=>$pluginClassName) {
		if (isset($plugins['all'][$pluginClassName])) {
			$plugin = $plugins['all'][$pluginClassName];
			$plugin->setPosition(++$position);
		}
	}

	// Add to syslog
	$syslog->add(array(
		'dictionaryKey'=>'plugins-sorted',
		'notes'=>''
	));

	Alert::set($L->g('The changes have been saved'));
	return true;
}

// Execute the plugins by hook
function execPluginsByHook($hook, $args = array()) {
	global $plugins;
	foreach ($plugins[$hook] as $plugin) {
		echo call_user_func_array(array($plugin, $hook), $args);
	}
}



function editUser($args) {
	global $users;
	global $syslog;

	if ($users->set($args)) {
		// Add to syslog
		$syslog->add(array(
			'dictionaryKey'=>'user-edited',
			'notes'=>$args['username']
		));

		return true;
	}

	return false;
}

function disableUser($args) {
	global $users;
	global $login;
	global $syslog;

	// Arguments
	$username = $args['username'];

	// Only administrators can disable users
	if ($login->role()!=='admin') {
		return false;
	}

	// Check if the username exists
	if (!$users->exists($username)) {
		return false;
	}

	// Disable the user
	if ($users->disableUser($username)) {
		// Add to syslog
		$syslog->add(array(
			'dictionaryKey'=>'user-disabled',
			'notes'=>$username
		));

		return true;
	}

	return false;
}

function deleteUser($args) {
	global $users, $pages;
	global $login;
	global $syslog;

	// Arguments
	$username = $args['username'];
	$deleteContent = isset($args['deleteContent']) ? $args['deleteContent'] : false;

	// Only administrators can delete users
	if ($login->role()!=='admin') {
		return false;
	}

	// The user admin cannot be deleted
	if ($username=='admin') {
		return false;
	}

	// Check if the username exists
	if (!$users->exists($username)) {
		return false;
	}

	if ($deleteContent) {
		$pages->deletePagesByUser(array('username'=>$username));
	} else {
		$pages->transferPages(array('oldUsername'=>$username));
	}

	if ($users->delete($username)) {
		// Add to syslog
		$syslog->add(array(
			'dictionaryKey'=>'user-deleted',
			'notes'=>$username
		));

		return true;
	}

	return false;
}



function editSettings($args) {
	global $site;
	global $syslog;
	global $L;
	global $pages;

	if (isset($args['language'])) {
		if ($args['language']!=$site->language()) {
			$tmp = new dbJSON(PATH_LANGUAGES.$args['language'].'.json', false);
			if (isset($tmp->db['language-data']['locale'])) {
				$args['locale'] = $tmp->db['language-data']['locale'];
			} else {
				$args['locale'] = $args['language'];
			}
		}
	}

	if (empty($args['homepage'])) {
		$args['homepage'] = '';
		$args['uriBlog'] = '';
	}

	if (empty($args['pageNotFound'])) {
		$args['pageNotFound'] = '';
	}

	if (isset($args['uriPage'])) {
		$args['uriPage'] = Text::addSlashes($args['uriPage']);
	}

	if (isset($args['uriTag'])) {
		$args['uriTag'] = Text::addSlashes($args['uriTag']);
	}

	if (isset($args['uriCategory'])) {
		$args['uriCategory'] = Text::addSlashes($args['uriCategory']);
	}

	if (!empty($args['uriBlog'])) {
		$args['uriBlog'] = Text::addSlashes($args['uriBlog']);
	} else {
		if (!empty($args['homepage']) && empty($args['uriBlog'])) {
			$args['uriBlog'] = '/blog/';
		} else {
			$args['uriBlog'] = '';
		}
	}

	if (isset($args['extremeFriendly'])) {
		$args['extremeFriendly'] = (($args['extremeFriendly']=='true')?true:false);
	}

	if (isset($args['customFields'])) {
		// Custom fields need to be JSON format valid, also the empty JSON need to be "{}"
		json_decode($args['customFields']);
		if (json_last_error() != JSON_ERROR_NONE) {
			return false;
		}
		$pages->setCustomFields($args['customFields']);
	}

	if ($site->set($args)) {
		// Check current order-by if changed it reorder the content
		if ($site->orderBy()!=ORDER_BY) {
			if ($site->orderBy()=='date') {
				$pages->sortByDate();
			} else {
				$pages->sortByPosition();
			}
			$pages->save();
		}

		// Add syslog
		$syslog->add(array(
			'dictionaryKey'=>'settings-changes',
			'notes'=>''
		));

		return true;
	}

	return false;
}

function changeUserPassword($args) {
	global $users;
	global $L;
	global $syslog;

	// Arguments
	$username = $args['username'];
	$newPassword = $args['newPassword'];
	$confirmPassword = $args['confirmPassword'];

	// Password length
	if (Text::length($newPassword) < 6) {
		Alert::set($L->g('Password must be at least 6 characters long'), ALERT_STATUS_FAIL);
		return false;
	}

	if ($newPassword!=$confirmPassword) {
		Alert::set($L->g('The password and confirmation password do not match'), ALERT_STATUS_FAIL);
		return false;
	}

	if ($users->setPassword(array('username'=>$username, 'password'=>$newPassword))) {
		// Add to syslog
		$syslog->add(array(
			'dictionaryKey'=>'user-password-changed',
			'notes'=>$username
		));

		Alert::set($L->g('The changes have been saved'), ALERT_STATUS_OK);
		return true;
	}

	return false;
}

// Returns true if the user is allowed to proceed
function checkRole($allowRoles, $redirect=true) {
	global $login;
	global $L;
	global $syslog;

	$userRole = $login->role();
	if (in_array($userRole, $allowRoles)) {
		return true;
	}

	if ($redirect) {
		// Add to syslog
		$syslog->add(array(
			'dictionaryKey'=>'access-denied',
			'notes'=>$login->username()
		));

		Alert::set($L->g('You do not have sufficient permissions'));
		Redirect::page('dashboard');
	}
	return false;
}



function editCategory($args) {
	global $L;
	global $pages;
	global $categories;
	global $syslog;

	if (Text::isEmpty($args['name']) || Text::isEmpty($args['newKey']) ) {
		Alert::set($L->g('Empty fields'));
		return false;
	}

	$newCategoryKey = $categories->edit($args);

	if ($newCategoryKey==false) {
		Alert::set($L->g('The category already exists'));
		return false;
	}

	// Change the category key in the pages database
	$pages->changeCategory($args['oldKey'], $newCategoryKey);

	// Add to syslog
	$syslog->add(array(
		'dictionaryKey'=>'category-edited',
		'notes'=>$newCategoryKey
	));

	Alert::set($L->g('The changes have been saved'));
	return true;
}

function deleteCategory($args) {
	global $L;
	global $categories;
	global $syslog;

	// Remove the category by key
	$categories->remove($args['oldKey']);

	// Remove the category from the pages ? or keep it if the user want to recovery the category ?

	// Add to syslog
	$syslog->add(array(
		'dictionaryKey'=>'category-deleted',
		'notes'=>$args['oldKey']
	));

	Alert::set($L->g('The changes have been saved'));
	return true;
}

// Returns an array with all the categories
// By default, the database of categories is alphanumeric sorted
function getCategories() {
	global $categories;

	$list = array();
	foreach ($categories->keys() as $key) {
		$category = new Category($key);
		array_push($list, $category);
	}
	return $list;
}

// Returns the object category if the category exists, FALSE otherwise
function getCategory($key) {
	try {
		$category = new Category($key);
		return $category;
	} catch (Exception $e) {
		return false;
	}
}

// Returns an array with all the tags
// By default, the database of tags is alphanumeric sorted
function getTags() {
	global $tags;

	$list = array();
	foreach ($tags->db as $key=>$fields) {
		$tag = new Tag($key);
		array_push($list, $tag);
	}
	return $list;
}

// Returns the object tag if the tag exists, FALSE otherwise
function getTag($key) {
	try {
		$tag = new Tag($key);
		return $tag;
	} catch (Exception $e) {
		return false;
	}
}

// Activate a theme
function activateTheme($themeDirectory) {
	global $site;
	global $syslog;
	global $L, $language;

	if (Sanitize::pathFile(PATH_THEMES.$themeDirectory)) {
		if (Filesystem::fileExists(PATH_THEMES.$themeDirectory.DS.'install.php')) {
			include_once(PATH_THEMES.$themeDirectory.DS.'install.php');
		}

		$site->set(array('theme'=>$themeDirectory));

		$syslog->add(array(
			'dictionaryKey'=>'new-theme-configured',
			'notes'=>$themeDirectory
		));

		Alert::set( $L->g('The changes have been saved') );
		return true;
	}
	return false;
}

function ajaxResponse($status=0, $message="", $data=array()) {
	$default = array('status'=>$status, 'message'=>$message);
	$output = array_merge($default, $data);
	exit (json_encode($output));
}

/*
| This function checks the image extension,
| generate a new filename to not overwrite the exists,
| generate the thumbnail,
| and move the image to a proper place
|
| @file		string	Path and filename of the image
| @imageDir	string	Path where the image is going to be stored
| @thumbnailDir	string	Path where the thumbnail is going to be stored, if you don't set the variable is not going to create the thumbnail
|
| @return	string/boolean	Path and filename of the new image or FALSE if there were some error
*/
function transformImage($file, $imageDir, $thumbnailDir=false) {
	global $site;

	// Check image extension
	$fileExtension = Filesystem::extension($file);
	$fileExtension = Text::lowercase($fileExtension);
	if (!in_array($fileExtension, $GLOBALS['ALLOWED_IMG_EXTENSION']) ) {
		return false;
	}

	// Generate a filename to not overwrite current image if exists
	$filename = Filesystem::filename($file);
	$nextFilename = Filesystem::nextFilename($imageDir, $filename);

	// Move the image to a proper place and rename
	$image = $imageDir.$nextFilename;
	Filesystem::mv($file, $image);
	chmod($image, 0644);

	// Generate Thumbnail
	if (!empty($thumbnailDir)) {
		if ($fileExtension == 'svg') {
			symlink($image, $thumbnailDir.$nextFilename);
		} else {
			$Image = new Image();
			$Image->setImage($image, $site->thumbnailWidth(), $site->thumbnailHeight(), 'crop');
			$Image->saveImage($thumbnailDir.$nextFilename, $site->thumbnailQuality(), true);
		}
	}

	return $image;
}

function downloadRestrictedFile($file) {
	if (is_file($file)) {
	    header('Content-Description: File Transfer');
	    header('Content-Type: application/octet-stream');
	    header('Content-Disposition: attachment; filename="'.basename($file).'"');
	    header('Expires: 0');
	    header('Cache-Control: must-revalidate');
	    header('Pragma: public');
	    header('Content-Length: ' . filesize($file));
	    readfile($file);
	    exit(0);
	}
}
