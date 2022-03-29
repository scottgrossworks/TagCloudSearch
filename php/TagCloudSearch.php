
<?php require './TCS_dbTools.php'; 


/*
 * THESE CREDENTIALS allow TCS to log into mysql database and run queries
 *
 *  read-only -'guest' privileges are OK
 *  does NOT have to be root / admin 
 */

$DB_URL = "---db url---";
$DB_NAME = "---db name---";
$DB_USER = "---db user---";
$DB_PWD = "---db pwd---";




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
 *  RUN THE CODE!
 * 
 */
try {

    if (($_POST == null) || (count($_POST) == 0)) {
        throw new Exception("Did not receive POST request");
    }

    //
    // RECEIVE html form input data
    // tcs_tags = tag,tag,tag,tag 
    //
    $rawTags = $_POST['tcs_tags'];
    $tags = processTags( $rawTags );

    if (count($tags) == 0) throw new Exception("Tags field cannot be empty");

    

    // connect to DB
    //
    connectToDB( $DB_URL, 
                 $DB_USER,
                 $DB_PWD );  

    $GLOBALS["DB_NAME"] = $DB_NAME;
    if (! isDBInit( $DB_NAME )) throw new Exception("Database $DB_NAME not initialized");
    // DB MUST BE initialized, tables and stored functions exist


    //
    // CALL DB functions
    //
    $theUrls = getUrlsFromTags( $tags );

    /* echo "<BR>GOT URLS FROM DB!!!";
    foreach( $theUrls as $eachRow ) {
        echo "<BR>$eachRow[0] -- $eachRow[1]";
    } */
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
    $errStr = "<BR>TCS ERROR: " . $error->getMessage();
    sendReturnData(null, null, $errStr);
    die();
}

?>
