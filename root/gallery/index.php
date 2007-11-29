<?php

/**
*
* @package phpBB3
* @version $Id$
* @copyright (c) 2007 phpBB Gallery
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : '../';
$album_root_path = $phpbb_root_path . 'gallery/';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
include_once($phpbb_root_path . 'includes/message_parser.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('mods/gallery');


//
// Get general album information
//
include($album_root_path . 'includes/common.'.$phpEx);


/*
+----------------------------------------------------------
| Build Categories Index
+----------------------------------------------------------
*/

$sql = 'SELECT a.*, COUNT(p.image_id) AS count, MAX(p.image_id) as last_image
		FROM ' . GALLERY_ALBUMS_TABLE . ' AS a
		LEFT JOIN ' . GALLERY_ALBUMS_TABLE . ' AS sa
			ON ( sa.left_id < a.right_id
				AND sa.left_id > a.left_id )
		LEFT JOIN ' . GALLERY_IMAGES_TABLE . ' AS p
			ON ( p.image_album_id = sa.album_id
				OR p.image_album_id = a.album_id )
		WHERE a.album_id <> 0
			AND a.parent_id = 0
		GROUP BY a.album_id
		ORDER BY a.left_id ASC';
$result = $db->sql_query($sql);

$album = array();

while( $row = $db->sql_fetchrow($result) )
{
	$album_user_access = album_user_access($row['album_id'], $row, 1, 0, 0, 0, 0, 0); // VIEW
	if ($album_user_access['view'] == 1)
	{
		$album[] = $row;
	}
}

$allowed_cat = '';

//
// $albums now stores all categories which this user can view. Dump them out!
//
for ($i = 0; $i < count($album); $i++)
{
	// --------------------------------
	// Build allowed category-list (for recent pics after here)
	// --------------------------------

	$allowed_cat .= ($allowed_cat == '') ? $album[$i]['album_id'] : ',' . $album[$i]['album_id'];


	// --------------------------------
	// Build moderators list
	// --------------------------------

	$l_moderators = '';
	$moderators_list = '';

	$grouprows= array();

	if( $album[$i]['album_moderator_groups'] <> '')
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
	}

	if( count($grouprows) > 0 )
	{
		$l_moderators = $user->lang['MODERATORS'];

		for ($j = 0; $j < count($grouprows); $j++)
		{
			$group_name = ($grouprows[$j]['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $grouprows[$j]['group_name']] : $grouprows[$j]['group_name'];
			$group_link = '<a href="' . append_sid("{$phpbb_root_path}memberlist.$phpEx?mode=group&g=" . $grouprows[$j]['group_id']) . '">' . $group_name . '</a>';

			$moderators_list .= ($moderators_list == '') ? $group_link : ', ' . $group_link;
		}
	}


	// ------------------------------------------
	// Get Last Pic of this Category
	// ------------------------------------------

	if ($album[$i]['count'] == 0)
	{
		//
		// Oh, this category is empty
		//
		$last_pic_info = $user->lang['NO_IMAGES'];
		$u_last_pic = '';
		$last_pic_title = '';
	}
	else
	{
		// ----------------------------
		// Check Pic Approval
		// ----------------------------

		if (($album[$i]['album_approval'] == ALBUM_ADMIN) || ($album[$i]['album_approval'] == ALBUM_MOD))
		{
			$pic_approval_sql = 'AND p.image_approval = 1';
		}
		else
		{
			$pic_approval_sql = '';
		}


		// ----------------------------
		// OK, we may do a query now...
		// ----------------------------

		$sql = 'SELECT p.image_id, p.image_name, p.image_user_id, p.image_username, p.image_time, p.image_album_id, u.user_id, u.username, u.user_colour
				FROM ' . GALLERY_IMAGES_TABLE . ' AS p
				LEFT JOIN ' . USERS_TABLE . ' AS u
					ON p.image_user_id = u.user_id
				WHERE p.image_id = ' . $album[$i]['last_image'] . ' ' . $pic_approval_sql . ' 
				ORDER BY p.image_time DESC';
		$result = $db->sql_query($sql);
		$lastrow = $db->sql_fetchrow($result);

		$last_pic_info = '';
		$last_pic_info .= '<dfn>' . $user->lang['LAST_IMAGE'] . '</dfn> ';
		if( !isset($album_config['last_pic_title_length']) )
		{
			$album_config['last_pic_title_length'] = 25;
		}
		if (strlen($lastrow['image_name']) > $album_config['last_pic_title_length'])
		{
			$lastrow['image_name'] = substr($lastrow['image_name'], 0, $album_config['last_pic_title_length']) . '...';
		}
		$last_pic_info .= '<a href="' . append_sid("{$album_root_path}image_page.$phpEx?id=" . $lastrow['image_id']) . '" style="font-weight: bold;">';
		$last_pic_info .= $lastrow['image_name'] . '</a><br />' . $user->lang['POST_BY_AUTHOR'] . ' ';
		$last_pic_info .= get_username_string('full', $lastrow['user_id'], ($lastrow['user_id'] <> ANONYMOUS) ? $lastrow['username'] : $user->lang['GUEST'], $lastrow['user_colour']);
//		$last_pic_info .= '<a href="' . append_sid("../memberlist.$phpEx?mode=viewprofile&amp;u=". $lastrow['user_id']) .'" style="color: #' . $user->data['user_colour'] . ';" class="username-coloured">'. $lastrow['username'] .'</a> ';
		$last_pic_info .= '<a href="' . append_sid("{$album_root_path}image_page.$phpEx?id=" . $lastrow['image_id']) . '"><img src="' . $phpbb_root_path . 'styles/prosilver/imageset/icon_topic_latest.gif" width="11" height="9" alt="' . $user->lang['VIEW_THE_LATEST_IMAGE'] . '" title="' . $user->lang['VIEW_THE_LATEST_IMAGE'] . '" /></a><br />';
		$last_pic_info .= $user->lang['POSTED_ON_DATE'] . ' ' . $user->format_date($lastrow['image_time']);
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
		'U_VIEW_CAT' 		=> append_sid("album.$phpEx?id=" . $album[$i]['album_id']),
		'CAT_TITLE' 		=> $album[$i]['album_name'],
		'ALBUM_FOLDER_IMG_SRC'		=> $user->img($folder_image, $folder_alt, false, '', 'src'),
		'SUBALBUMS'			=> get_album_children($album[$i]['album_id']),
		'CAT_DESC' 			=> generate_text_for_display($album[$i]['album_desc'], $album[$i]['album_desc_uid'], $album[$i]['album_desc_bitfield'], $album[$i]['album_desc_options']),
		'L_MODERATORS' 		=> $l_moderators,
		'L_SUBALBUMS' 		=> $l_subalbums,
		'MODERATORS' 		=> $moderators_list,
		'PICS' 				=> $album[$i]['count'],
		'LAST_PIC_INFO' 	=> $last_pic_info)
	);
}
// END of Categories Index


