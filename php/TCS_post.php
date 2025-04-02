<!DOCTYPE html>
<html>
<body>

<!-- begin PHP -->
<?php require './TCS_dbTools.php';       

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
function createFilename() {

    $folder = $GLOBALS['FOLDER'];
    if (! str_ends_with($folder, "/")) $folder .= "/";

    do {
        $filename = $folder . rand(10000, 99999) . ".html";
  
        // try again if filename already exists
    } while (file_exists($filename));

    echo "<BR>CREATING FILENAME=$filename";

    return $filename;
}


/*
 * 
 */
function createLocalHTML($urlID, $filename, $url, $caption, $date, $tags) {
    
    try {
    
            $theFile = fopen( $filename, 'w' );

            // 
            //
            $wrapper = "<div class='TCS_blogPost' id='$urlID'>";
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
            foreach ($tags as $eachTag) {
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
    try {
        $db_url = SQL_safe( $_POST['db_url'] );
        $db_name = SQL_safe( $_POST['db_name'] );
        $db_user = SQL_safe( $_POST['db_user'] );
        $db_pwd = SQL_safe( $_POST['db_pwd'] );
    } catch (Exception $e) {
        throw new Exception("Invalid database credentials: " . $e->getMessage());
    }

    echo "<BR>DB_URL: $db_url";
    echo "<BR>DB_NAME: $db_name";
    echo "<BR>DB_USER: $db_user";
    echo "<BR>DB_PWD: $db_pwd"; 

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
    
    if (! ($url && $caption && $folder) ) {
        throw new Exception("FORM must include at least URL, CAPTION, and root FOLDER");
    } else if (! $date) {
        $date = todaysDate();
    }

    // Tags use SQL_sanitize since they go into SQL queries
    $rawTags = $_POST['tags']; 
    $tags = processTags( $rawTags );
    

    
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
    connectToDB( $db_name,
                 $db_url, 
                 $db_user,
                 $db_pwd );  

    if (! isDBInit( $db_name )) throw new Exception("Database $db_name not initialized");
    // DB MUST BE initialized, tables and stored functions exist

    // create the file name where the blog post will be stored
    $filename = createFilename();

    // store to DB:
    //   filename of local html
    //   date assoc with original content URL
    //   tags
    $urlID = storeToDB( $filename, $tags, $date );
    echo "<BR>URL STORED TO DB: " . $urlID;

    // create the local .html content that will be pasted into the
    // main webpage upon return of the TCS search
    createLocalHTML($urlID, $filename, $url, $caption, $date, $tags);

    // if no Exceptions are thrown...
    echo "<BR>FILE CREATED: $filename";


    closeConnection();
    

} catch (Exception $error) {
    echo "<BR>TCS ERROR: " . $error->getMessage();
    die("<BR>TCS_post aborting.");
}

echo "<BR>SUCCESS!";
?>

</body>
</html>