<?php
require_once $_SERVER['DOCUMENT_ROOT']."google-api-php-client-2.4.1/vendor/autoload.php"; // Google Client API v2.0
require_once "account_verification_token.php"; // Get the verification code.

// Get the variables (location and water quality).
$variables_from_module;
if(isset($_GET['location']) && isset($_GET['water_quality'])){
	$variables_from_module = [
	  "location" => $_GET['location'],
	  "water_quality" => (int)$_GET['water_quality']
    ];
}else{
    $variables_from_module = [
	  "location" => "ERROR",
	  "water_quality" => 0
    ];
}


/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('Water Quality Detector'); // Enter your application name.
    $client->setScopes('https://www.googleapis.com/auth/spreadsheets');
    $client->setAuthConfig('credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    $tokenPath = 'token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
		print("Token Found!");
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
			// Do not forget to refresh the page after getting the verification code and entering it to the account_verification_token.php.
            printf("Open the following link in your browser:<br><br>%s<br><br>", $authUrl); // <= Comment
            print 'Do not forget to refresh the page after getting the verification code and entering it to the account_verification_token.php.<br><br>Set the verification code in the account_verification_token.php file.'; // <= Comment
            // Set the verification code to create the token.json.
			$authCode = trim($GLOBALS['account_verification_token']);

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }else{
				print("Successful! Refresh the page.");
			}
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}


// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Sheets($client);

// Enter your spreadsheetId:
$spreadsheetId = '[Spreadsheet_ID]';
// Enter the range (the first row) under which new values will be appended:
$range = 'A1:B1';
// Append recent findings (location and water quality) to the spreadsheet.
$values = [
    [$variables_from_module["location"], $variables_from_module["water_quality"]]
];
$body = new Google_Service_Sheets_ValueRange([
    'values' => $values
]);
$params = [
    'valueInputOption' => "RAW"
];
// Append if only requested!
if(isset($_GET['location'])){
    $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
    printf("<br><br>%d cells appended.", $result->getUpdates()->getUpdatedCells());
}
