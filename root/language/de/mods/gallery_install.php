<?php

/**
*
* @package phpBB3 - gallery
* @version $Id: gallery_install.php 249 2008-01-24 21:03:57Z nickvergessen $
* @copyright (c) 2007 nickvergessen
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
**/
if (!defined('IN_PHPBB'))
{
	exit;
}
if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

$lang = array_merge($lang, array(
	'AFTER_INSTALL_GOTO'				=> 'Zur %sGalerie%s',

	'EXAMPLE_ALBUM1'					=> 'Deine erste Kategorie',
	'EXAMPLE_ALBUM2'					=> 'Dein erstes Album',
	'EXAMPLE_ALBUM2_DESC'				=> 'Beschreibung deines ersten Albums.',
	'EXAMPLE_DESC'						=> 'Danke dass du phpBB Gallery v%s aka. &quot;DB-Bird&quot; installiert hast.<br />'
											. 'Das ist nur ein Beispiel-Bild. Du kannst es löschen, wenn du möchtest.',
	'EXAMPLE_DESC_UID'					=> '1vrbfkfh',

	'INSTALLER_CHMOD'					=> 'Auf Beschreibbarkeit überprüfen',
	'INSTALLER_CHMOD_EXPLAIN'			=> 'Die folgenden Verzeichnisse müssen CHMOD auf 777 gesetzt haben, damit die Gallery reibungslos funktioniert.',
	'INSTALLER_CHMOD_UNWRITABLE'		=> 'Unbeschreibbar',
	'INSTALLER_CHMOD_WRITABLE'			=> 'Beschreibbar',

	'INSTALLER_CONVERT'					=> 'Konvertieren',
	'INSTALLER_CONVERT_NOTE'			=> 'Konvertiere MOD zu v%s',
	'INSTALLER_CONVERT_PREFIX'			=> 'Präfix der phpBB2-Installation',
	'INSTALLER_CONVERT_SUCCESSFUL'		=> 'Konvertierung des MODs zu v%s war erfolgreich.<br />Kopiere nun die Bilder aus den Verzeichnissen album/upload und album/upload/cache aus der phpBB2-Installation in die der phpBB3-Installation.',
	'INSTALLER_CONVERT_UNSUCCESSFUL'	=> 'Konvertierung des MODs zu v%s war <strong>nicht</strong> erfolgreich.',
	'INSTALLER_CONVERT_UNSUCCESSFUL2'	=> 'Du hast kein Präfix für die phpBB2-Installation eingefügt.',
	'INSTALLER_CONVERT_WELCOME'			=> 'Willkommen zur Konvertierung',
	'INSTALLER_CONVERT_WELCOME_NOTE'	=> 'Wenn du den MOD konvertierst, kopieren wir die Daten aus deine phpBB2-Installation in die phpBB3-Installation.',

	'INSTALLER_DELETE'					=> 'Löschen',
	'INSTALLER_DELETE_NOTE'				=> 'Löschen',
	'INSTALLER_DELETE_BBCODE'			=> 'Wähle den richtigen BBCode',
	'INSTALLER_DELETE_SUCCESSFUL'		=> 'Die MOD wurde erfolgreich deinstalliert.<br />Du kannst nun alle Dateien löschen.',
	'INSTALLER_DELETE_UNSUCCESSFUL'		=> 'Die MOD konnte <strong>nicht</strong> deinstalliert werden.',
	'INSTALLER_DELETE_WELCOME'			=> 'Du möchtest die Gallery wirklich löschen?',
	'INSTALLER_DELETE_WELCOME_NOTE'		=> 'Wenn du die MOD hier löschst, werden alle Datenbankeinträge gelöscht.',

	'INSTALLER_INTRO'					=> 'Intro',
	'INSTALLER_INTRO_WELCOME'			=> 'Willkommen zur MOD-Installation',
	'INSTALLER_INTRO_WELCOME_NOTE'		=> 'Bitte wähle aus, was du tun möchtest.',

	'INSTALLER_INSTALL'					=> 'Installieren',
	'INSTALLER_INSTALL_MENU'			=> 'Installation',
	'INSTALLER_INSTALL_SUCCESSFUL'		=> 'Installation der MOD v%s war erfolgreich.',
	'INSTALLER_INSTALL_UNSUCCESSFUL'	=> 'Installation der MOD v%s war <strong>nicht</strong> erfolgreich.',
	'INSTALLER_INSTALL_VERSION'			=> 'Installiere MOD v%s',
	'INSTALLER_INSTALL_WELCOME'			=> 'Willkommen zur Installation',
	'INSTALLER_INSTALL_WELCOME_NOTE'	=> 'Wenn du den MOD installierst, werden möglicherweise vorhandene Datenbanktabellen mit gleichem Namen gelöscht.',

	'INSTALLER_UPDATE'					=> 'Update',
	'INSTALLER_UPDATE_MENU'				=> 'Updatemenü',
	'INSTALLER_UPDATE_NOTE'				=> 'Update MOD von v%s nach v%s',
	'INSTALLER_UPDATE_SUCCESSFUL'		=> 'Update der MOD von v%s nach v%s war erfolgreich.',
	'INSTALLER_UPDATE_UNSUCCESSFUL'		=> 'Update der MOD von v%s nach v%s war <strong>nicht</strong> erfolgreich.',
	'INSTALLER_UPDATE_VERSION'			=> 'Update MOD von v',
	'INSTALLER_UPDATE_WELCOME'			=> 'Willkommen zum Update',

	'MISSING_PARENT_MODULE'				=> 'Modul #%s fehlt als übergeordnetes Modul für "%s".',

	'WARNING'							=> 'Warnung',
));

?>