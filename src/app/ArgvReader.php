<?php 

final class ArgvReader {
    
    private static $_usrArg         = '-u';
    private static $_pwdArg         = '-p';
    private static $_dateFromArg    = '-f';
    private static $_dateToArg      = '-t';
    
    private $_username = '';
    private $_password = '';
    private $_dateFrom  = '';
    private $_dateTo    = '';
    
    private static $_ARG_KEY_VAL_MAP = Array();
    
    public function __construct() {
        // Remember to initialize all properties
        $this->_username = '';
        $this->_password = '';
        $this->_dateFrom    = '';
        $this->_dateTo      = '';
        
        /**
         * Hashtable of argument prefix_key => value. 
         * This map makes it easier if later we want to accept more arguments from argv
         * e.g. if later we have one more argument -c to input comment, simply add the 
         * following memebers to this class:
         *      private static $_commentArg = '-c';
         *      private $_commentText = '';
         * and then add this item:
         *      self::$_commentArg   => &$this->_commentText
         * to this map. 
         * That's it. The method App_Main::readArgs will do the rest for us!
         */
        self::$_ARG_KEY_VAL_MAP = Array(
            self::$_usrArg      => &$this->_username,
            self::$_pwdArg      => &$this->_password,
            self::$_dateFromArg => &$this->_dateFrom,
            self::$_dateToArg   => &$this->_dateTo
        );
    }

	public function readArgs($argv) {
	    // check to ensure the passing $argv is an array
	    if (gettype($argv) != "array") {
	        $this->_appError('$argv passing to '.__METHOD__.' is required to be an array. '.
	                           ucfirst(gettype($argv))." was given.");
	    }
        
        // loop through $argv elements to associate appropriate pairs of arg $key=>$val
        $argKeys = array_keys(self::$_ARG_KEY_VAL_MAP);
        while ($idx<count($argv)) {
            $key = ''; 
            $val = '';
            while ($idx<count($argv) && !in_array($argv[$idx], $argKeys)) {
                ++$idx;
            }
            if ($idx<count($argv)) {
                $key = $argv[$idx];
                if ($idx<count($argv)-1 && !in_array($argv[$idx+1], $argKeys)) {
                    $val = $argv[$idx+1];
                    $idx += 2;
                } else {
                    $idx += 1;
                }
                self::$_ARG_KEY_VAL_MAP[$key] = $val;
            }
        }
	}

    public function passwordPrompt($promptMsg) {
        print $promptMsg;
        system('stty -echo');
        $this->_password = trim(fgets(STDIN));
        system('stty echo');
        print "\n";
    }

    public function getOdinUsr() {
        return $this->_username;
    }
    public function getOdinPwd() {
        return $this->_password;
    }
    public function getDateFrom() {
        return $this->_dateFrom;
    }
    public function getDateTo() {
        return $this->_dateTo;
    }

    private function _appError($msg) {
        print "Application error: " . $msg;
        print "\n\n";
        exit(1);
    }

}