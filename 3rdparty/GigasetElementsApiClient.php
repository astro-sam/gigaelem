<?php

/*
 * Gigaset "Smart Home" or "Elements" client-side implementation.
 *
 * @author Jean-Marie Bonnefont <jbonnefont@elphe.com>. Based on work done by dynasticopheus : https://github.com/dynasticorpheus/gigasetelements-cli
 * @date: 2020-05-10
 *
 */

session_name('GigasetElementsApiClient');
session_start(); // needed to store token and avoid permanent re-auth

define('GE_CURL_ERROR_TYPE', 0);
define('GE_API_ERROR_TYPE',1); //error return from api
define('GE_INTERNAL_ERROR_TYPE', 2); //error because internal state is not consistent
define('GE_JSON_ERROR_TYPE',3);
define('GE_NOT_LOGGED_ERROR_TYPE', 4); //unable to get access token

define('GE_CURL_MAX_REDIR', 5);
define('GE_NO_PARAMS', array() );

define('GE_BACKEND_BASE_URI', "https://api.gigaset-elements.de");
define('GE_BACKEND_STATUS_URI', "https://status.gigaset-elements.de/api/v1/status");
define('GE_BACKEND_HEALTH_URI', "https://api.gigaset-elements.de/api/v2/me/health");
#
define('GE_BACKEND_IDENT_URI', "https://im.gigaset-elements.de/identity/api/v1/user/login");
define('GE_BACKEND_AUTH_URI',"https://api.gigaset-elements.de/api/v1/auth/openid/begin");
#
// define('COOKIE_LOCATION',"/tmp/gigaelem.txt");
#
define('GE_ALARM_MODES',array('home','away','night','custom'));
#
define('GE_SENSOR_NAMES', array(
			'ws02'=> 'window_sensor',
			'ps01' => 'presence_sensor',
			'ps02' => 'presence_sensor',
			'ds01' => 'door_sensor',
			'ds02' => 'door_sensor',
			'is01' => 'indoor_siren',
			'sp01' => 'smart_plug',
			'sp02' => 'smart_plug',
			'bn01' => 'button',
			'yc01' => 'camera',
			'sd01' => 'smoke',
			'um01' => 'umos',
			'hb01' => 'hue_bridge',
			'hb01.hl01' => 'hue_light',
			'bs01' => 'base_station',
			'wd01' => 'water_sensor',
			'cl01' => 'climate_sensor'
		));
		
define('GE_EVENT_TYPES', array(
			'all' => 'All',
			'systemhealth' => 'System status',
			'intrusion' => 'Alarme',
			'button' => 'Bouton',
			'camera' => 'Camera',
			'-' => 'Camera enregistrements',
			'door' => 'Porte',
			'homecoming' => 'Entree',
			'hue' => 'Philips Hue',
			'motion' => 'Mouvement',
			'phone' => 'Téléphone',
			'plug' => 'Prise',
			'siren' => 'Sirène',
			'smoke' => 'Fumée',
			'water' => 'Eau',
			'window' => 'Fenetre',
			'umos' => 'Unknown'
		));
		
		
		
/**
* some light Exception implementation
**/
class GEClientException extends Exception
{
    public function __construct($code, $message)
    {
        parent::__construct($message, $code);
    }
}

class GEApiErrorType extends GEClientException
{
    public $error_type;
	function __construct($code, $message)
    {
        parent::__construct($code, $message);
		$this->error_type = GE_API_ERROR_TYPE;
    }
}

class GECurlErrorType extends GEClientException
{
    public $error_type;
	function __construct($code, $message)
    {
        parent::__construct($code, $message);
		$this->error_type = GE_CURL_ERROR_TYPE;
    }
}

class GEJsonErrorType extends GEClientException
{
    public $error_type;
    function __construct($code, $message)
    {
        parent::__construct($code, $message);
		$this->error_type = GE_JSON_ERROR_TYPE;
    }
}

