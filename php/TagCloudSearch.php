<?php require './TCS_dbTools.php'; 


/*
 * DATABASE LOGIN -- REMOVED from this file on 2026-11-10.
 *
 *   $DB_URL  = "XXXXXXXX";
 *   $DB_NAME = "XXXXXXXX";
 *   $DB_USER = "XXXXXXXX";
 *   $DB_PWD  = "XXXXXXXX";
 *
 * The real values now live in tcs_config.php, next to this file on the server.
 * tcs_config.php is NEVER committed to git (see .gitignore).
 * To set up: copy tcs_config.example.php -> tcs_config.php and fill it in.
 *
 *  read-only -'guest' privileges are OK -- does NOT have to be root / admin
 */
require __DIR__ . '/tcs_config.php';




/*
//
// The TCS Protocol:
//
//   -- .JS -- sendSearchToServer()-------------------- ->  TCS server
//
//       SEARCH_TAGS --> comma,delimited,list
//       HTTP POST
//         body: tcs_tags=comma,delimited,list 
//
//
//       TCS HTTP client <- ------------------------------ TCS server
//
//       JSON data
//          2 JSON objects -- tags, urls
//               tags[] = [ tagName, popularity ]
//               urls[] = [ ID , url ]
//
//             passed to each listener callback function
//
*/


/*
 * echo the return data back to HTML
 */
function sendReturnData( $theTags, $theUrls, $errStr ) {

    // Ensure no output before headers
    if (ob_get_length()) ob_clean();

    header('Content-Type: application/json; charset=utf-8');

    if ($errStr != null) {

            $response = array( 
                'tcs_status' => false,
                'tcs_message' => $errStr
            );

    } else {
    
            $response = array(
                'tcs_status' => true,
                'tcs_message' => 'OK',
                'tcs_tags' => $theTags,
                'tcs_urls' => $theUrls
        );
    }

    echo json_encode( $response );
    exit(); // Ensure no additional output
}




/*
 *  INCOMING HTTP POST request from TagCloudSearch UI inside html page
 *  ....
 *  runs with hardcoded DB credentials, unlike the CMS tools edit/post
 *  RUN THE CODE!
 * 
 */
try {

    if (($_POST == null) || (count($_POST) == 0)) {
        throw new Exception("Did not receive POST request");
    }

    // connect to DB
    //
    connectToDB( $DB_NAME,
                 $DB_URL, 
                 $DB_USER,
                 $DB_PWD );  

                 
    if (! isDBInit( $DB_NAME )) {
        throw new Exception("Database $DB_NAME not initialized");
    }
    // DB MUST BE initialized, tables and stored functions exist


    // RECEIVE html form input data
    // tags may be invalid -- will call processTags() in dbTools
    // tcs_tags = tag,tag,tag,tag 
    //
    $rawTags = $_POST['tcs_tags'];


    $theTags = []; // empty array - no tags
    $theUrls = []; // empty array - no urls

    // Sanitize input tags to prevent SQL injection
    $safeTags = processTags( $rawTags );
    if (count($safeTags) == 0) {
        logError("NO TAGS FOUND: " . $rawTags);
        
    } else {
        
        $theUrls = getUrls_matchAllTags( $safeTags );

        // [ [ index, url ], [index, url], .... ]
        // may be empty
        //
        // theUrls -- [ID][tagName][date]
        //
        // echo "<BR>GOT URLS FROM DB!!!";
        // foreach( $theUrls as $eachRow ) {
        //     echo "<BR>$eachRow[0] -- $eachRow[1]";
        // }
        //
        // $theTags = [tag][popularity]

        if (count($theUrls) > 0) {
            $theTags = getTagsFromUrls( $theUrls );
        }
    }

    closeConnection();

    //
    // SEND JSON return data back to HTML --> TCS client-side
    //
    // $theTags and $theUrls contain the return data
    sendReturnData( $theTags, $theUrls, null );


} catch (Exception $error) {
    $errStr = "TCS ERROR: " . $error->getMessage();
    error_log("TagCloudSearch.php: " . $errStr);
    sendReturnData([], [], $errStr);
    die();
}

?>
