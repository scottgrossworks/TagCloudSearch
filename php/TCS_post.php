<!DOCTYPE html>
<html>
<body>

<!-- begin PHP -->
<?php require 'TCS_dbTools.php';
      

$FOLDER = null;



/*
* Be able to locate the root directory for putting the final generated HTML
* containing the new blog post
*/
function findRootDir( $folder ) {

    if ( $GLOBALS["FOLDER"] != null ) return;

    try {
        if ( is_dir( $folder) ) {
    
            $GLOBALS["FOLDER"] = $folder;
            return;
    
        } else {
            throw new Exception("Folder not Found");
        }   
    } catch (Exception $error) {
        $errStr  = "Cannot set root directory $folder: " . $error->getMessage();
        throw new Exception( $errStr );
    }
}



/*
 * 2/2022 -- filename is a rand between 10000 --> 99999 .html
 */
function createLocalHTML($url, $caption, $date, $tags) {

    $folder = $GLOBALS['FOLDER'];
    if (! str_ends_with($folder, "/")) $folder .= "/";
    
    $filename = $folder . rand(10000, 99999) . ".html";
    echo "<BR>CREATING FILENAME=$filename";
    
    try {
    
            $theFile = fopen( $filename, 'w' );

            // 
            //
            $wrapper = "<div class='TCS_blogPost'>";
            fwrite( $theFile, $wrapper );

            //
            //
            $newDate = date_create( $date );
            $dateTag = "<div class='TCS_date'>" . date_format($newDate, "m/d/Y") . "</div>";
            fwrite( $theFile, $dateTag );

            //
            //
            $urlTag = "<BR><img class='TCS_postUrl' src='$url'>";
            fwrite( $theFile, $urlTag );

            //
            //
            $captionTag = "<BR><div class='TCS_caption'>$caption</div>";
            fwrite( $theFile, $captionTag );

 
            //
            //
            fwrite( $theFile, "<BR><ul class='TCS_bricks'>" );
            foreach ($tags as &$eachTag) {
                fwrite( $theFile, "<li class='TCS_inactive'>$eachTag</li>" );
            } 
            fwrite( $theFile, "</ul></div>" ); // close the tag list and the wrapper 

            fclose( $theFile );

        } catch (Exception $error) {

            $errorStr = "Error writing to file: $filename = " . $error->getMessage();
            throw new Exception( $errorStr );
        }

    return $filename;
}




/*
 * INCOMING HTTP POST from html page
 * 
 * 
 */
try {
    
    if (($_POST == null) || (count($_POST) == 0)) {
        throw new Exception("Did not receive POST request");
    }


    //
    // get hidden form DB login credentials
    //
    $db_url = $_POST['db_url'];
    $db_name = $_POST['db_name'];
    $db_user = $_POST['db_user'];
    $db_pwd = $_POST['db_pwd'];

    if (($db_url == null) || ($db_name == null) || ($db_user == null) || ($db_pwd == null))
        throw new Exception("Did not receive DB login credentials");

    $GLOBALS["DB_URL"] = $db_url;
    $GLOBALS["DB_NAME"] = $db_name;
    $GLOBALS["DB_USER"] = $db_user;
    $GLOBALS["DB_PWD"] = $db_pwd;


    //
    // html form input data
    // some of these are mandatory -- will throw exception later if empty
    //
    $url = $_POST['url'];
    $caption = $_POST['caption'];
    $date = $_POST['date'];
    $folder = $_POST['folder'];
    $rawTags = $_POST['tags'];
    $tags = processTags( $rawTags );

    if ( ($url == null) || ($caption == null) || ($folder == null) ) {
        throw new Exception("FORM must include at least URL, CAPTION, and root FOLDER");
    } else if (($date == null)) {
        $date = todaysDate();
    }
    
    echo "TCS_post<BR>...";
 
    echo "<BR>URL: $url";
    echo "<BR>CAPTION: $caption";
    echo "<BR>DATE: $date";
    echo "<BR>FOLDER: $folder";
    foreach ($tags as $eachTag) {
        echo "<BR>TAG: $eachTag";
    } 
    

    // will throw Exception if not found
    findRootDir( $folder );
    echo "<BR>Root Directory FOUND";



    // connect to DB
    //
    connectToDB( $db_url, 
                 $db_user,
                 $db_pwd );  

    $GLOBALS["DB_NAME"] = $db_name;
    if (! isDBInit( $db_name )) throw new Exception("Database $db_name not initialized");
    // DB MUST BE initialized, tables and stored functions exist



    // create the local .html content that will be pasted into the
    // main webpage upon return of the TCS search
    $filename = createLocalHTML($url, $caption, $date, $tags);
    echo "<BR>FILE CREATED: $filename";

    // store to DB:
    //   filename of local html
    //   date assoc with original content URL
    //   tags
    storeToDB( $filename, $tags, $date );
    echo "<BR>ALL DATA STORED TO DB";

    closeConnection();
    

} catch (Exception $error) {
    echo "<BR>TCS ERROR: " . $error->getMessage();
    die("<BR>TCS_post aborting.");
}

echo "<BR>SUCCESS!";
?>

</body>
</html>