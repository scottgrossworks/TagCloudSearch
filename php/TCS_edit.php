<!DOCTYPE html>
<html>
<body>

<!-- begin PHP -->
<?php require './TCS_dbTools.php'; 
      

/* suppress pesky warning msgs */
// error_reporting(E_ALL ^ E_WARNING); 


/*
 * HTML POST request
 * 
 */
try {

    echo("IN TCS EDIT<BR>...");

    if (($_POST == null) || (count($_POST) == 0)) {
        throw new Exception("Did not receive POST request");
    }

    //
    // html form input data
    // 
    //
    $urlID = (isset($_POST['ID'])) ? $_POST['ID'] : null;
    if (! $urlID) throw new Exception( "Must supply URL ID" );

    $editButton = (isset($_POST['edit'])) ? $_POST['edit'] : null;
    $deleteButton = (isset($_POST['delete'])) ? $_POST['delete'] : null; 
    if (! ($editButton || $deleteButton)) throw new Exception( "ONLY accepts edit or delete commands" );

    //
    // get hidden form DB login credentials
    //
    try {
        $db_url = SQL_safe( $_POST['db_url'] );
        $db_name = SQL_safe( $_POST['db_name'] );
        $db_user = SQL_safe( $_POST['db_user'] );
        $db_pwd = SQL_safe( $_POST['db_pwd'] );
    } catch (Exception $e) {
        throw new Exception("Invalid database credentials: " . $e->getMessage());
    }

    // connect to DB
    //
    connectToDB( $db_name,
                 $db_url, 
                 $db_user,
                 $db_pwd );  

    if (! isDBInit( $db_name )) throw new Exception("Database $db_name not initialized");
    // DB MUST BE initialized, tables and stored functions exist

    if ($deleteButton) {
        echo "<BR>DELETING POST: $urlID";
        // remove any tags assoc with this urlID
        removeTags( $db_name, $urlID );
        // remove the url
        $success = removeUrl( $db_name, $urlID );
        if ($success) {
            echo "<BR>SUCCESS DELETED url ID=$urlID";
        } else {
            echo "<BR>DELETE FAILED, ID NOT FOUND=$urlID";
        }
    } else { //editButton
        echo "<BR>EDITING POST: $urlID";
        
        // make sure the url ID exists
        $sql = "SELECT * FROM $db_name.urls WHERE ID=$urlID;";
        $result = runSQL( $sql );
        $ID_found = $result->fetch_array(MYSQLI_NUM);
        if (! $ID_found) throw new Exception("URL ID not found: $urlID");
        
        // these are the NEW tags - now we can process them since DB is connected
        $rawTags = $_POST['tags'];
        $newTags = processTags( $rawTags );
        
        if (sizeof($newTags) == 0) throw new Exception( "NO TAGS TO EDIT" );

        /*
        * get all tags associated with urlID
        * for each old tag
        * tesdb.tags2urls -> remove tagName,ID mapping 
        * testdb.tags -> DECREMENT popularity 
        */
        removeTags($db_name, $urlID);

        // tags: create new tag or increment popularity
        // tags2urls: create new mapping tag --> ID from tcs_storeNewUrl
        foreach ($newTags as $eachTag) {

            // final check just to make sure we don't add empty tags
            $len = strlen($eachTag);
            if ($len > 0) {
                $sql = "SELECT $db_name.tcs_createNewTag($urlID,'$eachTag')";
                runSQL( $sql );
                echo "<BR>CREATED NEW TAG $eachTag";
            }
        }

     
    }

    closeConnection();
    

} catch (Exception $error) {
    echo "<BR>TCS ERROR: " . $error->getMessage();
    die("<BR>TCS_post aborting.");
}


echo "<BR>SUCCESS!";
?>

</body>
</html>