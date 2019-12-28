<?php
namespace GDO\MailGPG;

use GDO\Core\GDO_Module;

final class Module_MailGPG extends GDO_Module
{
	public function getUserSettingsURL() { return href('MailGPG', 'Encryption'); }
	public function getClasses() { return ['GDO\MailGPG\GDO_PublicKey']; }
	public function onLoadLanguage() { $this->loadLanguage('lang/mailgpg'); }
}
