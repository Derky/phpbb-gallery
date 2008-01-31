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
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
include($phpbb_root_path . 'gallery/includes/common.'.$phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('mods/gallery');

/**
* Check the request
*/
$image_id = request_var('image_id', request_var('id', 0));
if (!$image_id)
{
	trigger_error($user->lang['NO_IMAGE_SPECIFIED'], E_USER_WARNING);
}
// ------------------------------------
// Salting the form...yumyum ...
// ------------------------------------
add_form_key('image_page');

/**
* Get the image info
*/
$image_data = get_image_info($image_id);
$album_id = $image_data['image_album_id'];
$user_id = $image_data['image_user_id'];
if (empty($image_data) || !file_exists(ALBUM_UPLOAD_PATH . $image_data['image_filename']))
{
	trigger_error($user->lang['IMAGE_NOT_EXIST'], E_USER_WARNING);
}
/**
* Get the album info of the images album
*/
if ($album_id <> PERSONAL_GALLERY)
{
	$album_data = get_album_info($album_id);
}
else
{
	$album_data = init_personal_gallery_cat($user_id);
}
if (empty($album_data))
{
	trigger_error($user->lang['ALBUM_NOT_EXIST'], E_USER_WARNING);
}

/**
* Check the permissions
*/
$album_user_access = album_user_access($album_id, $album_data, 1, 0, 1, 1, 1, 1);
if (!$album_user_access['view'])
{
	if (!$user->data['is_registered'])
	{
		login_box("gallery/image_page.$phpEx?image_id=$image_id", $user->lang['LOGIN_INFO']);
	}
	else
	{
		trigger_error($user->lang['NOT_AUTHORISED'], E_USER_WARNING);
	}
}

// ------------------------------------
// Check Pic Rating
// ------------------------------------

$already_rated = false;

if (($album_config['rate'] <> 0) && $user->data['is_registered'])
{
	$sql = 'SELECT *
		FROM ' . GALLERY_RATES_TABLE . '
		WHERE rate_image_id = ' . $image_id . '
			AND rate_user_id = ' . $user->data['user_id'] . '
		LIMIT 1';

	$result = $db->sql_query($sql);

	if ($db->sql_affectedrows($result) > 0)
	{
		$already_rated = true;
	}
}

// ------------------------------------
// Check Pic Approval
// ------------------------------------

if ($user->data['user_type'] <> USER_FOUNDER)
{
	if (($album_data['album_approval'] == ADMIN) || (($album_data['album_approval'] == MOD) || !$album_user_access['moderator']))
	{
		if (!$image_data['image_approval'])
		{
			trigger_error($user->lang['NOT_AUTHORISED'], E_USER_WARNING);
		}
	}
}

// ------------------------------------
// Posting Comments & Rating
// ------------------------------------

if (isset($_POST['comment']) || isset($_POST['rate']))
{
	// Check the salt... yumyum
	if (!check_form_key('image_page'))
	{
		trigger_error('FORM_INVALID');
	}

	include($phpbb_root_path . 'includes/functions_user.' . $phpEx);

	if (isset($_POST['comment']))
	{
		if (!$album_config['comment'] || !$album_user_access['comment'])
		{
			trigger_error($user->lang['NOT_AUTHORISED'], E_USER_WARNING);
		}
		$comment_text = substr(request_var('comment', '', true), 0, $album_config['desc_length']);
		$comment_username = (!$user->data['is_registered']) ? substr(request_var('comment_username', '', true), 0, 32) : $user->data['username'];
		if( empty($comment_text) )
		{
			// Build the navigation
			if ($album_id <> PERSONAL_GALLERY)
			{
				generate_album_nav($album_data);
			}
			else
			{
				$template->assign_block_vars('navlinks', array(
					'FORUM_NAME'	=> $user->lang['PERSONAL_ALBUMS'],
					'U_VIEW_FORUM'	=> append_sid("{$phpbb_root_path}gallery/album_personal_index.$phpEx"),
				));

				$template->assign_block_vars('navlinks', array(
					'FORUM_NAME'	=> sprintf($user->lang['PERSONAL_ALBUM_OF_USER'], $image_data['username']),
					'U_VIEW_FORUM'	=> append_sid("{$phpbb_root_path}gallery/album_personal.$phpEx", 'user_id=' . $user_id),
				));
			}
			trigger_error($user->lang['COMMENT_NO_TEXT'], E_USER_WARNING);
		}
		// --------------------------------
		// Check Pic Locked
		// --------------------------------
		if (($image_data['image_lock']) && (!$auth_data['moderator']))
		{
			trigger_error($user->lang['IMAGE_LOCKED'], E_USER_WARNING);
		}
		// --------------------------------
		// Check username for guest posting
		// --------------------------------

		if (!$user->data['is_registered'])
		{
			if ($comment_username <> '')
			{
				$result = validate_username($comment_username);
				if ( $result['error'] )
				{
					trigger_error($result['error_msg'], E_USER_WARNING);
				}
			}
		}


		// --------------------------------
		// Prepare variables
		// --------------------------------

		$comment_time		= time();
		$comment_user_id	= $user->data['user_id'];
		$comment_user_ip	= $user->ip;
		// --------------------------------
		// Get $comment_id
		// --------------------------------
		$sql = 'SELECT MAX(comment_id) AS max
			FROM ' . GALLERY_COMMENTS_TABLE;
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$comment_id = $row['max'] + 1;
		// --------------------------------
		// Insert into DB
		// --------------------------------
		include_once($phpbb_root_path . 'includes/message_parser.' . $phpEx);
		$message_parser 			= new parse_message();
		$message_parser->message 	= utf8_normalize_nfc($comment_text);
		if($message_parser->message)
		{
			$message_parser->parse(true, true, true, true, false, true, true, true);
		}
		$sql_ary = array(
			'comment_id'		=> $comment_id,
			'comment_image_id'	=> $image_id,
			'comment_user_id'	=> $comment_user_id,
			'comment_username'	=> $comment_username,
			'comment_user_ip'	=> $comment_user_ip,
			'comment_time'		=> $comment_time,
			'comment'			=> $message_parser->message,
			'comment_uid'		=> $message_parser->bbcode_uid,
			'comment_bitfield'	=> $message_parser->bbcode_bitfield,
			);
		
		$db->sql_query('INSERT INTO ' . GALLERY_COMMENTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary));

		// --------------------------------
		// Complete... now send a message to user
		// --------------------------------
		$template->assign_vars(array(
			'META' => '<meta http-equiv="refresh" content="3;url=' . append_sid("{$phpbb_root_path}gallery/image_page.$phpEx?image_id=$image_id&comment_set=1") . '#comments">',
		));

		$message = $user->lang['COMMENT_STORED'] . "<br /><br />" . sprintf($user->lang['CLICK_VIEW_COMMENT'], "<a href=\"" . append_sid("{$phpbb_root_path}gallery/image_page.$phpEx?image_id=$image_id&stored=1") . "#$comment_id\">", "</a>") . "<br /><br />" . sprintf($user->lang['CLICK_RETURN_GALLERY_INDEX'], "<a href=\"" . append_sid("{$phpbb_root_path}gallery/index.$phpEx") . "\">", "</a>");
		trigger_error($message, E_USER_WARNING);
	}

	if (isset($_POST['rate']))
	{
		if (!$album_config['rate'] || !$album_user_access['rate'])
		{
			trigger_error($user->lang['NOT_AUTHORISED'], E_USER_WARNING);
		}
		else if ($already_rated)
		{
			trigger_error($user->lang['ALREADY_RATED'], E_USER_WARNING);
		}
		$rate_point = request_var('rate', 0);
		if( ($rate_point <= 0) || ($rate_point > $album_config['rate_scale']) )
		{
			trigger_error('Bad submitted value', E_USER_WARNING);
		}
		$rate_user_id = $user->data['user_id'];
		$rate_user_ip = $user->ip;
		// --------------------------------
		// Insert into the DB
		// --------------------------------
		$sql_ary = array(
			'rate_image_id'	=> $image_id,
			'rate_user_id'	=> $rate_user_id,
			'rate_user_ip'	=> $rate_user_ip,
			'rate_point'	=> $rate_point,
		);
		$db->sql_query('INSERT INTO ' . GALLERY_RATES_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary));

		// --------------------------------
		// Complete... now send a message to user
		// --------------------------------
		$message = $user->lang['RATING_SUCCESSFUL'];
		if ($album_id <> PERSONAL_GALLERY)
		{
			$template->assign_vars(array(
				'META' => '<meta http-equiv="refresh" content="3;url=' . append_sid("{$phpbb_root_path}gallery/image_page.$phpEx?image_id=$image_id&rate_set=1#rating") . '">',
			));
			$message .= "<br /><br />" . sprintf($user->lang['CLICK_RETURN_ALBUM'], "<a href=\"" . append_sid("album.$phpEx?id=$album_id") . "\">", "</a>");
		}
		else
		{
			$template->assign_vars(array(
				'META' => '<meta http-equiv="refresh" content="3;url=' . append_sid("album_personal.$phpEx?user_id=$user_id") . '">',
			));
			$message .= "<br /><br />" . sprintf($user->lang['CLICK_RETURN_PERSONAL_ALBUM'], "<a href=\"" . append_sid("album_personal.$phpEx?user_id=$user_id") . "\">", "</a>");
		}

		$message .= "<br /><br />" . sprintf($user->lang['CLICK_RETURN_GALLERY_INDEX'], "<a href=\"" . append_sid("index.$phpEx") . "\">", "</a>");
		trigger_error($message, E_USER_WARNING);
	}
}

