<?php
namespace GDO\MailGPG\Method;

use GDO\Account\Module_Account;
use GDO\Account\Method\Settings;
use GDO\File\GDT_File;
use GDO\Form\GDT_AntiCSRF;
use GDO\Form\GDT_Form;
use GDO\Form\GDT_Submit;
use GDO\Form\MethodForm;
use GDO\Mail\Mail;
use GDO\MailGPG\GDO_PublicKey;
use GDO\UI\GDT_Panel;
use GDO\UI\GDT_Link;
use GDO\User\GDO_User;
use GDO\File\FileUtil;

/**
 * Setup GPG Mail Encryption.
 * Sends a test mail before key is saved.
 * 
 * @author gizmore
 *
 */
final class Encryption extends MethodForm
{
	/**
	 * @var GDO_PublicKey
	 */
	private $key;
	
	public function isUserRequired() { return true; }
	
	public function execute()
	{
		$this->key = GDO_PublicKey::getKeyForUser(GDO_User::current());

		Module_Account::instance()->renderAccountTabs();
		Settings::make()->navModules();
		
		if (isset($_POST['btn_delete']))
		{
			return $this->onDelete()->add(parent::execute());
		}
		return parent::execute();
	}

	public function createForm(GDT_Form $form)
	{
		$form->addField(GDT_Panel::make('info')->text('infob_gpg_upload'));
		$form->addField(GDO_PublicKey::table()->gdoColumn('gpg_pubkey'));
		$form->addField(GDT_File::make('gpg_file')->label('gpg_file')->action($this->href()));
		$form->addField(GDT_AntiCSRF::make());
		if ($this->key === null)
		{
			$form->actions()->addField(GDT_Submit::make());
		}
		else
		{
			$form->actions()->addField(GDT_Submit::make('btn_delete'));
		}
		$form->withGDOValuesFrom($this->key);
	}

	##############
	### Delete ###
	##############
	public function onDelete()
	{
		if ($this->getForm()->validate())
		{
			$this->key->delete();
			return $this->message('msg_gpg_key_removed');
		}
		return $this->error('err_form_invalid');
	}

	###########
	### Add ###
	###########
	public function formValidated(GDT_Form $form)
	{
		$user = GDO_User::current();

		FileUtil::createDir(GDO_PATH . 'temp/gpg');
		$outfile = GDO_PATH . 'temp/gpg/' . $user->getID();
		
		
		# Get file or paste
		$file_content = '';
		
		/** @var $file \GDO\File\GDO_File **/
		$file = $form->getFormValue('gpg_file');
		
		if ($file)
		{
			$file_content = file_get_contents($file->getPath());
		}
		else
		{
			$file_content = $form->getFormVar('gpg_pubkey');
		}
		
		$file_content = trim($file_content);
		
		
		if (strpos($file_content, '-----BEGIN ') !== 0)
		{
			$response = $this->error('err_gpg_not_start_with_begin');
		}
		elseif (!file_put_contents($outfile, $file_content, GWF_CHMOD))
		{
			$response = $this->error('err_write_file');
		}
		elseif (!($fingerprint = GDO_PublicKey::grabFingerprint($file_content)))
		{
			$response = $this->error('err_gpg_fail_fingerprinting');
		}
		else
		{
			$response = $this->sendGPGMail($user, $fingerprint);
		}
		
		return $response->add($this->renderPage());
	}
	
	private function sendGPGMail(GDO_User $user, $fingerprint)
	{
		if (!($email = $user->getMail()))
		{
			return $this->error('err_user_has_no_email');
		}
		$mail = new Mail();
		$mail->setSender(GWF_BOT_EMAIL);
		$mail->setSenderName(GWF_BOT_NAME);
		$mail->setReceiver($email);
		$mail->setGPGKey($fingerprint);
		$mail->setSubject(t('mail_subj_gpg', [sitename()]));
		$mail->setBody($this->getGPGMailBody($user, $fingerprint));
		$mail->sendToUser($user);
		return $this->message('msg_gpg_mail_sent');
	}
	
	private function getGPGMailBody(GDO_User $user, $fingerprint)
	{
		$link = GDT_Link::anchor(url('Account', 'SetGPGKey', "&userid={$user->getID()}&token={$fingerprint}"));
		$args = [$user->displayName(), sitename(), $link];
		return tusr($user, 'mail_body_gpg', $args);
	}
	
}
