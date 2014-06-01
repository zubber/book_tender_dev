<?php

/**
 * UserIdentity represents the data needed to identity a user.
 * It contains the authentication method that checks if the provided
 * data can identity the user.
 */
class UserIdentity extends CUserIdentity
{
	/**
	 * Authenticates a user.
	 * @return boolean whether authentication succeeds.
	 */
	private $_id;
	
	public function authenticate()
	{
		
		$record=User::model()->findOne(array('username'=>$this->username));
		if($record===null)
			$this->errorCode=self::ERROR_USERNAME_INVALID;
		else if($record->password!==crypt($this->password,$record->password))
			$this->errorCode=self::ERROR_PASSWORD_INVALID;
		else
		{
			$this->_id=(string)$record->_id;
			$this->setState('title', $record->username);
			$this->errorCode=self::ERROR_NONE; //sd(11);
		}
        return !$this->errorCode;
	}

	public function getId()
	{
		return $this->_id;
	}
}