/*
+----------------------------------------------------------
| Main work here...
+----------------------------------------------------------
*/
$previous_id = $next_id = $last_id = 0;
$do_next = false;
$sort_method = request_var('sort_method', $album_config['sort_method']);
$sort_order = request_var('sort_order', $album_config['sort_order']);
$image_approval_sql = ' AND i.image_approval = 1';
if (($album_data['album_approval'] <> ALBUM_USER) && (($user->data['user_type'] == USER_FOUNDER) || (($album_user_access['moderator'] == 1) && ($album_data['album_approval'] == ALBUM_MOD))))
{
	$image_approval_sql = '';
}
$sql = 'SELECT i.image_id, AVG(r.rate_point) AS rating, COUNT(DISTINCT c.comment_id) AS comments, MAX(c.comment_id) as new_comment
	FROM ' . GALLERY_IMAGES_TABLE . ' AS i
	LEFT JOIN ' . GALLERY_RATES_TABLE . ' AS r
		ON i.image_id = r.rate_image_id
	LEFT JOIN ' . GALLERY_COMMENTS_TABLE . ' AS c
		ON i.image_id = c.comment_image_id
	WHERE i.image_album_id = ' . $album_id . $image_approval_sql . '
	GROUP BY i.image_id
	ORDER BY ' . $sort_method . ' ' . $sort_order;
