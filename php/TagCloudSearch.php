<?php require './TCS_dbTools.php'; 


/*
 * THESE CREDENTIALS allow TCS to log into mysql database and run queries
 *
 *  read-only -'guest' privileges are OK
 *  does NOT have to be root / admin 
 */

$DB_URL = "107.180.24.253";
$DB_NAME = "sgw_tcs_4_2022";
$DB_USER = "tcs_user";
$DB_PWD = "tcs_pwd";




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

                 
    if (! isDBInit( $DB_NAME )) throw new Exception("Database $DB_NAME not initialized");
    // DB MUST BE initialized, tables and stored functions exist


    // RECEIVE html form input data
    // tags may be invalid -- will call processTags() in dbTools
    // tcs_tags = tag,tag,tag,tag 
    //
    $rawTags = $_POST['tcs_tags'];

    // 03/2025 -- new, faster SQL query with INNER JOINs for each tag
    // must match ALL tag bricks    
    $theUrls = getUrls_matchAllTags( $rawTags );
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
    $theTags = []; // empty array - no tags
    if (count($theUrls) > 0) {
        $theTags = getTagsFromUrls( $theUrls );
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
