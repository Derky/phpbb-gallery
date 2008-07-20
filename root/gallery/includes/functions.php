<?php

/**
*
* @package phpBB3
* @version $Id$
* @copyright (c) 2007 phpBB Gallery
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

if (!defined('IN_PHPBB'))
{
	die('Hacking attempt');
}
$gallery_root_path = GALLERY_ROOT_PATH;

$gd_check = function_exists('gd_info') ? gd_info() : array();;
$gd_success = isset($gd_check['GD Version']);
if (!$gd_success)
{
	if (!isset($album_config['gd_version']))
	{
		$sql = 'SELECT * FROM ' . GALLERY_CONFIG_TABLE . " WHERE config_name = 'gd_version'";
		$result = $db->sql_query($sql);
		while( $row = $db->sql_fetchrow($result) )
		{
			$album_config_name = $row['config_name'];
			$album_config_value = $row['config_value'];
			$album_config[$album_config_name] = $album_config_value;
		}
	}
	if ($album_config['gd_version'] > 0)
	{
		$sql = 'UPDATE ' . GALLERY_CONFIG_TABLE . " SET config_value = 0 WHERE config_name = 'gd_version'";
		$result = $db->sql_query($sql);
		$album_config['gd_version'] = 0;
	}
}

$sql = 'SELECT *
	FROM ' . GALLERY_USERS_TABLE . '
	WHERE user_id = ' . (int) $user->data['user_id'];
$result = $db->sql_query($sql);
$user->gallery = $db->sql_fetchrow($result);

// ----------------------------------------------------------------------------
// This function will return the access data of the current user for a category
// Default returning value is "0" (means NOT AUTHORISED)
//
// All $*_check must be "1" or "0"
//
// $passed_auth must be a full row from GALLERY_ALBUMS_TABLE. This function still works without
// ... but $passed_auth will make it worked very much faster (because this function is often
// called in a loop)
//

//we may delete this one:
function album_user_access($album_id, $passed_auth = 0, $view_check, $upload_check, $rate_check, $comment_check, $edit_check, $delete_check)
{
	global $db, $album_config, $user, $auth;

	// --------------------------------
	// Force to check moderator status
	// --------------------------------
	$moderator_check = 1;


	// --------------------------------
	// Here the array which this function would return. Now we initiate it!
	// --------------------------------
	$album_user_access = array(
		'view'		=> 0,
		'upload'	=> 0,
		'rate'		=> 0,
		'comment'	=> 0,
		'edit'		=> 0,
		'delete'	=> 0,
		'moderator'	=> 0,
	);
	$album_user_access_keys = array_keys($album_user_access);
	//
	// END initiation $album_user_access
	//


	// --------------------------------
	// Check $cat_id
	// --------------------------------
	if ($album_id == PERSONAL_GALLERY)
	{
		$personal_gallery_access = personal_gallery_access(1,1);

		if ($personal_gallery_access['view'])
		{
			$album_user_access['view'] = 1;
		}

		if ($personal_gallery_access['upload'])
		{
			$album_user_access['upload']	= 1;
			$album_user_access['rate']		= 1;
			$album_user_access['comment']	= 1;

			$album_user_access['edit']		= 1;
			$album_user_access['delete']	= 1;

			if ($auth->acl_get('a_board') && $user->data['is_registered'] && !$user->data['is_bot'])
			{
				$album_user_access['moderator'] = 1;
			}
		}

		return $album_user_access;
	}
	else if ($album_id < 0)
	{
		trigger_error('Bad album_id arguments for function album_user_access()', E_USER_WARNING);
	}
	//
	// END check $album_id
	//


	// --------------------------------
	// If the current user is an ADMIN (ALBUM_ADMIN == ADMIN)
	// --------------------------------
	if ($auth->acl_get('a_board') && $user->data['is_registered'])
	{
		for ($i = 0; $i < count($album_user_access); $i++)
		{
			$album_user_access[$album_user_access_keys[$i]] = 1;
		}
		return $album_user_access;
	}
	//
	// END check ADMIN
	//


	// --------------------------------
	// if this is a GUEST, we will ignore some checking
	// --------------------------------
	if (!$user->data['is_registered'] || $user->data['is_bot'])
	{
		$edit_check = 0;
		$delete_check = 0;
		$moderator_check = 0;
	}
	//
	// END check GUEST
	//


	// --------------------------------
	// check if RATE or COMMENT are turned off by Album Config, so we can ignore them
	// --------------------------------
	if (!$album_config['rate'])
	{
		$rate_check = 0;
	}
	if (!$album_config['comment'])
	{
		$comment_check = 0;
	}
	//
	// END Check RATE & COMMENT
	//


	// --------------------------------
	// The array that list all access type this function will look for (except MODERATOR)
	// --------------------------------
	$access_type = array();

	if ($view_check <> 0)
	{
		$access_type[] = 'view';
	}
	if ($upload_check <> 0)
	{
		$access_type[] = 'upload';
	}
	if ($rate_check <> 0)
	{
		$access_type[] = 'rate';
	}
	if ($comment_check <> 0)
	{
		$access_type[] = 'comment';
	}
	if ($edit_check <> 0)
	{
		$access_type[] = 'edit';
	}
	if ($delete_check <> 0)
	{
		$access_type[] = 'delete';
	}
	//
	// END generating array $access_type
	//


	// --------------------------------
	// If everything is empty
	// --------------------------------
	if (empty($access_type) && ($moderator_check == 0))
	{
		//
		// Function EXIT here
		//
		return $album_user_access;
	}
	//
	// END check empty
	//


	// --------------------------------
	// Generate the SQL query based on $access_type and $moderator_check
	// --------------------------------
	$sql = 'SELECT album_id';

	for ($i = 0; $i < count($access_type); $i++)
	{
		$sql .= ', album_'. $access_type[$i] .'_level, album_'. $access_type[$i] .'_groups';
	}

	if ($moderator_check == 1)
	{
		$sql .= ', album_moderator_groups';
	}

	$sql .= '
			FROM ' . GALLERY_ALBUMS_TABLE . '
			WHERE album_id = ' . $album_id;
	//
	// END SQL query generating
	//


	// --------------------------------
	// Query the $sql then Fetchrow if $passed_auth == 0
	// --------------------------------
	if (!is_array($passed_auth))
	{
		$result = $db->sql_query($sql);
		$thiscat = $db->sql_fetchrow($result);
	}
	else
	{
		$thiscat = $passed_auth;
	}


	// --------------------------------
	// Maybe the access level is not PRIVATE or the groups list is empty
	// ... so we can skip some queries ;)
	// --------------------------------
	$groups_access = array();
	for ($i = 0; $i < count($access_type); $i++)
	{
		switch ($thiscat['album_' . $access_type[$i] . '_level'])
		{
			case ALBUM_GUEST:
				$album_user_access[$access_type[$i]] = 1;
			break;

			case ALBUM_USER:
				if ($user->data['is_registered'] && !$user->data['is_bot'])
				{
					$album_user_access[$access_type[$i]] = 1;
				}
			break;

			case ALBUM_PRIVATE:
				if( ($thiscat['album_' . $access_type[$i] . '_groups'] <> '') and ($user->data['is_registered']) )
				{
					$groups_access[] = $access_type[$i];
				}
			break;

			case ALBUM_MOD:
				// this will be checked later
			break;

			case ALBUM_ADMIN:
				// ADMIN already returned before at the checking code
				// at the top of this function. So this user cannot be authorised
				$album_user_access[$access_type[$i]] = 0;
			break;

			default:
				$album_user_access[$access_type[$i]] = 0;
			break;
		}
	}
	//
	// END Check Access Level
	//


	// --------------------------------
	// We can return now if $groups_access is empty AND $moderator_check == 0
	// --------------------------------
	if (($moderator_check == 1) and ($thiscat['album_moderator_groups'] <> ''))
	{
		// We can merge them now
		$groups_access[] = 'moderator';
	}

	if (empty($groups_access))
	{
		//
		// Function EXIT here
		//
		return $album_user_access;
	}


	// --------------------------------
	// Now we have the list of usergroups have PRIVATE/MODERATOR access
	// So we will check if this user is in these usergroups or not...
	// --------------------------------
	// upto (6 + 1) loops maximum when this user logged in and All Levels
	// are set to PRIVATE and this function was called to check all.
	// So avoiding PRIVATE will speed up your album. However, these queries are very fast
	for ($i = 0; $i < count($groups_access); $i++)
	{
		$sql = 'SELECT group_id, user_id
				FROM ' . USER_GROUP_TABLE . '
				WHERE user_id = ' . $user->data['user_id'] . ' 
					AND user_pending = 0
					AND group_id IN (' . $thiscat['album_' . $groups_access[$i] . '_groups'] . ')';
		$result = $db->sql_query($sql);

		if( $db->sql_affectedrows($result) > 0 )
		{
			$album_user_access[$groups_access[$i]] = 1;
		}
	}
	//
	// END check PRIVATE/MODERATOR groups
	//


	// --------------------------------
	// If $moderator_check was called and this user is a MODERATOR he
	// will be authorised for all accesses which were not set to ADMIN
	// --------------------------------
	if ($album_user_access['moderator'] && ($moderator_check == 1))
	{
		for ($i = 0; $i < count($album_user_access); $i++)
		{
			if(isset($thiscat['album_' . $album_user_access_keys[$i] . '_level']) && ($thiscat['album_' . $album_user_access_keys[$i] . '_level'] <> ALBUM_ADMIN))
			{
				$album_user_access[$album_user_access_keys[$i]] = 1;
			}
		}
	}
	return $album_user_access;
}
//we may delete this one:
function personal_album_access($user_id)
{
	global $db, $user, $album_config;

	$sql = 'SELECT MAX(g.view_personal_albums) as view_personal_albums, MAX(g.allow_personal_albums) as allow_personal_albums, MAX(g.personal_subalbums) as personal_subalbums
		FROM ' . GROUPS_TABLE . ' as g
		LEFT JOIN ' . USER_GROUP_TABLE . " as ug
			ON ug.group_id = g.group_id
		WHERE ug.user_id = {$user->data['user_id']}
			AND ug.user_pending = 0";
	$result = $db->sql_query($sql);
	$permission_data = $db->sql_fetchrow($result);
	$allowed = array(
		'view'		=> false,
		'upload'	=> false,
		'rate'		=> false,
		'comment'	=> false,
		'edit'		=> false,
		'delete'	=> false,
		'moderator'	=> false,
	);
	if ($album_config['rate'])
	{
		$allowed['rate'] = true;
	}
	if ($album_config['comment'])
	{
		$allowed['comment'] = true;
	}

	if ($permission_data['allow_personal_albums'] || $permission_data['view_personal_albums'])
	{
		$allowed['view'] = true;
	}

	if (($user_id == $user->data['user_id']) || ($user->data['user_type'] == USER_FOUNDER))
	{
		$allowed['upload'] = true;
		$allowed['edit'] = true;
		$allowed['delete'] = true;
		$allowed['moderator'] = true;
	}

	return $allowed;
}

//we may delete this one:
function personal_gallery_access($check_view, $check_upload)
{
	global $db, $user, $album_config;

	// This array will contain the result
	$personal_gallery_access = array(
		'view' 		=> 0,
		'upload' 	=> 0,
	);

	// --------------------------------
	// Who can create personal gallery?
	// --------------------------------
	if ($check_upload)
	{
		switch ($album_config['personal_gallery'])
		{
			case ALBUM_USER:
				if ($user->data['is_registered'] && !$user->data['is_bot'])
				{
					$personal_gallery_access['upload'] = 1;
				}
			break;

			case ALBUM_PRIVATE:
				if ($user->data['is_registered'] && ($user->data['user_type'] == USER_FOUNDER))
				{
					$personal_gallery_access['upload'] = 1;
				}
				else if(!empty($album_config['personal_gallery_private']) && $user->data['is_registered'] && !$user->data['is_bot'])
				{
					$sql = 'SELECT group_id, user_id
						FROM ' . USER_GROUP_TABLE . '
						WHERE user_id = ' . $user->data['user_id'] . ' 
							AND user_pending = 0
							AND group_id IN (' . $album_config['personal_gallery_private'] . ')';
					$result = $db->sql_query($sql);

					if( $db->sql_affectedrows($result) > 0 )
					{
						$personal_gallery_access['upload'] = 1;
					}
				}
			break;

			case ALBUM_ADMIN:
				if ($user->data['is_registered'] && ($user->data['user_type'] == USER_FOUNDER))
				{
					$personal_gallery_access['upload'] = 1;
				}
			break;
		}
	}

	// --------------------------------
	// Who can view other personal gallery?
	// --------------------------------
	if ($check_view)
	{
		switch ($album_config['personal_gallery_view'])
		{
			case ALBUM_GUEST:
				$personal_gallery_access['view'] = 1;
			break;

			case ALBUM_USER:
				if ($user->data['is_registered'] && !$user->data['is_bot'])
				{
					$personal_gallery_access['view'] = 1;
				}
			break;

			case ALBUM_PRIVATE:
				if( ($user->data['is_registered']) && ($user->data['user_type'] == USER_FOUNDER) )
				{
					$personal_gallery_access['view'] = 1;
				}
				else if(!empty($album_config['personal_gallery_private']) && $user->data['is_registered'] && !$user->data['is_bot'])
				{
					$sql = 'SELECT group_id, user_id
							FROM ' . USER_GROUP_TABLE . '
							WHERE user_id = ' . $user->data['user_id'] . ' 
								AND user_pending = 0
								AND group_id IN (' . $album_config['personal_gallery_private'] . ')';
					$result = $db->sql_query($sql);

					if( $db->sql_affectedrows($result) > 0 )
					{
						$personal_gallery_access['view'] = 1;
					}
				}
			break;
		}
	}

	return $personal_gallery_access;
}

//we may delete this one:
function init_personal_gallery_cat($user_id = 0)
{
	global $user, $db, $lang, $album_config;

	if (!$user_id)
	{
		$user_id = $user->data['user_id'];
	}

	$sql = 'SELECT COUNT(image_id) AS count
		FROM ' . GALLERY_IMAGES_TABLE . '
		WHERE image_album_id = ' . PERSONAL_GALLERY . '
			AND image_user_id = ' . $user_id;

	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);
	$count = $row['count'];

	if ($user_id <> $user->data['user_id'])
	{
		$sql = 'SELECT user_id, username
			FROM ' . USERS_TABLE . '
			WHERE user_id = ' . $user_id;

		$result = $db->sql_query($sql);
		$user_row = $db->sql_fetchrow($result);
		$username = $user_row['username'];
	}
	else
	{
		$username = $user->data['username'];
	}

	$thiscat = array(
		'album_id'					=> 0,
		'parent_id'					=> 0,
		'left_id'					=> 0,
		'right_id'					=> 0,
		'album_parents'				=> '',
		'album_type'				=> 2,
		'album_name'				=> sprintf($user->lang['PERSONAL_ALBUM_OF_USER'], $username),
		'album_desc'				=> '',
		'album_desc_uid'			=> '',
		'album_desc_bitfield'		=> '',
		'album_desc_options'		=> '',
		'album_order'				=> 0,
		'count'						=> $count,
		'album_view_level'			=> $album_config['personal_gallery_view'],
		'album_upload_level'		=> $album_config['personal_gallery'],
		'album_rate_level'			=> $album_config['personal_gallery_view'],
		'album_comment_level'		=> $album_config['personal_gallery_view'],
		'album_edit_level'			=> $album_config['personal_gallery'],
		'album_delete_level'		=> $album_config['personal_gallery'],
		'album_view_groups'			=> $album_config['personal_gallery_private'],
		'album_upload_groups'		=> $album_config['personal_gallery_private'],
		'album_rate_groups'			=> $album_config['personal_gallery_private'],
		'album_comment_groups'		=> $album_config['personal_gallery_private'],
		'album_edit_groups'			=> $album_config['personal_gallery_private'],
		'album_delete_groups'		=> $album_config['personal_gallery_private'],
		'album_delete_groups'		=> $album_config['personal_gallery_private'],
		'album_moderator_groups'	=> '',
		'album_approval'			=> 0,
	);
	return $thiscat;
}

/**
* Get album children (for displaying the subalbums
*/
function get_album_children($album_id)
{
	global $db, $phpEx, $phpbb_root_path, $gallery_root_path;

	$rows = array();

	$sql = 'SELECT *
		FROM ' . GALLERY_ALBUMS_TABLE . "
		WHERE parent_id = $album_id
		ORDER BY left_id ASC";
	$result = $db->sql_query($sql);
	$navigation = '';

	while ($row = $db->sql_fetchrow($result))
	{
		$rows[] = $row;
		$navigation .= (($navigation) ? ', ' : '') . '<a href="' . append_sid("{$phpbb_root_path}{$gallery_root_path}album.$phpEx", 'album_id=' . $row['album_id']) . '">' . $row['album_name'] . '</a>';
	}
	$db->sql_freeresult($result);

	return $navigation;
}
/**
* Get album details
*/
function get_album_info($album_id)
{
	global $db, $user, $gallery_root_path, $phpbb_root_path, $phpEx;

	$sql = 'SELECT a.*, w.watch_id
		FROM ' . GALLERY_ALBUMS_TABLE . ' AS a
		LEFT JOIN ' . GALLERY_WATCH_TABLE . ' AS w
			ON a.album_id = w.album_id
				AND w.user_id = ' . $user->data['user_id']  . "
		WHERE a.album_id = $album_id";
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);

	if (!$row)
	{
		meta_refresh(3, append_sid("{$phpbb_root_path}{$gallery_root_path}index.$phpEx"));
		trigger_error(sprintf($user->lang['ALBUM_ID_NOT_EXIST'], $album_id));
	}

	return $row;
}