class GEInternalErrorType extends GEClientException
{
    public $error_type;
    function __construct($message)
    {
        parent::__construct($code, $message);
		$this->error_type = GE_INTERNAL_ERROR_TYPE;
    }
}

class GENotLoggedErrorType extends GEClientException
{
    public $error_type;
    function __construct($code, $message)
    {
        parent::__construct($code, $message);
		$this->error_type = GE_NOT_LOGGED_ERROR_TYPE;
    }
}
/*
 * Main class
 */
class GigasetElementsApiClient
{
    protected $file_upload_support;
    protected $reefssid;
    protected $usertoken;
	protected $basestation_id;
    // cURL options
    public static $CURL_OPTS = array(
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HEADER         => TRUE,
		CURLOPT_FOLLOWLOCATION => FALSE, // manual redirect
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => TRUE,
        CURLOPT_HTTPHEADER     => array("Accept: application/json","Accept: binary/octet-stream"),
    );
    // logging gizmo
	public $ge_log = ["init."];
	// 
	private function geLog($message="") {
		if ($this->withLog) {
			$this->ge_log[] = $message;
		}
	}
	// 
	public function setFileUpload($set=false)
	{
		$this->file_upload_support=$set;
	}
	/**
    * Initialize a Gigaset Elements Client.
    *
    * @param $config
    *   An associative array as below:
    *   - reefssid: (optional) The login reefssid previously stored
    *   - usertoken: the authentication token previously stored.
    *   - username: (optional) The username.
    *   - password: (optional) The password.
    */
    public function __construct($config = array())
    {
        if(isset($config["withlog"]))
        {
            $this->withLog = $config["withlog"];
        } else {
			$this->withLog = false; // default
		}
        if(isset($config["ip"]))
        {
            $this->ip = $config["ip"];
        } else {
			$this->ip = NULL; // default
		}
        if(isset($config["file_upload_support"]))
        {
            $this->file_upload_support = $config["file_upload_support"];
        } else {
			$this->file_upload_support = false; // default
		}
        // If tokens are provided let's store them
        if(isset($config["usertoken"]))
        {
            $this->usertoken = $config["usertoken"];
        }
        if(isset($config["reefssid"]))
        {
            $this->reefssid = $config["reefssid"];
        }
        // Storing URI for later use
        $this->uri = array("base_uri" => GE_BACKEND_BASE_URI, "status_uri" => GE_BACKEND_STATUS_URI, "health_uri" => GE_BACKEND_HEALTH_URI, "identification_uri" => GE_BACKEND_IDENT_URI, "authorize_uri" => GE_BACKEND_AUTH_URI);
		// storing credentials
		if(!isset($config["username"])) throw new GEClientException("500", "no username provided");
		if(!isset($config["password"])) throw new GEClientException("500", "no password provided");
        $this->username = $config["username"];
        $this->password = $config["password"];
    }
    /**
    * Performs HTTP REST request.
    *
    * @param $path
    *   The target path, relative to base_path/service_uri or an absolute URI.
    * @param $method
    *   (optional) The HTTP method (default 'GET').
    * @param $params
    *   (optional The GET/POST parameters.
    *
    * @return
    *   The json_decoded result or GElientException if pb happened
    */
    public function query($path, $method = 'GET', $params = array())
    {
		$this->geLog("#query# enter function");
        $ch = curl_init();
        $opts = self::$CURL_OPTS;
        if ($params)
        {
            switch ($method) {
                case 'GET':
                    $path .= '?' . http_build_query($params, NULL, '&');
                break;
                // POST is default
                default:
                    if ($this->file_upload_support) {
                        $opts[CURLOPT_POSTFIELDS] = $params;
                    } else  {
                        $opts[CURLOPT_POSTFIELDS] = http_build_query($params, NULL, '&');
                    }
                break;
            }
        }
		$this->geLog( "#query# NEW REQUEST ***");
		$this->geLog( "#query# PATH=".$path);
		// 
		// **PREPARE QUERY
        $opts[CURLOPT_URL] = $path;
		$auth_redirect = False;
        // Disable the 'Expect: 100-continue' behaviour. This causes CURL to wait for 2 seconds if the server does not support this header.
        if (isset($opts[CURLOPT_HTTPHEADER])) {
            $existing_headers = $opts[CURLOPT_HTTPHEADER];
            $existing_headers[] = 'Expect:';
			// handle cookies - SPECIFIC HERE
			if (isset($this->usertoken)) {
				$this->geLog("#query# adding usertoken cookie : [".$this->usertoken."]");
				$existing_headers[] = 'Cookie: usertoken='.$this->usertoken;
			} elseif (isset($this->reefssid)) {  // don't add reefssid if already authentified
				$this->geLog("#query# no usertoken, but reefssid present (auth): [".$this->reefssid."]");
				$existing_headers[] = 'Cookie: reefssid='.$this->reefssid;
				$auth_redirect = True;
			} else {
				$this->geLog("#query# no reefssid (user not yet identified)");
			}
	
            $opts[CURLOPT_HTTPHEADER] = $existing_headers;
        }
        else {
            $opts[CURLOPT_HTTPHEADER] = array('Expect:');
        }
        curl_setopt_array($ch, $opts);
		// 
		// ** EXECUTE QUERY
		$this->geLog( "#query# execute query...");
        $result = curl_exec($ch);
		$this->geLog( "#query# RESULT=[".$result."]");
        $errno = curl_errno($ch);
		// manage CURLE_SSL_CACERT || CURLE_SSL_CACERT_BADFILE cases
        if ($errno == 60 || $errno == 77) {
            $this->geLog( "#query# WARNING ! SSL_VERIFICATION has been disabled since ssl error retrieved. Please check your certificate http://curl.haxx.se/docs/sslcerts.html");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            $result = curl_exec($ch);
        }
		//
        if ($result === FALSE) {
            $e = new GECurlErrorType(curl_errno($ch), curl_error($ch));
            curl_close($ch);
            throw $e;
        }
		// 
        $info = curl_getinfo($ch);
        curl_close($ch);
		//
        $response_array = explode("\r\n\r\n", $result);
		$headers = $response_array[0];
		$body=$response_array[1];
		//
		// ** AUTHENTICATION CASE
		if ($auth_redirect) {
    		for ($it=1; $it<GE_CURL_MAX_REDIR; $it++) {
				if ($info["http_code"] == "302")
    		    $this->geLog( "#query# redirected AUTH : [".$it."/".GE_CURL_MAX_REDIR."]");
       			// manually performs redirect (because cookies must be resent each time)
    	        $ch_redir = curl_init();
    			$opts[CURLOPT_URL] = $info["redirect_url"];
    			// $existing_headers[] = 'Cookie: reefssid='.$this->reefssid; // already set in principle
    			// $opts[CURLOPT_HTTPHEADER] = $existing_headers; // already set in principle
    			curl_setopt_array($ch_redir, $opts);
    			$this->geLog( "#query# execute REDIR query...");
    			$result_redir = curl_exec($ch_redir);
        		$this->geLog( "#query# REDIR RESULT=[".$result_redir."]");
				$info = curl_getinfo($ch_redir);
    			// if OAuth redirect : process cookies
				$response_array = explode("\r\n\r\n", $result_redir);
    			$header_redir = $response_array[0];
    			preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header_redir, $matches);
    			foreach($matches[1] as $item) {
    				parse_str($item, $cookie);
    				if (!isset($cookie["usertoken"])) {
    					$this->geLog( "#query# usertoken COOKIE NOT FOUND");
    					throw new GEJsonErrorType(500, "usertoken cookie not found");
    				}
    			}
    			if (isset($cookie["usertoken"])) {
    				// query does not returns JSON. Then we replace it...
					$headers = explode("\r\n\r\n", $result_redir)[0];
    				$body = '{"usertoken": "'.$cookie["usertoken"].'"}';
				break;
    			} // else body remains the principal one
			}
		}
		// end auth
		//
		// ** MANAGE RESPONSE
		$headers = explode("\r\n", $headers);
        //Only 2XX response are considered as a success
        if(strpos($headers[0], 'HTTP/1.1 2') !== FALSE) {
            $decode = json_decode($body, TRUE);
            if(!$decode) {
                if (preg_match('/^HTTP\/1.1 ([0-9]{3,3}) (.*)$/', $headers[0], $matches)) {
                    throw new GEJsonErrorType($matches[1], $matches[2]);
                } else throw new GEJsonErrorType(200, "OK");
            }
			return $decode;
        } else {
            if (!preg_match('/^HTTP\/1.1 ([0-9]{3,3}) (.*)$/', $headers[0], $matches)) {
                $matches = array("", 400, "bad request");
            }
            $decode = json_decode($body, TRUE);
            if(!$decode) {
                throw new GEApiErrorType($matches[1], $matches[2], null);
            }
            throw new GEApiErrorType($matches[1], $matches[2], $decode);
        }
    }
    /**
    * Returns the current REEF SSID
    */
    public function getReefSsid()
    {
        return $this->reefssid;
    }
    /**
    * Returns the current OAUTH TOKEN
    */
    public function getUserToken()
    {
		$this->geLog( "#getUserToken# enter function");
		if (isset($this->usertoken)) {
			try {
				$this->getHealth();
			}
			catch(GENotLoggedErrorType $ex) {
				// proceed to login
				$this->geLog( "#getUserToken# not logged or token expired, login needed");
				$this->usertoken = $this->doLogin();
				$_SESSION['user_token'] = $this->usertoken;
			}
			catch(GECurlErrorType $ex) {
    			$this->geLog( "#getUserToken# error while getting token : ".$ex->getCode()." / ".$ex->getMessage());
				throw new GEClientException($ex->getCode(), $ex->getMessage());
			}
			$this->geLog( "#getUserToken# fetched from memory");
		} else {
			// get token from session
			if (isset($_SESSION['user_token'])) {
				$this->usertoken = $_SESSION['user_token'];
				$this->geLog( "#getUserToken# fetched from PHP session : [".$this->usertoken."]");
			} else {
				// proceed to login
				$this->geLog( "#getUserToken# no token > Login needed");
				$this->usertoken = $this->doLogin();
				$_SESSION['user_token'] = $this->usertoken;
			}
		}
		return true;
    }
    /**
    * Performs login with OAuth token retrieval
    */
    public function doLogin()
    {
		$this->geLog( "#doLogin# enter function");
        // STEP 1 : login
		$ret = $this->query($this->uri['identification_uri'], 'POST', array( 'email' => $this->username, 'password' => $this->password ) );
		//
		if ($ret["status"]=="ok") {
			$this->geLog( "#doLogin# login query OK");
			$this->reefssid = $ret["reefssid"];
		} else {
			$this->geLog( "#doLogin# login query FAILED");
			throw new GEClientException("500", "could not log in");
		}
		// STEP 2 : authentication
		$ret = $this->query($this->uri['authorize_uri'], 'GET', array( 'op' => 'gigaset' ) );
		if(isset($ret["usertoken"])) {	
			if (strlen($ret["usertoken"])>2) return $ret["usertoken"];
		}
		return false;
    }

	/**
	/* MAIN METHODS 
	**/
	
	/**
	* gets system status (mainly used for service availability)
	* @ return
	* OK or KO status (from JSON response)
	**/
	public function isAlive() 
	{
		$this->geLog( "#isAlive# enter function");
        try {
			// query
			$ret = $this->query($this->uri['status_uri'], 'GET', GE_NO_PARAMS );
		}
		catch (GEClientException $ex) {
			$this->geLog( "#isAlive# API CALL FAILED");
			throw new GEClientException($ex->getCode(), $ex->getMessage());
		}
		$this->geLog( "#isAlive# API CALL OK. ret = ".print_r($ret));
		// status
		if (isset($ret["error"])) {
			throw new GECurlErrorType($ret["error"]["code"], $ret["error"]["message"]);
		}
		// response
		if ($ret["isMaintenance"] === FALSE) {
			return true;
		}
		return false; // any other case is nok
	}
	/**
	* gets system health (mainly used for login check)
	* @ return
	* OK or KO status (from JSON response)
	**/
	public function getHealth($details=false) 
	{
		$this->geLog( "#getHealth# enter function");
		// query
        $ret = $this->query($this->uri['health_uri'], 'GET', GE_NO_PARAMS );
		// status
		if (isset($ret["error"])) {
			if ($ret["error"]["code"]=="401") {
				throw new GENotLoggedErrorType("401", "user not logged in");
			} else {
				throw new GECurlErrorType($ret["error"]["code"], $ret["error"]["message"]);
			}
		}
		// response
		if ($details) {
			return $ret;
		} else {
			if(isset($ret["system_health"])) {	
				if ($ret["system_health"]=="green") return true;
				else return false;
			} 
		}
	}
	/**
	* gets events from system (what occured in the house)
	* @ return
	* OK or KO status (from JSON response)
	**/
	public function getEvents($event_nb,$event_type='all') 
	{
		$this->geLog( "#getEvents# enter function");
		if ($event_type=='all') $params = array('limit' => $event_nb);
		else $params = array('limit' => $event_nb,'group' => $event_type);
		// query
        $ret = $this->query($this->uri['base_uri'].'/api/v2/me/events', 'GET', $params );
		// status
		if (isset($ret["error"])) {
			if ($ret["error"]["code"]=="401") {
				throw new GENotLoggedErrorType("401", "user not logged in");
			} else {
				throw new GECurlErrorType($ret["error"]["code"], $ret["error"]["message"]);
			}
		}
		// response
		if(isset($ret["home_state"])) {	
			if ($ret["home_state"]=="ok") {
				return $ret["events"];
			} else return $ret["home_state"];
		}
	}
	/**
	* gets elements from system basestation
	* @ return
	* OK or KO status (from JSON response)
	**/
	public function getFromBase($type="endnodes") 
	{
		// API is different for cameras
		if ($type=="cameras") $url = $this->uri['base_uri'].'/api/v1/me/cameras';
		else $url = $this->uri['base_uri'].'/api/v1/me/basestations';
        // query
		$ret = $this->query($url, 'GET', GE_NO_PARAMS );
		// status
		if (isset($ret["error"])) {
			if ($ret["error"]["code"]=="401") {
				throw new GENotLoggedErrorType("401", "user not logged in");
			} else {
				throw new GECurlErrorType($ret["error"]["code"], $ret["error"]["message"]);
			}
		}
		// stores basestation ID
		if($type!="cameras") {
			$this->basestation_id = $ret[0]["id"];
		}
		// response
		if ($type=="cameras")  return $ret; // camera list
		if ($type=="endnodes") return $ret[0]["endnodes"]; // only one basestation managed atm (by Gigaset as well btw)
		if ($type=="sensors")  return $ret[0]["sensors"];
		if ($type=="modes")    return $ret[0]["intrusion_settings"];
		// in case of wrong parameters
		return $ret[0];
	}
	/**
	* switch alarm mode, from mode X to mode Y
	**/
	public function switchMode($requestedMode='home') 
	{
		if (!in_array($requestedMode,GE_ALARM_MODES)) {
			throw new GEClientException(400, "Trying to set inexisting mode");
		}
		// query
		$this->setFileUpload(true);
        $ret = $this->query($this->uri['base_uri'].'/api/v1/me/basestations/'.$this->basestation_id, 'POST', '{"intrusion_settings":{"active_mode":"'.$requestedMode.'"}}' );
		$this->setFileUpload(false);
		// status
		if (isset($ret["error"])) {
			if ($ret["error"]["code"]=="401") {
				throw new GENotLoggedErrorType("401", "user not logged in");
			} else {
				throw new GECurlErrorType($ret["error"]["code"], $ret["error"]["message"]);
			}
		}
		// response
		if(isset($ret["_id"])) {	
			return $ret["_id"];
		} else return false;
		
	}
	// end of class
}
?>
