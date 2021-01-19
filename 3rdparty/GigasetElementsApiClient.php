<?php

/*
 * Gigaset "Smart Home" or "Elements" client-side implementation.
 *
 * @author Jean-Marie Bonnefont <jbonnefont@elphe.com>. Based on work done by dynasticopheus : https://github.com/dynasticorpheus/gigasetelements-cli
 * @date: 2020-05-10
 *
 */

// ini_set('display_errors',1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

session_name('GigasetElementsApiClient');
session_start();

define('CURL_ERROR_TYPE', 0);
define('API_ERROR_TYPE',1);//error return from api
define('INTERNAL_ERROR_TYPE', 2); //error because internal state is not consistent
define('JSON_ERROR_TYPE',3);
define('NOT_LOGGED_ERROR_TYPE', 4); //unable to get access token

define('CURL_MAX_REDIR', 5);
define('WITH_LOG', True);

define('BACKEND_BASE_URI', "https://api.gigaset-elements.de");
define('BACKEND_STATUS_URI', "https://status.gigaset-elements.de/api/v1/status");
define('BACKEND_HEALTH_URI', "https://api.gigaset-elements.de/api/v2/me/health");
#
define('BACKEND_IDENT_URI', "https://im.gigaset-elements.de/identity/api/v1/user/login");
define('BACKEND_AUTH_URI',"https://api.gigaset-elements.de/api/v1/auth/openid/begin");
#
// define('COOKIE_LOCATION',"/tmp/gigaelem.txt");
#
define('ALARM_MODES',array('home','away','night','custom'));
#
define('SENSOR_NAMES', array(
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
		
define('EVENT_TYPES', array(
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
		$this->error_type = API_ERROR_TYPE;
    }
}

class GECurlErrorType extends GEClientException
{
    public $error_type;
	function __construct($code, $message)
    {
        parent::__construct($code, $message);
		$this->error_type = CURL_ERROR_TYPE;
    }
}

class GEJsonErrorType extends GEClientException
{
    public $error_type;
    function __construct($code, $message)
    {
        parent::__construct($code, $message);
		$this->error_type = JSON_ERROR_TYPE;
    }
}

class GEInternalErrorType extends GEClientException
{
    public $error_type;
    function __construct($message)
    {
        parent::__construct($code, $message);
		$this->error_type = INTERNAL_ERROR_TYPE;
    }
}

class GENotLoggedErrorType extends GEClientException
{
    public $error_type;
    function __construct($code, $message)
    {
        parent::__construct($code, $message);
		$this->error_type = NOT_LOGGED_ERROR_TYPE;
    }
}
/*
 * Main class
 */
 
class GigasetElementsApiClient
{
	/**
	* Array of persistent variables stored.
	*/
	// protected $ip;
    protected $file_upload_support;
    protected $reefssid;
    protected $usertoken;
	protected $basestation_id;
    /**
   * Default options for cURL.
   */
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
    //
	public $ge_log = ["init."]; // useful for communicating information to caller
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
        $this->uri = array("base_uri" => BACKEND_BASE_URI, "status_uri" => BACKEND_STATUS_URI, "health_uri" => BACKEND_HEALTH_URI, "identification_uri" => BACKEND_IDENT_URI, "authorize_uri" => BACKEND_AUTH_URI);
		// storing credentials
		if(!isset($config["username"])) throw new GEClientException("500", "no username provided");
		if(!isset($config["password"])) throw new GEClientException("500", "no password provided");
        $this->username = $config["username"];
        $this->password = $config["password"];
    }

	private function geLog($message="") {
		if (WITH_LOG) {
			$this->ge_log[] = $message;
		}
	}

    /**
    * Makes an HTTP request.
    *
    * This method can be overriden by subclasses if developers want to do
    * fancier things or use something other than cURL to make the request.
    *
    * @param $path
    *   The target path, relative to base_path/service_uri or an absolute URI.
    * @param $method
    *   (optional) The HTTP method (default 'GET').
    * @param $params
    *   (optional The GET/POST parameters.
    *
    * @return
    *   The json_decoded result or GElientException if pb happend
    */
    public function makeRequest($path, $method = 'GET', $params = array())
    {
		$this->geLog("#makeRequest# enter function");
        $ch = curl_init();
        $opts = self::$CURL_OPTS;
        if ($params)
        {
            switch ($method)
            {
                case 'GET':
                    $path .= '?' . http_build_query($params, NULL, '&');
                break;
                // Method override as we always do a POST.
                default:
                    if ($this->file_upload_support)
                    {
                        $opts[CURLOPT_POSTFIELDS] = $params;
                    }
                    else
                    {
                        $opts[CURLOPT_POSTFIELDS] = http_build_query($params, NULL, '&');
                    }
                break;
            }
        }
		$this->geLog( "#makeRequest# NEW REQUEST ***");
		$this->geLog( "#makeRequest# PATH=".$path);
		// 
		// PREPARE QUERY
		// *****************
        $opts[CURLOPT_URL] = $path;
		$auth_redirect = False;
        // Disable the 'Expect: 100-continue' behaviour. This causes CURL to wait
        // for 2 seconds if the server does not support this header.
        if (isset($opts[CURLOPT_HTTPHEADER]))
        {
            $existing_headers = $opts[CURLOPT_HTTPHEADER];
            $existing_headers[] = 'Expect:';
			// handle cookies - SPECIFIC HERE
			if (isset($this->usertoken))
			{
				$this->geLog("#makeRequest# adding usertoken cookie : [".$this->usertoken."]");
				$existing_headers[] = 'Cookie: usertoken='.$this->usertoken;
			}
			elseif (isset($this->reefssid)) // don't add reefssid if already authentified
			{
				$this->geLog("#makeRequest# no usertoken, but reefssid present (auth): [".$this->reefssid."]");
				$existing_headers[] = 'Cookie: reefssid='.$this->reefssid;
				$auth_redirect = True;
			}
			else
			{
				$this->geLog("#makeRequest# no reefssid (user not yet identified)");
			}
	
            $opts[CURLOPT_HTTPHEADER] = $existing_headers;
        }
        else
        {
            $opts[CURLOPT_HTTPHEADER] = array('Expect:');
        }
        curl_setopt_array($ch, $opts);
		// 
		// EXECUTE QUERY
		// *****************
		$this->geLog( "#makeRequest# execute query...");
        $result = curl_exec($ch);
		$this->geLog( "#makeRequest# RESULT=[".$result."]");

        $errno = curl_errno($ch);
        // manage CURLE_SSL_CACERT || CURLE_SSL_CACERT_BADFILE cases
        if ($errno == 60 || $errno == 77)
        {
            $this->geLog( "#makeRequest# WARNING ! SSL_VERIFICATION has been disabled since ssl error retrieved. Please check your certificate http://curl.haxx.se/docs/sslcerts.html");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            $result = curl_exec($ch);
        }
		// manage result
        if ($result === FALSE)
        {
            $e = new GECurlErrorType(curl_errno($ch), curl_error($ch));
            curl_close($ch);
            throw $e;
        }
		// normal exec
        $info = curl_getinfo($ch);
		//
        curl_close($ch);
		// DEBUG
		// echo var_dump(explode("\r\n",$result));
		//
        // Normal query : split the HTTP response into header and body.
        $response_array = explode("\r\n\r\n", $result);
		$headers = $response_array[0];
		$body=$response_array[1];
		//
		// Manage AUTHENTIFICATION
		// *****************
		if ($auth_redirect) {
    		for ($it=1; $it<CURL_MAX_REDIR; $it++) {
				if ($info["http_code"] == "302")
    		    $this->geLog( "#makeRequest# redirected AUTH : [".$it."/".CURL_MAX_REDIR."]");
    
    			// manually performs redirect
    	        $ch_redir = curl_init();
    			$opts[CURLOPT_URL] = $info["redirect_url"];
    			// $existing_headers[] = 'Cookie: reefssid='.$this->reefssid; // already set in principle
    			// $opts[CURLOPT_HTTPHEADER] = $existing_headers; // already set in principle
    			curl_setopt_array($ch_redir, $opts);
    			$this->geLog( "#makeRequest# execute REDIR query...");
    			$result_redir = curl_exec($ch_redir);
        		$this->geLog( "#makeRequest# REDIR RESULT=[".$result_redir."]");
				$info = curl_getinfo($ch_redir);
    			// 
    			// if OAuth redirect : process cookies
    			$response_array = explode("\r\n\r\n", $result_redir);
    			$header_redir = $response_array[0];
    			//
    			preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header_redir, $matches);
    			foreach($matches[1] as $item) {
    				parse_str($item, $cookie);
    				if (!isset($cookie["usertoken"]))
    				{
    					$this->geLog( "#makeRequest# usertoken COOKIE NOT FOUND");
    					throw new GEJsonErrorType(500, "usertoken cookie not found");
    				}
    			}
    			if (isset($cookie["usertoken"])) {
    				// query does not returns JSON. Then we replace it...
					$headers = explode("\r\n\r\n", $result_redir)[0];
    				$body = '{"usertoken": "'.$cookie["usertoken"].'"}';
				break;
    			}
    			// else body remains the principal one
			}
		}
		// end auth
		//
		// MANAGE RESPONSE
		// ************
		//
		// gets header detail
		$headers = explode("\r\n", $headers);
        //
        //Only 2XX response are considered as a success
        if(strpos($headers[0], 'HTTP/1.1 2') !== FALSE)
        {
            $decode = json_decode($body, TRUE);
            if(!$decode)
            {
                if (preg_match('/^HTTP\/1.1 ([0-9]{3,3}) (.*)$/', $headers[0], $matches))
                {
                    throw new GEJsonErrorType($matches[1], $matches[2]);
                }
                else throw new GEJsonErrorType(200, "OK");
            }
			return $decode;
        }
        else
        {
            if (!preg_match('/^HTTP\/1.1 ([0-9]{3,3}) (.*)$/', $headers[0], $matches))
            {
                $matches = array("", 400, "bad request");
            }
            $decode = json_decode($body, TRUE);
            if(!$decode)
            {
                throw new GEApiErrorType($matches[1], $matches[2], null);
            }
            throw new GEApiErrorType($matches[1], $matches[2], $decode);
        }
    }
	/**
	* PUBLIC METHODS
	**/
	//
	public function setFileUpload($set=false)
	{
	$this->file_upload_support=$set;
	}
    /**
    * Returns the current token
    */
    public function getUserToken()
    {
		$this->geLog( "#getUserToken# enter function");
		# TODO : manage cookie directly with session
		// then we login
		if (isset($this->usertoken))
		{
			try 
			{
				$this->getHealth();
			}
			catch(GENotLoggedErrorType $ex)
			{
				// proceed to login
				$this->geLog( "#getUserToken# not logged or token expired, login needed");
				$this->usertoken = $this->doLogin();
				$_SESSION['user_token'] = $this->usertoken;
			}
			catch(GECurlErrorType $ex)
			{
    			$this->geLog( "#getUserToken# error while getting token : ".$ex->getCode()." / ".$ex->getMessage());
				throw new GEClientException($ex->getCode(), $ex->getMessage());
			}
			$this->geLog( "#getUserToken# fetched from memory");
		}
		else 
		{
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
    * Returns the current REEF SSID
    */
    public function getReefSsid()
    {
        return $this->reefssid;
    }

	/**
	* gets system sttus (mainly used for service availability)
	* @ return
	* OK or KO status (from JSON response)
	**/
	public function isAlive() 
	{
		$this->geLog( "#isAlive# enter function");
        try
		{
			$ret = $this->makeRequest($this->uri['status_uri'],
				'GET',
				array()
				);
		}
		catch (GEClientException $ex)
		{
			$this->geLog( "#isAlive# API CALL FAILED");
			throw new GEClientException($ex->getCode(), $ex->getMessage());
		}
		$this->geLog( "#isAlive# API CALL OK. ret = ".print_r($ret));
		// manages status
		if (isset($ret["error"]))
		{
		throw new GECurlErrorType($ret["error"]["code"], $ret["error"]["message"]);
		}
		// manages response
		if ($ret["isMaintenance"] === FALSE)
		{
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
        $ret = $this->makeRequest($this->uri['health_uri'],
            'GET',
            array()
            );
		// manages status
		if (isset($ret["error"]))
		{
			if ($ret["error"]["code"]=="401")
			{
				throw new GENotLoggedErrorType("401", "user not logged in");
			} 
			else
			{
				throw new GECurlErrorType($ret["error"]["code"], $ret["error"]["message"]);
			}
		}
		// manages response
		if ($details) {
			return $ret;
		} else {
			if(isset($ret["system_health"]))
			{	
				if ($ret["system_health"]=="green") return true;
				else return false;
			} 
		}
	}
    /**
    * Performs login with OAuth token retrieval
    *
    * @returns
    *   token to be stored
    */
    public function doLogin()
    {
		$this->geLog( "#doLogin# enter function");
        // STEP 1 : login
		$ret = $this->makeRequest($this->uri['identification_uri'],
            'POST',
            array(
				'email' => $this->username,
				'password' => $this->password
				)
            );
		if ($ret["status"]=="ok")
		{
			$this->geLog( "#doLogin# login query OK");
			// stores for auth
			$this->reefssid = $ret["reefssid"];
		}
		else 
		{
			$this->geLog( "#doLogin# login query FAILED");
			throw new GEClientException("500", "could not log in");
		}
		// STEP 2 : authentication
		$ret = $this->makeRequest($this->uri['authorize_uri'],
            'GET',
            array(
				'op' => 'gigaset'
				)
            );
		// manages response
		if(isset($ret["usertoken"]))
		{	
			if (strlen($ret["usertoken"])>2) return $ret["usertoken"];
		}
		return false;
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
		//
        $ret = $this->makeRequest($this->uri['base_uri'].'/api/v2/me/events',
            'GET',
			$params
			);
		// manages status
		if (isset($ret["error"]))
		{
			if ($ret["error"]["code"]=="401")
			{
				throw new GENotLoggedErrorType("401", "user not logged in");
			} 
			else
			{
				throw new GECurlErrorType($ret["error"]["code"], $ret["error"]["message"]);
			}
		}
		// manages response
		if(isset($ret["home_state"]))
		{	
			if ($ret["home_state"]=="ok")
			{
				return $ret["events"];
			}
			else return $ret["home_state"];
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
		if ($type=="cameras")
			$url = $this->uri['base_uri'].'/api/v1/me/cameras';
		else
			$url = $this->uri['base_uri'].'/api/v1/me/basestations';
        $ret = $this->makeRequest($url,
            'GET',
			array()
			);
		// manages status
		if (isset($ret["error"]))
		{
			if ($ret["error"]["code"]=="401")
			{
				throw new GENotLoggedErrorType("401", "user not logged in");
			} 
			else
			{
				throw new GECurlErrorType($ret["error"]["code"], $ret["error"]["message"]);
			}
		}
		// stores basestation ID
		if($type!="cameras")
		{
			$this->basestation_id = $ret[0]["id"];
		}
		// manages response
		if ($type=="cameras")
			return $ret; // camera list
		if ($type=="endnodes")
			return $ret[0]["endnodes"]; // only one basestation managed atm (by Gigaset as well btw)
		if ($type=="sensors")
			return $ret[0]["sensors"];
		if ($type=="modes")
			return $ret[0]["intrusion_settings"];
		// in case of wrong parameters
		return $ret[0];
	}
	/**
	* switch smart home mode (alarm)
	* @ return
	* OK or KO status (from JSON response)
	**/
	public function switchMode($requestedMode='home') 
	{
		if (!in_array($requestedMode,ALARM_MODES))
		{
			throw new GEClientException(400, "Trying to set inexisting mode");
		}
		//
		$params = '{"intrusion_settings":{"active_mode":"'.$requestedMode.'"}}';
		$this->setFileUpload(true);
        $ret = $this->makeRequest($this->uri['base_uri'].'/api/v1/me/basestations/'.$this->basestation_id,
            'POST',
			$params 
			);
		$this->setFileUpload(false);
		// manages status
		if (isset($ret["error"]))
		{
			if ($ret["error"]["code"]=="401")
			{
				throw new GENotLoggedErrorType("401", "user not logged in");
			} 
			else
			{
				throw new GECurlErrorType($ret["error"]["code"], $ret["error"]["message"]);
			}
		}
		// manages response
		if(isset($ret["_id"]))
		{	
			return $ret["_id"];
		}
		else return false;
		
	}
	// end of class
}

/* 
echo "<br>*** CREATING API CLIENT ***<br>";

$client =  new GigasetElementsApiClient(array(
				'username' => 'jbonnefont@elphe.com',
				'password' => 'War3@samsoul.fr'
			 ));

echo "<br>*** CHECKING IF SERVERS ALIVE ***<br>";

try 
{
	if ($client->isAlive())
	{
		echo "API alive<br>";
	}
	else
	{
		echo "API DEAD<br>";
	}
}
catch (GEClientException $ex)
{
	echo "QUERY KO code [".$ex->getCode()."] mess [".$ex->getMessage()."]<br>";
}

echo "<br>*** CKECKING/DOING LOGIN ***<br>";

try 
{
	$client->getUserToken(); // OAuth login
}
catch (GEClientException $ex)
{
	echo "QUERY KO code [".$ex->getCode()."] mess [".$ex->getMessage()."]<br>";
}

echo "<br>*** GETTING EVENTS ***<br>";

try 
{
	$events = $client->getEvents("5","all");
}
catch (GEClientException $ex)
{
	echo "QUERY KO code [".$ex->getCode()."] mess [".$ex->getMessage()."]<br>";
}
if (is_string($events))
{
	echo "SYSTEM STATE KO : ".$events."<br>";
} 
else
{
	echo "SYSTEM STATE OK<br>";
	foreach ($events as $event) 
	{
		if (isset($event["o"]["friendly_name"])) $_object_name = $event["o"]["friendly_name"];
		else $_object_name = "";
		if (isset($event["o"]["room"]["friendlyName"])) $_friendlyname = $event["o"]["room"]["friendlyName"];
		else $_friendlyname = "Maison";
		if (isset($event["o"]["modeBefore"])) $_modechg = $event["o"]["modeBefore"]." > ".$event["o"]["modeAfter"];
		else $_modechg = "";
		
		// echo "<br>EVENT : ".var_dump($event)."<br>"; 
		echo date("Y-m-d H:i:s",substr($event["ts"],0,10))." - ".$_object_name." - ".$_friendlyname." : ".$event["type"]." - ".$_modechg."<br>";
	}
}

echo "<br>*** GETTING MODES ***<br>";

try 
{
	$elements = $client->getFromBase("modes");
	// if ($elements["active_mode"]=="home") $client->switchMode("custom");
}
catch (GEClientException $ex)
{
	echo "QUERY KO code [".$ex->getCode()."] mess [".$ex->getMessage()."]<br>";
}
//
foreach ($elements["modes"] as $mode) 
{
	if (key($mode) == $elements["active_mode"]) $color = "#00FF00"; else $color = "#AAAAAA";
	echo "Mode : <span style='color:".$color.";'>".key($mode)."</span><br>";
}


echo "<br>*** GETTING ELEMENTS ***<br>";

try 
{
	$elements = $client->getFromBase("endnodes");
	$cameras = $client->getFromBase("cameras");
}
catch (GEClientException $ex)
{
	echo "QUERY KO code [".$ex->getCode()."] mess [".$ex->getMessage()."]<br>";
}
//
foreach ($elements as $elem) 
{
	if (array_key_exists($elem["type"],SENSOR_NAMES)) $_type = SENSOR_NAMES[$elem["type"]]; else $_type = $elem["type"];
	echo "Noeud : ".$elem["id"]." - ".$_type." - ".$elem["friendly_name"]." - ".$elem["status"]."<br>";
}
foreach ($cameras as $elem) 
{
	echo "Noeud : ".$elem["id"]." - camera - ".$elem["friendly_name"]." - ".$elem["status"]."<br>";
}
 */
?>