function generate_album_nav(&$album_data)
{
	global $db, $user, $template, $auth;
	global $phpEx, $phpbb_root_path, $gallery_root_path;

	// Get album parents
	$album_parents = get_album_parents($album_data);
	if ($album_data['album_user_id'] > 0 )
	{
		$sql = 'SELECT user_id, username, user_colour
			FROM ' . USERS_TABLE . '
			WHERE user_id = ' . $album_data['album_user_id'] . '
			LIMIT 1';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$template->assign_block_vars('navlinks', array(
				'FORUM_NAME'	=> $user->lang['PERSONAL_ALBUMS'],
				'U_VIEW_FORUM'	=> append_sid("{$phpbb_root_path}{$gallery_root_path}index.$phpEx", 'mode=personal'))
			);
		}
	}

	// Build navigation links
	if (!empty($album_parents))
	{
		foreach ($album_parents as $parent_album_id => $parent_data)
		{
			list($parent_name, $parent_type) = array_values($parent_data);

			$template->assign_block_vars('navlinks', array(
				'FORUM_NAME'	=> $parent_name,
				'FORUM_ID'		=> $parent_album_id,
				'U_VIEW_FORUM'	=> append_sid("{$phpbb_root_path}{$gallery_root_path}album.$phpEx", 'album_id=' . $parent_album_id))
			);
		}
	}

	$template->assign_block_vars('navlinks', array(
		'FORUM_NAME'	=> $album_data['album_name'],
		'FORUM_ID'		=> $album_data['album_id'],
		'U_VIEW_FORUM'	=> append_sid("{$phpbb_root_path}{$gallery_root_path}album.$phpEx", 'album_id=' . $album_data['album_id']))
	);
	$template->assign_vars(array(
		'ALBUM_ID' 		=> $album_data['album_id'],
		'ALBUM_NAME'	=> $album_data['album_name'],
		'ALBUM_DESC'	=> generate_text_for_display($album_data['album_desc'], $album_data['album_desc_uid'], $album_data['album_desc_bitfield'], $album_data['album_desc_options']))
	);
	return;
}

