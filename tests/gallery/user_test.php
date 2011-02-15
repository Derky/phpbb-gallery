<?php
/**
*
* @package testing
* @copyright (c) 2008 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

class gallery_phpbb_gallery_user_test extends gallery_database_test_case
{
	public function getDataSet()
	{
		return $this->createXMLDataSet(dirname(__FILE__).'/fixtures/gallery_users.xml');
	}

	public static function user_entry_exists_data()
	{
		return array(
			array(0, false, null),
			array(0, true, false),

			array(2, false, null),
			array(2, true, true),
		);
	}

	/**
	* @dataProvider user_entry_exists_data
	*/
	public function test_user_entry_exists($user_id, $load, $expected)
	{
		$db = $this->new_dbal();

		$user = new phpbb_gallery_user($db, $user_id, $load);

		$this->assertEquals($expected, $user->entry_exists);
	}

	public static function user_force_load_data()
	{
		return array(
			array(0, false, false),
			array(0, true, false),

			array(2, false, true),
			array(2, true, true),
		);
	}

	/**
	* @dataProvider user_force_load_data
	*/
	public function test_user_force_load($user_id, $load, $expected)
	{
		$db = $this->new_dbal();

		$user = new phpbb_gallery_user($db, $user_id, $load);
		$user->force_load();

		$this->assertEquals($expected, $user->entry_exists);
	}

	public static function user_get_data()
	{
		return array(
			array(2, 'user_images', 1),
			array(2, 'watch_own', true),
			array(2, 'watch_com', false),
			array(2, 'does_not_exist', false),

			array(5, 'user_images', 0),
			array(5, 'watch_own', true),
			array(5, 'watch_com', false),
			array(5, 'does_not_exist', false),
		);
	}

	/**
	* @dataProvider user_get_data
	*/
	public function test_user_get($user_id, $key, $expected)
	{
		$db = $this->new_dbal();

		$user = new phpbb_gallery_user($db, $user_id);
		$this->assertEquals($expected, $user->get_data($key));
	}

	public static function user_load_data_data()
	{
		return array(
			array(2, true, array(
				'user_id'			=> 2,
				'user_images'		=> 1,
				'personal_album_id'	=> 0,
				'user_lastmark'		=> 0,
				'user_last_update'	=> 0,
				'user_permissions'	=> '',
				'user_viewexif'		=> false,
				'watch_own'			=> true,
				'watch_favo'		=> false,
				'watch_com'			=> false,
			)),
			array(5, false, array()),
		);
	}

	/**
	* @dataProvider user_load_data_data
	*/
	public function test_user_load_data($user_id, $entry_exists, $expected_values)
	{
		$db = $this->new_dbal();

		$user = new phpbb_gallery_user($db, $user_id);
		foreach ($expected_values as $key => $value)
		{
			$this->assertEquals($value, $user->get_data($key));
		}

		$this->assertEquals($entry_exists, $user->entry_exists);
	}

	public static function user_update_data_data()
	{
		return array(
			array(2, true, array('user_images'	=> 2), array(
				'user_id'			=> 2,
				'user_images'		=> 2,
				'personal_album_id'	=> 0,
				'user_lastmark'		=> 0,
				'user_last_update'	=> 0,
				'user_permissions'	=> '',
				'user_viewexif'		=> false,
				'watch_own'			=> true,
				'watch_favo'		=> false,
				'watch_com'			=> false,
			)),
			array(2, true, array('does_not_exist'	=> 2), array(
				'user_id'			=> 2,
				'user_images'		=> 1,
				'personal_album_id'	=> 0,
				'user_lastmark'		=> 0,
				'user_last_update'	=> 0,
				'user_permissions'	=> '',
				'user_viewexif'		=> false,
				'watch_own'			=> true,
				'watch_favo'		=> false,
				'watch_com'			=> false,
			)),
			array(5, false, array('user_images'	=> 2), array(
				'user_id'			=> 5,
				'user_images'		=> 2,
				'personal_album_id'	=> 0,
				'user_lastmark'		=> 0,
				'user_last_update'	=> 0,
				'user_permissions'	=> '',
				'user_viewexif'		=> true,
				'watch_own'			=> true,
				'watch_favo'		=> false,
				'watch_com'			=> false,
			)),
			array(5, false, array('does_not_exist'	=> 2), array(
				'user_id'			=> 5,
				'user_images'		=> 0,
				'personal_album_id'	=> 0,
				'user_lastmark'		=> 0,
				'user_last_update'	=> 0,
				'user_permissions'	=> '',
				'user_viewexif'		=> true,
				'watch_own'			=> true,
				'watch_favo'		=> false,
				'watch_com'			=> false,
			)),
		);
	}

	/**
	* @dataProvider user_update_data_data
	*/
	public function test_user_update_data($user_id, $entry_exists, $update, $expected_values)
	{
		$db = $this->new_dbal();

		$user = new phpbb_gallery_user($db, $user_id);
		$this->assertEquals($entry_exists, $user->entry_exists);

		$this->assertEquals(true, $user->update_data($update));
		foreach ($expected_values as $key => $value)
		{
			if ($key == 'user_last_update')
			{
				$this->assertGreaterThanOrEqual((time() - 1), $user->get_data($key));
				continue;
			}
			$this->assertEquals($value, $user->get_data($key));
		}
		$this->assertEquals(true, $user->entry_exists);

	}

	public static function user_increase_data_data()
	{
		return array(
			array(2, true, array('user_images'	=> 2), array(
				'user_id'			=> 2,
				'user_images'		=> 3,
				'personal_album_id'	=> 0,
				'user_lastmark'		=> 0,
				'user_last_update'	=> 0,
				'user_permissions'	=> '',
				'user_viewexif'		=> false,
				'watch_own'			=> true,
				'watch_favo'		=> false,
				'watch_com'			=> false,
			)),
			array(2, true, array('user_images'	=> 2, 'does_not_exist'	=> 2), array(
				'user_id'			=> 2,
				'user_images'		=> 3,
				'personal_album_id'	=> 0,
				'user_lastmark'		=> 0,
				'user_last_update'	=> 0,
				'user_permissions'	=> '',
				'user_viewexif'		=> false,
				'watch_own'			=> true,
				'watch_favo'		=> false,
				'watch_com'			=> false,
			)),
			array(2, true, array('does_not_exist'	=> 2), array(
				'user_id'			=> 2,
				'user_images'		=> 1,
				'personal_album_id'	=> 0,
				'user_lastmark'		=> 0,
				'user_last_update'	=> 0,
				'user_permissions'	=> '',
				'user_viewexif'		=> false,
				'watch_own'			=> true,
				'watch_favo'		=> false,
				'watch_com'			=> false,
			)),
			array(5, false, array('user_images'	=> 2), array(
				'user_id'			=> 5,
				'user_images'		=> 2,
				'personal_album_id'	=> 0,
				'user_lastmark'		=> 0,
				'user_last_update'	=> 0,
				'user_permissions'	=> '',
				'user_viewexif'		=> true,
				'watch_own'			=> true,
				'watch_favo'		=> false,
				'watch_com'			=> false,
			)),
			array(5, false, array('user_images'	=> 2, 'does_not_exist'	=> 2), array(
				'user_id'			=> 5,
				'user_images'		=> 2,
				'personal_album_id'	=> 0,
				'user_lastmark'		=> 0,
				'user_last_update'	=> 0,
				'user_permissions'	=> '',
				'user_viewexif'		=> true,
				'watch_own'			=> true,
				'watch_favo'		=> false,
				'watch_com'			=> false,
			)),
			array(5, false, array('does_not_exist'	=> 2), array(
				'user_id'			=> 5,
				'user_images'		=> 0,
				'personal_album_id'	=> 0,
				'user_lastmark'		=> 0,
				'user_last_update'	=> 0,
				'user_permissions'	=> '',
				'user_viewexif'		=> true,
				'watch_own'			=> true,
				'watch_favo'		=> false,
				'watch_com'			=> false,
			)),
		);
	}

	/**
	* @dataProvider user_increase_data_data
	*/
	public function test_user_increase_data($user_id, $entry_exists, $update, $expected_values)
	{
		$db = $this->new_dbal();

		$user = new phpbb_gallery_user($db, $user_id);
		$this->assertEquals($entry_exists, $user->entry_exists);

		$this->assertEquals(true, $user->increase_data($update));
		#$user->increase_data($update);
		foreach ($expected_values as $key => $value)
		{
			if ($key == 'user_last_update')
			{
				$this->assertGreaterThanOrEqual((time() - 1), $user->get_data($key));
				continue;
			}
			$this->assertEquals($value, $user->get_data($key));
		}
		$this->assertEquals(true, $user->entry_exists);

	}

	public static function user_delete_data()
	{
		return array(
			array(2, true),
			array(5, false),
		);
	}

	/**
	* @dataProvider user_delete_data
	*/
	public function test_user_delete($user_id, $exists_before_delete)
	{
		$db = $this->new_dbal();

		$user = new phpbb_gallery_user($db, $user_id);
		$this->assertEquals($exists_before_delete, $user->entry_exists);
		$user->delete();
		unset($user);

		$user = new phpbb_gallery_user($db, $user_id);
		$this->assertEquals(false, $user->entry_exists);
	}
}