$result = $db->sql_query($sql);
while ($row = $db->sql_fetchrow($result))
{
	if ($do_next)
	{
		$next_id = $row['image_id'];
	}
	$do_next = false;
	if ($row['image_id'] == $image_data['image_id'])
	{
		$previous_id = $last_id;
		$do_next = true;
	}
	$last_id = $row['image_id'];
}
$template->assign_vars(array(
	'U_VIEW_ALBUM'	=> ($album_id <> PERSONAL_GALLERY) ? append_sid("album.$phpEx?id=$album_id") : append_sid("album_personal.$phpEx?user_id=$user_id"),

	'U_IMAGE'			=> append_sid("{$phpbb_root_path}gallery/image.$phpEx?pic_id=$image_id"),
	'U_PREVIOUS'		=> ($previous_id) ? append_sid("{$phpbb_root_path}gallery/image_page.$phpEx?image_id=$previous_id") : '',
	'U_NEXT'			=> ($next_id && ($next_id != $previous_id)) ? append_sid("{$phpbb_root_path}gallery/image_page.$phpEx?image_id=$next_id") : '',
	'IMAGE_RSZ_WIDTH'	=> $album_config['preview_rsz_width'],
	'IMAGE_RSZ_HEIGHT'	=> $album_config['preview_rsz_height'],

	'IMAGE_NAME'		=> $image_data['image_name'],
	'IMAGE_DESC'		=> generate_text_for_display($image_data['image_desc'], $image_data['image_desc_uid'], $image_data['image_desc_bitfield'], 7),
	'IMAGE_BBCODE'		=> '[album]' . $image_data['image_id'] . '[/album]',
	'POSTER'			=> get_username_string('full', $image_data['image_user_id'], ($image_data['image_user_id'] <> ANONYMOUS) ? $image_data['image_username'] : $user->lang['GUEST'], $image_data['image_user_colour']),
	'IMAGE_TIME'		=> $user->format_date($image_data['image_time']),
	'IMAGE_VIEW'		=> $image_data['image_view_count'],

	'S_ALBUM_ACTION'	=> append_sid("{$phpbb_root_path}gallery/image_page.$phpEx?image_id=$image_id"))
);