/**
* Returns album parents as an array. Get them from album_data if available, or update the database otherwise
*/
function get_album_parents(&$album_data)
{
	global $db;

	$album_parents = array();
	if ($album_data['parent_id'] > 0)
	{
		if ($album_data['album_parents'] == '')
		{
			$sql = 'SELECT album_id, album_name, album_type
				FROM ' . GALLERY_ALBUMS_TABLE . '
				WHERE left_id < ' . $album_data['left_id'] . '
					AND right_id > ' . $album_data['right_id'] . '
					AND album_user_id = ' . $album_data['album_user_id'] . '
				ORDER BY left_id ASC';
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$album_parents[$row['album_id']] = array($row['album_name'], (int) $row['album_type']);
			}
			$db->sql_freeresult($result);

			$album_data['album_parents'] = serialize($album_parents);

			$sql = 'UPDATE ' . GALLERY_ALBUMS_TABLE . "
				SET album_parents = '" . $db->sql_escape($album_data['album_parents']) . "'
				WHERE parent_id = " . $album_data['parent_id'];
			$db->sql_query($sql);
		}
		else
		{
			$album_parents = unserialize($album_data['album_parents']);
		}
	}

	return $album_parents;
}

/**
* make the jump box, parent_album box, etc
*/
function make_album_jumpbox($select_id = false, $ignore_id = false, $album = false, $ignore_acl = false, $ignore_nonpost = false, $ignore_emptycat = true, $only_acl_post = false, $return_array = false)
{
	global $db, $user, $auth, $album_access_array;

	// no permissions yet
	$acl = ($ignore_acl) ? '' : (($only_acl_post) ? 'f_post' : array('f_list', 'a_forum', 'a_forumadd', 'a_forumdel'));

	// This query is identical to the jumpbox one
	$sql = 'SELECT *
		FROM ' . GALLERY_ALBUMS_TABLE . '
		WHERE album_user_id = 0
		ORDER BY left_id ASC';
	$result = $db->sql_query($sql, 600);

	$right = 0;
	$padding_store = array('0' => '');
	$padding = '';
	$forum_list = ($return_array) ? array() : '';

	// Sometimes it could happen that forums will be displayed here not be displayed within the index page
	// This is the result of forums not displayed at index, having list permissions and a parent of a forum with no permissions.
	// If this happens, the padding could be "broken"

	while ($row = $db->sql_fetchrow($result))
	{
		if ($row['left_id'] < $right)
		{
			$padding .= '&nbsp; &nbsp;';
			$padding_store[$row['parent_id']] = $padding;
		}
		else if ($row['left_id'] > $right + 1)
		{
			$padding = (isset($padding_store[$row['parent_id']])) ? $padding_store[$row['parent_id']] : '';
		}

		$right = $row['right_id'];
		$disabled = false;

		if (((is_array($ignore_id) && in_array($row['album_id'], $ignore_id)) || $row['album_id'] == $ignore_id) || ($album && !$row['album_type']))
		{
			$disabled = true;
		}

		if ($album_access_array[$row['album_id']]['i_view'] == 1)
		{
			if ($return_array)
			{
				// Include some more information...
				$selected = (is_array($select_id)) ? ((in_array($row['album_id'], $select_id)) ? true : false) : (($row['album_id'] == $select_id) ? true : false);
				$forum_list[$row['album_id']] = array_merge(array('padding' => $padding, 'selected' => ($selected && !$disabled), 'disabled' => $disabled), $row);
			}
			else
			{
				$selected = (is_array($select_id)) ? ((in_array($row['album_id'], $select_id)) ? ' selected="selected"' : '') : (($row['album_id'] == $select_id) ? ' selected="selected"' : '');
				$forum_list .= '<option value="' . $row['album_id'] . '"' . (($disabled) ? ' disabled="disabled" class="disabled-option"' : $selected) . '>' . $padding . $row['album_name'] . '</option>';
			}
		}
	}
	$db->sql_freeresult($result);
	unset($padding_store);

	return $forum_list;
}

