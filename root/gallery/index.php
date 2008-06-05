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
$gallery_root_path = GALLERY_ROOT_PATH;
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
include_once($phpbb_root_path . 'includes/message_parser.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('mods/gallery');


//
// Get general album information
//
include_once("{$phpbb_root_path}{$gallery_root_path}includes/common.$phpEx");
include_once("{$phpbb_root_path}{$gallery_root_path}includes/permissions.$phpEx");
$album_access_array = get_album_access_array();


/**
* Build Album-Index
*/
$mode = request_var('mode', 'index', true);
include($phpbb_root_path . $gallery_root_path . 'includes/functions_display.' . $phpEx);
if ($mode == 'personal')
{
	$template->assign_block_vars('navlinks', array(
		'FORUM_NAME'	=> $user->lang['PERSONAL_ALBUMS'],
		'U_VIEW_FORUM'	=> append_sid("{$phpbb_root_path}{$gallery_root_path}index.$phpEx", 'mode=personal'))
	);

	$template->assign_vars(array(
		'S_PERSONAL_GALLERY' 		=> true,
	));
}
display_albums(0, $mode);



/**
* Recent Public Pics
*/
include($phpbb_root_path . $gallery_root_path . 'includes/functions_recent.' . $phpEx);
$display = array(
	'name'		=> true,
	'poster'	=> true,
	'time'		=> true,
	'views'		=> true,
	'ratings'	=> false,
	'comments'	=> false,
	'album'		=> true,
);
//(rows, columns)
recent_gallery_images(1, 4, $display);

/**
* Start output the page
*/

$template->assign_vars(array(
	'U_YOUR_PERSONAL_GALLERY' 		=> ($album_access_array[-2]['i_upload'] == 1) ? ($user->data['album_id'] > 0) ? append_sid("{$phpbb_root_path}{$gallery_root_path}album.$phpEx", 'album_id=' . $user->data['album_id']) : append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=gallery&amp;mode=manage_albums') : '',
	'U_USERS_PERSONAL_GALLERIES' 	=> ($album_access_array[-3]['i_view'] == 1) ? append_sid("{$phpbb_root_path}{$gallery_root_path}index.$phpEx", 'mode=personal') : '',

	'S_LOGIN_ACTION'				=> append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=login'),
	'S_COLS' 						=> $album_config['cols_per_page'],
	'S_COL_WIDTH' 					=> (100/$album_config['cols_per_page']) . '%',
	'TARGET_BLANK' 					=> ($album_config['fullpic_popup']) ? 'target="_blank"' : '',
));

// Output page
$page_title = $user->lang['GALLERY'];

page_header($page_title);

$template->set_filenames(array(
	'body' => 'gallery_index_body.html')
);

page_footer();
?>