/*
+----------------------------------------------------------
| Recent Public Pics
+----------------------------------------------------------
*/

if ($allowed_cat <> '')
{
	$sql = 'SELECT p.*, u.user_id, u.username, u.user_colour, r.rate_image_id, AVG(r.rate_point) AS rating, COUNT(DISTINCT c.comment_id) AS comments
			FROM ' . GALLERY_IMAGES_TABLE . ' AS p
				LEFT JOIN ' . USERS_TABLE . ' AS u ON p.image_user_id = u.user_id
				LEFT JOIN ' . GALLERY_ALBUMS_TABLE . ' AS ct ON p.image_album_id = ct.album_id
				LEFT JOIN ' . GALLERY_RATES_TABLE . ' AS r ON p.image_id = r.rate_image_id
				LEFT JOIN ' . GALLERY_COMMENTS_TABLE . ' AS c ON p.image_id = c.comment_image_id
			WHERE p.image_album_id IN (' . $allowed_cat . ') 
				AND ( p.image_approval = 1 OR ct.album_approval = 0 )
			GROUP BY p.image_id
			ORDER BY p.image_time DESC
			LIMIT ' . $album_config['cols_per_page'];
	$result = $db->sql_query($sql);

	$recentrow = array();

	while( $row = $db->sql_fetchrow($result) )
	{
		$recentrow[] = $row;
	}


	if (count($recentrow) > 0)
	{
		for ($i = 0; $i < count($recentrow); $i += $album_config['cols_per_page'])
		{
			$template->assign_block_vars('recent_pics', array());

			for ($j = $i; $j < ($i + $album_config['cols_per_page']); $j++)
			{
				if( $j >= count($recentrow) )
				{
					break;
				}

				if(!$recentrow[$j]['rating'])
				{
					$recentrow[$j]['rating'] = $user->lang['NOT_RATED'];
				}
				else
				{
					$recentrow[$j]['rating'] = round($recentrow[$j]['rating'], 2);
				}
				$message_parser				= new parse_message();
				$message_parser->message	= $recentrow[$j]['image_desc'];
				$message_parser->decode_message($recentrow[$j]['image_desc_uid']);
				$template->assign_block_vars('recent_pics.recent_col', array(
					'U_PIC' 		=> ($album_config['fullpic_popup']) ? append_sid("{$album_root_path}image.$phpEx?pic_id=". $recentrow[$j]['image_id']) : append_sid("{$album_root_path}image_page.$phpEx?id=". $recentrow[$j]['image_id']),
					'THUMBNAIL' 	=> append_sid("{$album_root_path}thumbnail.$phpEx?pic_id=". $recentrow[$j]['image_id']),
					'DESC' 			=> $message_parser->message,
					)
				);

				if ($recentrow[$j]['user_id'] == ALBUM_GUEST)
				{
					$recent_poster = ($recentrow[$j]['image_username'] == '') ? $user->lang['GUEST'] : $recentrow[$j]['image_username'];
				}

				$template->assign_block_vars('recent_pics.recent_detail', array(
					'TITLE'			=> $recentrow[$j]['image_name'],
					'POSTER_FULL'	=> get_username_string('full', $recentrow[$j]['user_id'], ($recentrow[$j]['user_id'] <> ANONYMOUS) ? $recentrow[$j]['username'] : $user->lang['GUEST'], $recentrow[$j]['user_colour']),
					'TIME'			=> $user->format_date($recentrow[$j]['image_time']),
					'VIEW'			=> $recentrow[$j]['image_view_count'],
					'RATING'		=> ($album_config['rate'] == 1) ? ( '<a href="' . append_sid("{$album_root_path}image_page.$phpEx?id=" . $recentrow[$j]['image_id']) . '#rating">' . $user->lang['RATING'] . '</a>: ' . $recentrow[$j]['rating'] . '<br />') : '',
					'COMMENTS'		=> ($album_config['comment'] == 1) ? ( '<a href="' . append_sid("{$album_root_path}image_page.$phpEx?id=" . $recentrow[$j]['image_id']) . '#comments">' . $user->lang['COMMENTS'] . '</a>: ' . $recentrow[$j]['comments'] . '<br />') : '',
					'IP'			=> ($user->data['user_type'] == USER_FOUNDER) ? $user->lang['IP'] . ': <a href="http://www.nic.com/cgi-bin/whois.cgi?query=' . $recentrow[$j]['image_user_ip'] . '" target="_blank">' . $recentrow[$j]['image_user_ip'] . '</a><br />' : ''
				));
			}
		}
	}
	else
	{
		$template->assign_block_vars('no_pics', array());
	}
}
else
{
	$template->assign_block_vars('no_pics', array());
}


/*
+----------------------------------------------------------
| Start output the page
+----------------------------------------------------------
*/

$template->assign_vars(array(
	'U_YOUR_PERSONAL_GALLERY' 		=> append_sid("{$album_root_path}album_personal.$phpEx?user_id=" . $user->data['user_id']),
	'U_USERS_PERSONAL_GALLERIES' 	=> append_sid("{$album_root_path}album_personal_index.$phpEx"),

	'S_LOGIN_ACTION'				=> append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=login'),
	'S_COLS' 						=> $album_config['cols_per_page'],
	'S_COL_WIDTH' 					=> (100/$album_config['cols_per_page']) . '%',
	'TARGET_BLANK' 					=> ($album_config['fullpic_popup']) ? 'target="_blank"' : '',
));

$template->assign_block_vars('navlinks', array(
	'FORUM_NAME'	=> $user->lang['GALLERY'],
	'U_VIEW_FORUM'	=> append_sid("{$album_root_path}index.$phpEx"),
	)
);

// Output page
$page_title = $user->lang['GALLERY'];

page_header($page_title);

$template->set_filenames(array(
	'body' => 'gallery_index_body.html')
);

page_footer();
?>