function make_personal_jumpbox($album_user_id, $select_id = false, $ignore_id = false, $album = false, $ignore_acl = false, $ignore_nonpost = false, $ignore_emptycat = true, $only_acl_post = false, $return_array = false)
{
	global $db, $user, $auth, $album_access_array;

	// This query is identical to the jumpbox one
	$sql = 'SELECT *
		FROM ' . GALLERY_ALBUMS_TABLE . "
		WHERE album_user_id = $album_user_id
		ORDER BY left_id ASC";
	$result = $db->sql_query($sql, 600);

	$right = 0;
	$padding_store = array('0' => '');
	$padding = '';
	$forum_list = ($return_array) ? array() : '';

	// Sometimes it could happen that forums will be displayed here not be displayed within the index page
	// This is the result of forums not displayed at index, having list permissions and a parent of a forum with no permissions.
	// If this happens, the padding could be "broken"

	while ($row = $db->sql_fetchrow($result))
	{
		if ($row['left_id'] < $right)
		{
			$padding .= '&nbsp; &nbsp;';
			$padding_store[$row['parent_id']] = $padding;
		}
		else if ($row['left_id'] > $right + 1)
		{
			$padding = (isset($padding_store[$row['parent_id']])) ? $padding_store[$row['parent_id']] : '';
		}

		$right = $row['right_id'];
		$disabled = false;

		if (((is_array($ignore_id) && in_array($row['album_id'], $ignore_id)) || $row['album_id'] == $ignore_id) || ($album && !$row['album_type']))
		{
			$disabled = true;
		}

		if ($album_access_array[$row['album_id']]['i_view'] == 1)
		{
			if ($return_array)
			{
				// Include some more information...
				$selected = (is_array($select_id)) ? ((in_array($row['album_id'], $select_id)) ? true : false) : (($row['album_id'] == $select_id) ? true : false);
				$forum_list[$row['album_id']] = array_merge(array('padding' => $padding, 'selected' => ($selected && !$disabled), 'disabled' => $disabled), $row);
			}
			else
			{
				$selected = (is_array($select_id)) ? ((in_array($row['album_id'], $select_id)) ? ' selected="selected"' : '') : (($row['album_id'] == $select_id) ? ' selected="selected"' : '');
				$forum_list .= '<option value="' . $row['album_id'] . '"' . (($disabled) ? ' disabled="disabled" class="disabled-option"' : $selected) . '>' . $padding . $row['album_name'] . '</option>';
			}
		}
	}
	$db->sql_freeresult($result);
	unset($padding_store);

	return $forum_list;
}

