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
//new start here
$sql = 'SELECT a.*, COUNT(pc.image_id) AS count, MAX(pc.image_id) as last_image
	FROM ' . GALLERY_ALBUMS_TABLE . ' AS a
	LEFT JOIN ' . GALLERY_ALBUMS_TABLE . ' AS sa
		ON ( sa.left_id < a.right_id
			AND sa.left_id > a.left_id )
	LEFT JOIN ' . GALLERY_IMAGES_TABLE . ' AS pc
		ON ( pc.image_album_id = sa.album_id
			OR pc.image_album_id = a.album_id )
	WHERE a.album_id <> 0
		AND a.parent_id = ' . $album_id . '
	GROUP BY a.album_id
	ORDER BY a.left_id ASC';
$result = $db->sql_query($sql);

$album = array();

while( $row = $db->sql_fetchrow($result) )
{
	$album_user_access = album_user_access($row['album_id'], $row, 1, 0, 0, 0, 0, 0);
	if ($album_user_access['view'] == 1)
	{
		$album[] = $row;
	}
}

for ($i = 0; $i < count($album); $i++)
{
	/**
	* Build moderators list
	*/
	$l_moderators = '';
	$moderators_list = '';
	$grouprows= array();

	if ($album[$i]['album_moderator_groups'] != 0)
	{
		// We have usergroup_ID, now we need usergroup name
		$sql = 'SELECT group_id, group_name, group_type
				FROM ' . GROUPS_TABLE . '
				WHERE group_type <> ' . GROUP_HIDDEN . '
					AND group_id IN (' . $album[$i]['album_moderator_groups'] . ')
				ORDER BY group_name ASC';
		$result = $db->sql_query($sql);

		while( $row = $db->sql_fetchrow($result) )
		{
			$grouprows[] = $row;
		}
		if (count($grouprows) > 1)
		{
			$l_moderators = $user->lang['MODERATORS'];

			for ($j = 0; $j < count($grouprows); $j++)
			{
				$group_name = ($grouprows[$j]['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $grouprows[$j]['group_name']] : $grouprows[$j]['group_name'];
				$group_link = '<a href="' . append_sid("{$phpbb_root_path}memberlist.$phpEx?mode=group&g=" . $grouprows[$j]['group_id']) . '">' . $group_name . '</a>';

				$moderators_list .= ($moderators_list == '') ? $group_link : ', ' . $group_link;
			}
		}
		else if (count($grouprows) > 0)
		{
			$l_moderators = $user->lang['MODERATOR'];
			for ($j = 0; $j < count($grouprows); $j++)
			{
				$group_name = ($grouprows[$j]['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $grouprows[$j]['group_name']] : $grouprows[$j]['group_name'];
				$moderators_list = '<a href="' . append_sid("{$phpbb_root_path}memberlist.$phpEx?mode=group&g=" . $grouprows[$j]['group_id']) . '">' . $group_name . '</a>';
			}
		}
	}


	// ------------------------------------------
	// Get Last Pic of this Category
	// ------------------------------------------

	if ($album[$i]['count'] != 0)
	{
		// ----------------------------
		// Check Pic Approval
		// ----------------------------
		if (($album[$i]['album_approval'] == ALBUM_ADMIN) || ($album[$i]['album_approval'] == ALBUM_MOD))
		{
			$pic_approval_sql = 'AND i.image_approval = 1';
		}
		else
		{
			$pic_approval_sql = '';
		}
		// ----------------------------
		// OK, we may do a query now...
		// ----------------------------
		$sql = 'SELECT i.image_id, i.image_name, i.image_user_id, i.image_username, i.image_user_colour, i.image_time, i.image_album_id
				FROM ' . GALLERY_IMAGES_TABLE . ' AS i
				WHERE i.image_id = ' . $album[$i]['last_image'] . ' ' . $pic_approval_sql . ' 
				ORDER BY i.image_time DESC';
		$result = $db->sql_query($sql);
		$lastrow = $db->sql_fetchrow($result);
	}
	if ($album[$i]['left_id'] + 1 != $album[$i]['right_id'])
	{
		$folder_image = 'forum_read_subforum';
		$folder_alt = 'no';
		$l_subalbums = $user->lang['SUBALBUM'];
		if ($album[$i]['left_id'] + 3 != $album[$i]['right_id'])
		{
			$l_subalbums = $user->lang['SUBALBUMS'];
		}
	}
	else
	{
		$folder_image = 'forum_read';
		$l_subalbums = '';
		$folder_alt = 'no';
	}
	//$folder_image = ($album[$i]['left_id'] + 1 != $album[$i]['right_id']) ? '<img src="images/icon_subfolder.gif" alt="' . $user->lang['SUBFORUM'] . '" />' : '<img src="images/icon_folder.gif" alt="' . $user->lang['FOLDER'] . '" />';
	// END of Last Pic

	// ------------------------------------------
	// Parse to template the info of the current Category
	// ------------------------------------------

	$template->assign_block_vars('albumrow', array(
		'U_VIEW_ALBUM'			=> append_sid($phpbb_root_path . "gallery/album.$phpEx?id=" . $album[$i]['album_id']),
		'ALBUM_NAME'			=> $album[$i]['album_name'],
		'ALBUM_FOLDER_IMG_SRC'	=> $user->img($folder_image, $folder_alt, false, '', 'src'),
		'SUBALBUMS'				=> get_album_children($album[$i]['album_id']),
		'ALBUM_DESC'			=> generate_text_for_display($album[$i]['album_desc'], $album[$i]['album_desc_uid'], $album[$i]['album_desc_bitfield'], $album[$i]['album_desc_options']),
		'L_MODERATORS'			=> $l_moderators,
		'L_SUBALBUMS'			=> $l_subalbums,
		'MODERATORS'			=> $moderators_list,
		'IMAGES'				=> $album[$i]['count'],
		'U_LAST_IMAGE'			=> ($album[$i]['count'] != 0) ? append_sid("{$phpbb_root_path}gallery/image_page.$phpEx" , 'image_id=' . $lastrow['image_id']) : '',
		'LAST_IMAGE_NAME'		=> ($album[$i]['count'] != 0) ? $lastrow['image_name'] : '',
		'LAST_IMAGE_AUTHOR'		=> ($album[$i]['count'] != 0) ? get_username_string('full', $lastrow['image_user_id'], ($lastrow['image_user_id'] <> ANONYMOUS) ? $lastrow['image_username'] : $user->lang['GUEST'], $lastrow['image_user_colour']) : '',
		'LAST_IMAGE_TIME'		=> ($album[$i]['count'] != 0) ? $user->format_date($lastrow['image_time']) : '',
	));
}
$template->assign_vars(array(
	'LAST_POST_IMG'				=> $user->img('icon_topic_latest', 'VIEW_THE_LATEST_IMAGE'),
));
?>