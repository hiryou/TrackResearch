<?php

require_once(LIB_PATH.'/Curl/CurlBot.php');
require_once(LIB_PATH.'/Function/String.php');

final class OdinAuth {
	
	// user privilege
	private $_email 		= '';
	private $_password 		= '';
	
	/**
	 * to avoid signing in every time requesting a single page, this authSession 
     * is utilized to save the current
	 * authentication with the site, it involves reference to a cookie jar file
	 *
	 * e.g. $_SESSION['ODIN_AUTH']
	 */
    private $_sessionName  = 'ODIN_AUTH';
    private $_authSession  = null;
	
	const SIGNIN_PAGE_URL   = 'https://sso.pdx.edu/cas/login?service=http%3A%2F%2Ftrack.research.pdx.edu%2Fcas%3Fdestination%3Dhome';
	const SIGNIN_PERIOD 	= 600;	// resign in after every certain preriod to maintain authentication on site
	
	public function __construct($email, $password, $authSessionName=null) {
		$this->_email 		= (String)$email;
		$this->_password	= (String)$password;
        if ($authSessionName!=null)
            $this->_sessionName =  $authSessionName;
        $this->_createSession();
	}
    
    private function _createSession() {
        if (!isset($_SESSION['ODIN_AUTH']))
            $_SESSION[$this->_sessionName] = new stdClass();
        $this->_authSession = $_SESSION[$this->_sessionName];
    }
	
	/**
	 * get the curlBot which already connected authdenticaltion with amazon.com
	 * 
	 * @subpackage CurlBot
	 *
	 * @return CurlBot
	 */
	public function getAuth() {
		// sign in
		$this->signin();
		// prepare a new CurlBot
		$curlBot = new Curl_CurlBot();
		
		// set auth for this curlBot
		$curlBot->setAuth($this->_authSession->cookieFile,
						$this->_authSession->PHPSESSIONID,
						$this->_authSession->cookieStr);
						
		// return this curlBot
		return $curlBot;
	}
	
	/**
	 * signin to TRACK
	 * despite this function is called several times, the actual signin action should run only once
	 * 
	 * @uses Zend_Session_Namespace
	 * @subpackage CurlBot
	 *
	 */
	private function signin() {
		// if not signed in in yet,
		// or if the signin maintenance period was expired since the last time signin,
		// then signin in now 
		if ( !isset($this->_authSession->cookieFile) || (time()-$this->_authSession->time > self::SIGNIN_PERIOD) ) {
			// curlBot to use
			$curlBot = new Curl_CurlBot();
			
			// go to signin page
			$curlBot->navigateTo(self::SIGNIN_PAGE_URL );
			$body = $curlBot->getPageBody();
			//echo $body; die;
			
			// extract the signin form as HTML node from signin page body
			$temp = Function_String::getHtmlElements(
				$body, 
				array(
					array('form', 'id', 'fm1', 1)
				)
			);
			$signinFormNode = $temp[0];
			
			// get url where the form submit information to
			$submitUrl = 'https://sso.pdx.edu' . $signinFormNode->getAttribute('action');
			
			// build data to submit for signin
			$data = array();
			$formInputs = array();
			$formInputs = Function_String::getHtmlDescendantElementsByAttributes($signinFormNode, 'input', array(), $temp);
			foreach ($formInputs as $input) {
				$data[ $input->getAttribute('name') ] = $input->getAttribute('value');
			}
			
			// student identity
			$data['username'] = $this->_email;
			$data['password'] = $this->_password;
			//print_r($data); die;
			
			// submit form to signin
			//echo $submitUrl; die;
			$curlBot->submitForm($submitUrl, $data, array(), 'post', true);
			//echo $curlBot->getPageHeader(); die;
            
			// save this curlBot info to auth session
			$curlBot->getAuth($cookieFile, $PHPSESSIONID, $cookieStr);
			$this->_authSession->cookieFile 	= $cookieFile;
			$this->_authSession->PHPSESSIONID 	= $PHPSESSIONID;
			$this->_authSession->cookieStr 		= $cookieStr;
			$this->_authSession->time 			= time();
		}
	}
	
}