function make_move_jumpbox($select_id = false, $ignore_id = false, $album = false, $ignore_acl = false, $ignore_nonpost = false, $ignore_emptycat = true, $only_acl_post = false, $return_array = false)
{
	global $db, $user, $auth, $album_access_array;

	// This query is identical to the jumpbox one
	$sql = 'SELECT *
		FROM ' . GALLERY_ALBUMS_TABLE . "
		ORDER BY album_user_id, left_id ASC";
	$result = $db->sql_query($sql);

	$album_user_id = $right = 0;
	$padding_store = array('0' => '');
	$padding = '';
	$forum_list = ($return_array) ? array() : '';
	$personal_info = false;

	// Sometimes it could happen that forums will be displayed here not be displayed within the index page
	// This is the result of forums not displayed at index, having list permissions and a parent of a forum with no permissions.
	// If this happens, the padding could be "broken"

	while ($row = $db->sql_fetchrow($result))
	{
		if (($row['album_user_id'] > 0) && !$personal_info)
		{
			$personal_info = true;
			$forum_list .= '<option disabled="disabled" class="disabled-option">' . $user->lang['PERSONAL_ALBUMS'] . '</option>';
		}
		if ($album_access_array[$row['album_id']]['i_view'] == 1)
		{
			if ($album_user_id == $row['album_user_id'])
			{
				if ($row['left_id'] < $right)
				{
					$padding .= '&nbsp; &nbsp;';
					$padding_store[$row['parent_id']] = $padding;
				}
				else if ($row['left_id'] > $right + 1)
				{
					$padding = (isset($padding_store[$row['parent_id']])) ? $padding_store[$row['parent_id']] : '';
				}
			}
			else
			{
				$padding = '';
			}

			$right = $row['right_id'];
			$album_user_id = $row['album_user_id'];
			$disabled = false;

			if (($album_access_array[$row['album_id']]['i_upload'] != 1))
			{
				$disabled = true;
			}

			if ($return_array)
			{
				// Include some more information...
				$selected = (is_array($select_id)) ? ((in_array($row['album_id'], $select_id)) ? true : false) : (($row['album_id'] == $select_id) ? true : false);
				$forum_list[$row['album_id']] = array_merge(array('padding' => $padding, 'selected' => ($selected && !$disabled), 'disabled' => $disabled), $row);
			}
			else
			{
				$selected = (is_array($select_id)) ? ((in_array($row['album_id'], $select_id)) ? ' selected="selected"' : '') : (($row['album_id'] == $select_id) ? ' selected="selected"' : '');
				$forum_list .= '<option value="' . $row['album_id'] . '"' . (($disabled) ? ' disabled="disabled" class="disabled-option"' : $selected) . '>' . $padding . $row['album_name'] . '</option>';
			}
		}
	}
	$db->sql_freeresult($result);
	unset($padding_store);

	return $forum_list;
}
/**
* get image info
*/
function get_image_info($image_id)
{
	global $db, $user;

	$sql = 'SELECT *
		FROM ' . GALLERY_IMAGES_TABLE . ' i
		LEFT JOIN ' . GALLERY_WATCH_TABLE . ' w
			ON w.image_id = i.image_id
				AND w.user_id = ' . $user->data['user_id']  . '
		LEFT JOIN ' . GALLERY_FAVORITES_TABLE . ' f
			ON f.image_id = i.image_id
				AND f.user_id = ' . $user->data['user_id']  . '
		WHERE i.image_id = ' . $image_id;
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);

	if (!$row)
	{
		trigger_error('IMAGE_NOT_EXIST');
	}

	return $row;
}