if ($album_config['rate'])
{
	$template->assign_vars(array(
		'RATING'		=> $user->lang['RATING'],
		'IMAGE_RATING'	=> ($image_data['rating'] <> 0) ? round($image_data['rating'], 2) : $user->lang['NOT_RATED'],
	));
	
	if ($album_data['album_rate_level'] < 1 || $album_user_access['rate'])
	{
		$ratebox = false;
		if ($user->data['user_id'] == ANONYMOUS || $user->data['is_bot'])
		{
			if ($album_data['album_rate_level'] == 0)
			{
				$ratebox = '<a href="' . append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=login') . '">' . $user->lang['LOGIN_TO_RATE'] . '</a>';
			}
		}
		if (!$ratebox)
		{
			if (!$already_rated)
			{
				$ratebox = '<select name="rate">';
				for ($i = 0; $i < $album_config['rate_scale']; $i++)
				{
					$rate_point = $i + 1;
					$ratebox .= '<option value="' . $rate_point . '">' . $rate_point . '</option>';
				}
				$ratebox .= '</select> &nbsp; &nbsp; <input type="submit" name="submit" value="' . $user->lang['SUBMIT'] . '" class="button1" />';
			}
			else
			{
				$ratebox = $user->lang['ALREADY_RATED'];
			}
		}
		$template->assign_vars(array(
			'YOUR_RATING'	=> true,
			'S_RATEBOX'	=> $ratebox,
		));
	}
}

