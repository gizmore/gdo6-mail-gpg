<?php
namespace GDO\MailGPG\Method;

use GDO\Account\Module_Account;
use GDO\Core\Method;
use GDO\MailGPG\GDO_PublicKey;
use GDO\User\GDO_User;
use GDO\Util\Common;
/**
 * GPG Mail links here to finally save the GPG key.
 * @author gizmore
 * @since 3.0
 * @version 5.0
 * @see Mail
 */
final class SetGPGKey extends Method
{
	public function execute()
	{
		$user = GDO_User::table()->find(Common::getRequestString('userid'));
		$tmpfile = GDO_PATH . 'temp/gpg/' . $user->getID();
		$file_content = file_get_contents($tmpfile);
		unlink($tmpfile);

		if (!($fingerprint = GDO_PublicKey::grabFingerprint($file_content)))
		{
			return $this->error('err_gpg_fail_fingerprinting');
		}
		
		if (Common::getRequestString('token') !== $fingerprint)
		{
			return $this->error('err_gpg_token');
		}
		
		GDO_PublicKey::updateKey($user->getID(), $file_content);
		
		return $this->message('msg_gpg_key_added');
	}
}