/**
* Update Album-Information
*/
function update_lastimage_info($album_id)
{
	global $db, $user;

	//update album-information
	$images_real = $images = $album_user_id = 0;
	$sql = 'SELECT album_user_id
		FROM ' . GALLERY_ALBUMS_TABLE . "
		WHERE album_id = $album_id";
	$result = $db->sql_query($sql);
	if ($row = $db->sql_fetchrow($result))
	{
		$album_user_id = $row['album_user_id'];
	}
	$db->sql_freeresult($result);
	$sql = 'SELECT COUNT(image_id) images
		FROM ' . GALLERY_IMAGES_TABLE . "
		WHERE image_album_id = $album_id
			AND image_status = 1";
	$result = $db->sql_query($sql);
	if ($row = $db->sql_fetchrow($result))
	{
		$images = $row['images'];
	}
	$db->sql_freeresult($result);
	$sql = 'SELECT COUNT(image_id) images_real
		FROM ' . GALLERY_IMAGES_TABLE . "
		WHERE image_album_id = $album_id";
	$result = $db->sql_query($sql);
	if ($row = $db->sql_fetchrow($result))
	{
		$images_real = $row['images_real'];
	}
	$db->sql_freeresult($result);
	$sql = 'SELECT *
		FROM ' . GALLERY_IMAGES_TABLE . "
		WHERE image_album_id = $album_id
			AND image_status = 1
		ORDER BY image_time DESC
		LIMIT 1";
	$result = $db->sql_query($sql);
	if ($row = $db->sql_fetchrow($result))
	{
		$sql_ary = array(
			'album_images_real'			=> $images_real,
			'album_images'				=> $images,
			'album_last_image_id'		=> $row['image_id'],
			'album_last_image_time'		=> $row['image_time'],
			'album_last_image_name'		=> $row['image_name'],
			'album_last_username'		=> $row['image_username'],
			'album_last_user_colour'	=> $row['image_user_colour'],
			'album_last_user_id'		=> $row['image_user_id'],
		);
	}
	else
	{
		$sql_ary = array(
			'album_images_real'			=> 0,
			'album_images'				=> 0,
			'album_last_image_id'		=> 0,
			'album_last_image_time'		=> 0,
			'album_last_image_name'		=> '',
			'album_last_username'		=> '',
			'album_last_user_colour'	=> '',
			'album_last_user_id'		=> 0,
		);
		if ($album_user_id)
		{
			unset($sql_ary['album_last_user_colour']);
		}
	}
	$db->sql_freeresult($result);
	$sql = 'UPDATE ' . GALLERY_ALBUMS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
		WHERE ' . $db->sql_in_set('album_id', $album_id);
	$db->sql_query($sql);

	return $row;
}