if ($album_config['comment'])
{
	$template->assign_vars(array(
		'COMMENTS'		=> true,
		'IMAGE_COMMENTS'	=> $image_data['comments'],
	));
	//'PIC_COMMENTS' => $image_data['comments']
	
	if ($album_data['album_comment_level'] < 1 || $album_user_access['comment'])
	{
		$template->assign_vars(array(
			'POST_COMMENT'	=> true,
			'YOUR_COMMENT'	=> true,
		));
		$commentbox = false;
		if ($user->data['user_id'] == ANONYMOUS || $user->data['is_bot'])
		{
			if ($album_data['album_comment_level'] == 0)
			{
				$commentbox = '<a href="' . append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=login') . '">' . $user->lang['LOGIN_TO_COMMENT'] . '</a>';
			}
			else
			{
				$template->assign_vars(array(
						'S_CAN_COMMENT' => true
					)
				);
			}
		}
		if (!$commentbox)
		{
			$commentbox  = '';
			$commentbox .= '<textarea name="comment" class="inputbox" cols="60" rows="4"></textarea><br /><br /><input type="submit" name="submit" value="' . $user->lang['SUBMIT'] . '" class="button1" />';
		}
		$template->assign_vars(array(
			'S_COMMENTBOX' 			=> $commentbox,
			'S_MAX_LENGTH' 			=> $album_config['desc_length'],
		));
	}
	
	$total_comments = $image_data['comments'];
	$comments_per_page = 10;
	
	$start = request_var('start', 0);
	
	$sort_order = request_var('sort_order', 'ASC');
	
	if ($total_comments > 0)
	{
		$limit_sql = ($start == 0) ? $comments_per_page : $start .','. $comments_per_page;

		$sql = 'SELECT c.*, u.user_id, u.username, u.user_colour
			FROM ' . GALLERY_COMMENTS_TABLE . ' AS c
			LEFT JOIN ' . USERS_TABLE . ' AS u
				ON c.comment_user_id = u.user_id
			WHERE c.comment_image_id = ' . $image_id . '
			ORDER BY c.comment_id ' . $sort_order . '
			LIMIT ' . $limit_sql;

		$result = $db->sql_query($sql);

		$commentrow = array();

		while( $row = $db->sql_fetchrow($result) )
		{
			$commentrow[] = $row;
		}
		
		$even = 0;
		
		for ($i = 0; $i < count($commentrow); $i++)
		{
			if (($commentrow[$i]['user_id'] == ALBUM_GUEST) || ($commentrow[$i]['username'] == ''))
			{
				$poster = ($commentrow[$i]['comment_username'] == '') ? $user->lang['GUEST'] : $commentrow[$i]['comment_username'];
			}
			else
			{
				$poster = '<a href="'. append_sid("{$phpbb_root_path}memberlist.$phpEx?mode=viewprofile&amp;u=" . $commentrow[$i]['user_id']) . '" class="username-coloured">' . $commentrow[$i]['username'] . '</a>';
			}

			if ($commentrow[$i]['comment_edit_count'] > 0)
			{
				$sql = 'SELECT c.comment_id, c.comment_edit_user_id, u.user_id, u.username, u.user_colour
					FROM ' . GALLERY_COMMENTS_TABLE . ' AS c
					LEFT JOIN ' . USERS_TABLE . ' AS u
						ON c.comment_edit_user_id = u.user_id
					WHERE c.comment_id = ' . $commentrow[$i]['comment_id']. '
					LIMIT 1';

				$result = $db->sql_query($sql);

				$lastedit_row = $db->sql_fetchrow($result);

				$edit_info = ($commentrow[$i]['comment_edit_count'] == 1) ? $user->lang['EDITED_TIME_TOTAL'] : $user->lang['EDITED_TIMES_TOTAL'];

				$edit_info = '<br /><br />&raquo;&nbsp;'. sprintf($edit_info, get_username_string('full', $lastedit_row['user_id'], $lastedit_row['username'], $lastedit_row['user_colour']), $user->format_date($commentrow[$i]['comment_edit_time']), $commentrow[$i]['comment_edit_count']) .'<br />';
			}
			else
			{
				$edit_info = '';
			}
			//$commentrow[$i]['comment'] = smilies_pass($commentrow[$i]['comment']);
			
			if ($even == 0)
			{
				$row_style = 'bg2';
				$even++;
			}
			else
			{
				$row_style = 'bg1';
				$even = 0;
			}
				
			$template->assign_block_vars('commentrow', array(
				'ID' 			=> $commentrow[$i]['comment_id'],
				'POSTER' 		=> get_username_string('full', $commentrow[$i]['user_id'], ($commentrow[$i]['user_id'] <> ANONYMOUS) ? $commentrow[$i]['username'] : $user->lang['GUEST'], $commentrow[$i]['user_colour']),
				'TIME' 			=> $user->format_date($commentrow[$i]['comment_time']),
				'IP' 			=> ($user->data['user_type'] == USER_FOUNDER) ? '<br />' . $user->lang['IP'] . ': <a href="http://www.nic.com/cgi-bin/whois.cgi?query=' . $commentrow[$i]['comment_user_ip'] . '">' . $commentrow[$i]['comment_user_ip'] .'</a><br />' : '',
				'S_ROW_STYLE' 	=> $row_style,
				'TEXT' 			=> generate_text_for_display($commentrow[$i]['comment'], $commentrow[$i]['comment_uid'], $commentrow[$i]['comment_bitfield'], 7),
				'EDIT_INFO' 	=> $edit_info,
				'EDIT' 			=> '',//( ( $auth_data['edit'] && ($commentrow[$i]['comment_user_id'] == $user->data['user_id']) ) || ($auth_data['moderator'] && ($album_data['album_edit_level'] != ALBUM_ADMIN) ) || ($user->data['user_type'] == USER_FOUNDER) ) ? '<a href="'. append_sid("edit.$phpEx?comment_id=". $commentrow[$i]['comment_id']) .'">'. $user->lang['EDIT_IMAGE'] .'</a>' : '',
				'DELETE' 		=> '',//( ( $auth_data['delete'] && ($commentrow[$i]['comment_user_id'] == $user->data['user_id']) ) || ($auth_data['moderator'] && ($album_data['album_delete_level'] != ALBUM_ADMIN) ) || ($user->data['user_type'] == USER_FOUNDER) ) ? '<a href="'. append_sid("edit.$phpEx?comment_id=". $commentrow[$i]['comment_id']) .'">'. $user->lang['DELETE_IMAGE'] .'</a>' : ''
				)
			);
		}

		$template->assign_vars(array(
			'PAGINATION' 	=> generate_pagination(append_sid("{$phpbb_root_path}gallery/image_page.$phpEx?image_id=$image_id&amp;sort_order=$sort_order"), $total_comments, $comments_per_page, $start),
			'PAGE_NUMBER' 	=> sprintf($user->lang['PAGE_OF'], ( floor( $start / $comments_per_page ) + 1 ), ceil( $total_comments / $comments_per_page ))
			)
		);
	}
	else
	{
		$template->assign_vars(array(
			'NO_COMMENTS' => true)
		);
	}
}

// Build the navigation
if ($album_id <> PERSONAL_GALLERY)
{
	generate_album_nav($album_data);
}
else
{
	$template->assign_block_vars('navlinks', array(
		'FORUM_NAME'	=> $user->lang['PERSONAL_ALBUMS'],
		'U_VIEW_FORUM'	=> append_sid("{$phpbb_root_path}gallery/album_personal_index.$phpEx"),
	));

	$template->assign_block_vars('navlinks', array(
		'FORUM_NAME'	=> sprintf($user->lang['PERSONAL_ALBUM_OF_USER'], $image_data['username']),
		'U_VIEW_FORUM'	=> append_sid("{$phpbb_root_path}gallery/album_personal.$phpEx", 'user_id=' . $user_id),
	));
}

// Output page
$page_title = $user->lang['VIEW_IMAGE'];// . ' &bull; ' . $album_data['album_name']; ### add image title later

page_header($page_title);

$template->set_filenames(array(
	'body' => 'gallery_page_body.html')
);

page_footer();

?>