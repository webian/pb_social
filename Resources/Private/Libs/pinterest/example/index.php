<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../src/PinterestAPI.php');


var_dump(json_decode(curl_download('http://api.pinterest.com/v3/pidgets/boards/jfulk27/yummy-recipes-to-try/pins/')));


//
//$client_id = 'YOUR_PINTEREST_APP_CLIENT_ID';
//$client_secret = 'YOUR_PINTEREST_APP_CLIENT_SECRET';
//$username = 'jfulk27';
//$password = 'YOUR_PINTEREST_PASSWORD';
//
//$p = new Pinterest\PinterestAPI();
////$p->fetch_access_token($client_id, $client_secret, $username, $password);
//$resp = json_decode($p->allByUser($username),1);
//var_dump($resp);

//if($resp['status'] == "success")
//{
//	$renderPin = function($pin){echo '<div><h4>' . $pin['description'] . '</h4>'; foreach($pin['images'] as $image) echo '<img src="' . $image['url'] . '" width="' . $image['width'] . '" height="' . $image['height'] . '" alt ="' . $pin['description'] . '"></div>';};
//
//	foreach($resp['data']['pins'] as $pin)
//		$renderPin($pin);
//}

function curl_download($Url){

    // is cURL installed yet?
    if (!function_exists('curl_init')){
        die('Sorry cURL is not installed!');
    }

    // OK cool - then let's create a new cURL resource handle
    $ch = curl_init();

    // Now set some options (most are optional)

    // Set URL to download
    curl_setopt($ch, CURLOPT_URL, $Url);

    // Set a referer
//    curl_setopt($ch, CURLOPT_REFERER, "http://www.example.org/yay.htm");

    // User agent
//    curl_setopt($ch, CURLOPT_USERAGENT, "MozillaXYZ/1.0");

    // Include header in result? (0 = yes, 1 = no)
    curl_setopt($ch, CURLOPT_HEADER, 0);

    // Should cURL return or print out the data? (true = return, false = print)
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Timeout in seconds
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // Download the given URL, and return output
    $output = curl_exec($ch);

    // Close the cURL resource, and free system resources
    curl_close($ch);

    return $output;
}