/**
* Obtain list of moderators of each album
*/
function get_album_moderators(&$album_moderators, $album_id = false)
{
	global $config, $template, $db, $phpbb_root_path, $phpEx, $user;

	// Have we disabled the display of moderators? If so, then return
	// from whence we came ...
	if (!$config['load_moderators'])
	{
		return;
	}

	$album_sql = '';

	if ($album_id !== false)
	{
		if (!is_array($album_id))
		{
			$album_id = array($album_id);
		}

		// If we don't have a forum then we can't have a moderator
		if (!sizeof($album_id))
		{
			return;
		}

		$album_sql = 'AND m.' . $db->sql_in_set('album_id', $album_id);
	}

	$sql_array = array(
		'SELECT'	=> 'm.*, u.user_colour, g.group_colour, g.group_type',

		'FROM'		=> array(
			GALLERY_MODSCACHE_TABLE	=> 'm',
		),

		'LEFT_JOIN'	=> array(
			array(
				'FROM'	=> array(USERS_TABLE => 'u'),
				'ON'	=> 'm.user_id = u.user_id',
			),
			array(
				'FROM'	=> array(GROUPS_TABLE => 'g'),
				'ON'	=> 'm.group_id = g.group_id',
			),
		),

		'WHERE'		=> "m.display_on_index = 1 $album_sql",
	);

	$sql = $db->sql_build_query('SELECT', $sql_array);
	$result = $db->sql_query($sql, 3600);

	while ($row = $db->sql_fetchrow($result))
	{
		if (!empty($row['user_id']))
		{
			$album_moderators[$row['album_id']][] = get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']);
		}
		else
		{
			$album_moderators[$row['album_id']][] = '<a' . (($row['group_colour']) ? ' style="color:#' . $row['group_colour'] . ';"' : '') . ' href="' . append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=group&amp;g=' . $row['group_id']) . '">' . (($row['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $row['group_name']] : $row['group_name']) . '</a>';
		}
	}
	$db->sql_freeresult($result);

	return;
}

?>