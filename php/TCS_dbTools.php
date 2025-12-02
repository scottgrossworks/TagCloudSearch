<?php

/*
 * Create a TCS database using php and mysql
 * These functions create the tables and the stored functions used by TCS queries
 *  uses ROOT / ADMIN DB login -- modifies DB
 *
 * DELIMITER //
 * 
 *  ... define function here ...
 * 
 * // 
 * 
 * 
 */

/* 
 * GLOBALS
 */

$DB_NAME = null;
$DB_URL = null;
$DB_USER = null;
$DB_PWD = null;
$GLOBALS["BLACKLIST"] = ['select', 'drop', 'insert', 'update', 'delete'];

$MAX_TAG_LENGTH = 20;


/*
 * Ensures that every time getDBConnection() is called, the correct database is explicitly selected.
 */
function getDBConnection() {
   
    if (! isset($GLOBALS["DB_CONNECTION"])) {
        return null;
    }

    $con = $GLOBALS["DB_CONNECTION"];
    
    // Select the DB if not already selected
    if (! $con->select_db($GLOBALS["DB_NAME"])) {
        error_log("Cannot select DB: " . $GLOBALS["DB_NAME"]);
        return null;
    }

    return $con;
}




/*
 */
function isDBConnected() {

    $isConnected = $GLOBALS[ "DB_CONNECTION" ];

    return ( $isConnected != null );
}



/*
 *  CHECK THE DB CONFIG VARIABLE == 1
 *  only set after DB fully initialized 
 */
function isDBInit( $DB_name ) {

    $initDone = 0;

    try {  
        $sql = "SELECT * FROM $DB_name.config WHERE (initDone)";
        // DB call
        // will throw Exception if not connected to DB
        $result = runSQL( $sql );

        // process results
        $initDone = $result->fetch_column(0);

    } catch (Exception $error) {
        // if there's a problem -- it's not initialized
        $initDone = 0;
    }
    
    return ($initDone == 1); // return true if initialized
}


/*
 * Create main DATABASE if it doesn't exist
 *  RE-CREATE from scratch if it does
 *  will throw Exceptions on errors
 */
function createDB( $con, $DB_name ) {

    try {
        
        // Validate that $DB_name is a safe identifier:
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $DB_name)) {
            throw new Exception("Invalid DB name");
        }

        // does the database already exist?
        $sql = "SHOW DATABASES LIKE '$DB_name'";
        $result = $con->query($sql);
        if (!$result) throw new Exception("Failed to query databases: " . $con->error);
        
        if (mysqli_fetch_array($result)) {
            throw new Exception("Database '$DB_name' already exists — abort to prevent data loss.");
        }

        // create DB
        $sql = "CREATE DATABASE $DB_name;";  // must be unquoted!
        $dbCreated = $con->query( $sql );
        if (! $dbCreated) throw new Exception("Failed to create DB: $DB_name");

        // debug_log("EXECUTED: $sql");

        // make this the selected DB
        if (! $con->select_db($DB_name)) {
            throw new Exception("Could not select DB: " . $con->error);
        }

        // debug_log("$DB_name IS SELECTED DB");

    } catch (Exception $error) {

        $err_msg = $error->getMessage();
        logError( $err_msg );
        throw new Exception( "Cannot create DB: " . $err_msg);
    }

}


/*
 *
 */
function createTables() {

    // this is just a placeholder -- the default date for a TCS_post will be the date posted
    // if no date is given in the create form
    $theDate = date('Y-m-d');

    try {  
        $sql =
            "CREATE TABLE `config` (
             `initDone` int(32) NOT NULL DEFAULT 0,
             UNIQUE KEY `is_init` (`initDone`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='configuration values for TCS';";            
        runSQL( $sql );


        $sql=
            "CREATE TABLE `tags` (
                `tagName` varchar(32) NOT NULL,
                `popularity` int(11) unsigned NOT NULL DEFAULT 1,
                PRIMARY KEY (`tagName`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='each tag has a name and # - how many total posts use it';";

        runSQL( $sql );       
            

        $sql=
            "CREATE TABLE `urls` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `url` varchar(128) NOT NULL,
                `postDate` date NOT NULL DEFAULT '$theDate', 
                PRIMARY KEY (`ID`)
            ) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COMMENT='URL for each blog post -- date added at post time';";
        
        runSQL( $sql );
            

        $sql=
            "CREATE TABLE `tags2urls` (
                `tagName` varchar(32) NOT NULL,
                `ID` int(11) NOT NULL,
                UNIQUE KEY `TagToUrl` (`tagName`,`ID`),
                KEY `map_ID` (`ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='maps each tag to all the posts which use it';";

        runSQL( $sql );

    } catch (Exception $error) {
        $msg = "MySQL ERROR creating TABLES: " . $error->getMessage();
        throw new Exception( $msg );
    }

}


/*
 * functon morePopular(tagName) -> increments popularity col for tagName - 
 * 
 *     $sql =
        "USE `$DB_name`;
        DROP FUNCTION IF EXISTS `tcs_morePopular`;
DELIMITER //
CREATE FUNCTION `tcs_morePopular` (eachTag varchar(32))
        RETURNS INT DETERMINISTIC
        BEGIN
    
        DECLARE pop INT;
        SET @pop := 0;
        
        UPDATE tags 
                SET tags.popularity = @pop := tags.popularity + 1
                WHERE tags.tagName = eachTag;
        
        RETURN @pop;
        END//
DELIMITER ;";

 */
function createMorePopular() {

    try {  
        // $sql = "USE `$DB_name`;";
        // runSQL( $sql );

        $sql = "DROP FUNCTION IF EXISTS `tcs_morePopular`;";
        runSQL( $sql );

        $sql =
        "CREATE FUNCTION tcs_morePopular (eachTag varchar(32))
        RETURNS INT DETERMINISTIC
        BEGIN
    
        DECLARE pop INT;
        SET @pop := 0;
        
        UPDATE tags 
                SET tags.popularity = @pop := tags.popularity + 1
                WHERE tags.tagName = eachTag;
        
        RETURN @pop;
        END;";

        runSQL( $sql );


    } catch (Exception $error) {
        $msg = "MySQL ERROR creating tcs_morePopular(): " . $error->getMessage();
        throw new Exception( $msg );
    }
}


/*
 * decrements popularity col for tagName - 
 */
function createLessPopular() {
    //
    try {
        // functon lessPopular(tagName) -> decrements popularity col for tagName
        // keep popularity ABOVE 0
        // - returns popularity

        $sql = "DROP FUNCTION IF EXISTS `tcs_lessPopular`;";
        runSQL( $sql );

        $sql = "CREATE FUNCTION tcs_lessPopular (eachTag varchar(32))
        RETURNS INT DETERMINISTIC
        BEGIN
        
        DECLARE pop INT;
        SET @pop := 0;
        
        UPDATE tags 
                SET tags.popularity = @pop := tags.popularity - 1
                WHERE tags.popularity > 0 AND tags.tagName = eachTag;
        
        RETURN @pop;
        END;";

        runSQL( $sql );

    } catch (Exception $error) {
        $msg = "MySQL ERROR creating tcs_lessPopular(): " . $error->getMessage();
        throw new Exception( $msg );
    }
}



/*
 * function tcs_insertOrPopular( $eachTag ) -> create a new tag of popularity = 1
 * or increment popularity of existing tag -- 
 */
function createInsertOrPopular() {

    try {
        
        $sql = "DROP FUNCTION IF EXISTS `tcs_insertOrPopular`;";
        runSQL( $sql );

        $sql =
        "CREATE FUNCTION tcs_insertOrPopular(eachTag varchar(32))
        RETURNS INT DETERMINISTIC
        
        BEGIN
        DECLARE popularity INT;
        SET popularity = 1;
        
        IF EXISTS (SELECT * FROM tags WHERE tags.tagName = eachTag) 
        THEN
        SELECT tcs_morePopular(eachTag) INTO @popularity;
        ELSE
        INSERT INTO tags(tagName) VALUES (eachTag);
        END IF;
        
        RETURN @popularity;
        END;";
    
        runSQL( $sql );
            
    } catch (Exception $error) {
        $msg = "MySQL ERROR creating tcs_insertOrPopular(): " . $error->getMessage();
        throw new Exception( $msg );
    }

}


/*
 * function tcs_storeNewUrl( $url, $date ) - take a .html file url and create date -- 
 * create mapping in urls database and tags2urls junction table
 */
function createStoreNewUrl() {

    try {

        $sql = "DROP FUNCTION IF EXISTS `tcs_storeNewUrl`;";
        runSQL( $sql );

        $sql =
        "CREATE FUNCTION tcs_storeNewUrl( inUrl VARCHAR(128), inDate VARCHAR(10) )
        RETURNS INT DETERMINISTIC
        BEGIN
            DECLARE retID INT;
            SET retID = 0;
            SELECT (ID) INTO retID
                FROM urls
                WHERE url = inUrl;
        
            IF retID = 0 THEN
                INSERT INTO urls( url, postDate )
                VALUES (inUrl, inDate);
                SET retID = LAST_INSERT_ID();
            ELSE
                UPDATE urls
                SET url = inUrl, postDate = inDate
                WHERE ID = retID;
            END IF;
        
            RETURN retID;
            END;";

        runSQL( $sql );
            
        } catch (Exception $error) {
            $msg = "MySQL ERROR creating tcs_storeNewUrl(): " . $error->getMessage();
            throw new Exception( $msg );
        }
}


/*
 * must be called after tcs_storeNewUrl() so urls ID exists
 *function createNewTag( newID, eachTag ) -- store a new tag and associate it with the 
 * file ID in urls --
 */
function createCreateNewTag() {

    try {

        $sql = "DROP FUNCTION IF EXISTS `tcs_createNewTag`;";
        runSQL( $sql );

        $sql = "CREATE FUNCTION tcs_createNewTag( newID INT, eachTag VARCHAR(32) )
        RETURNS INT DETERMINISTIC
        BEGIN
            DECLARE popularity INT;
            SET popularity = 0;
            
            SELECT tcs_insertOrPopular( eachTag ) INTO @popularity;
            
            INSERT IGNORE INTO tags2urls( tags2urls.tagName, tags2urls.ID )
            VALUES ( eachTag, newID);
        
            RETURN @popularity;
            END;";

        runSQL( $sql );
            
    } catch (Exception $error) {
            $msg = "MySQL ERROR creating tcs_createNewTag(): " . $error->getMessage();
            throw new Exception( $msg );
    }
}



/*
 * Create stored functions starting from basic tools and building to top-level calls
 */
function initDB( $DB_name ) {

    // set/check important global variables
    if ( $DB_name == null ) {
        throw new Exception("DB NAME is null");
    }
    $GLOBALS['DB_NAME'] = $DB_name;
    
    $con = $GLOBALS["DB_CONNECTION"];
    if ($con == null) throw new Exception("Not connected to DB: " . $DB_name);;
    

    // else -- initialize DB with stored functions
    // that are called by subsquent php functions

    // create the main database 
    // will OVERWRITE existing DB same name
    createDB( $con, $DB_name );
    // echo "<BR>DATABASE CREATED: $DB_name";

    // create tables
    createTables();
    // echo "<BR>TABLES CREATED";

    // create morePopular stored function
    createMorePopular();
    // echo "<BR>FUNCTION tcs_morePopular CREATED";

    // create lessPopular stored function
    createLessPopular();
    // echo "<BR>FUNCTION tcs_lessPopular CREATED";
    
    // insert a new tag into tags
    //  of if it exists -- increment popularity
    createInsertOrPopular();
    // echo "<BR>FUNCTION tcs_insertOrPopular CREATED";
    
    // create mapping in urls database and tags2urls junction table
    createStoreNewUrl();
    // echo "<BR>FUNCTION tcs_storeNewUrl CREATED";

    // must be called AFTER tcs_storeNewUrl() so url ID exists
    // store a new tag and assoc it with the ID in urls
    createCreateNewTag();
    // echo "<BR>FUNCTION tcs_createNewTag CREATED";

    // set the DB config to indicate functions have been initialized
    // will throw exception if malformed sql
    $sql = "INSERT IGNORE INTO $DB_name.config (initDone) VALUES (1);";
    $result = runSQL( $sql );

    // echo "<BR>DATABASE INITIALIZED!";
}





 /*
  *
    RETURN all the IDs (and urls mapped to them) where the tagName mapped
    to that ID in tags2urls is ONE OF the members of $tags 
    passed in from the UI

    SELECT DISTINCT urls.ID, urls.url
    FROM testdb.urls as urls
    INNER JOIN testdb.tags2urls AS map ON urls.ID = map.ID
    INNER JOIN testdb.tags AS tags ON map.tagName = tags.tagName
    WHERE tags.tagName = "$eachTag" OR tags.tagName=....;
    
  * MUST have at least one tag

    // FIXME FIXME FIXME
    // 6/2022
    // CAN WE change the SQL to make search MORE restrictive -- 
    // posts must contain ALL search tags
     
  * $tags is an array of tagNames from processTags()
  *
  * --> returns array[][] -- [ID][url] 
  */
function getUrlsFromTags( $tags ) {
    if (count($tags) == 0) throw new Exception("Empty tags passed to getUrlsFromTags()");

    $theDB = $GLOBALS['DB_NAME'];
    if ($theDB == null) throw new Exception("DB_NAME global variable not set");

    // BUILD THE SQL QUERY
    $safeTag = $theDB->real_escape_string( array_shift( $tags ) );
    $allTagsSQL = "WHERE tags.tagName='$safeTag'";

    foreach ($tags as $eachTag) {
        $safeTag = $theDB->real_escape_string( $eachTag );
        $allTagsSQL .= " OR tags.tagName='$safeTag'";
    } 

    $allTagsSQL .= ";";

    // build the query -- insert db name 
    $sql = "SELECT DISTINCT urls.ID, urls.url
    FROM $theDB.urls as urls
    INNER JOIN $theDB.tags2urls AS map ON urls.ID = map.ID
    INNER JOIN $theDB.tags AS tags ON map.tagName = tags.tagName
     ";

    // add the WHERE clause we built from the tags
    $sql .= $allTagsSQL;

    // DB call
    // will throw exception if not connected to DB
    $result = runSQL( $sql );
    // process results
    $theUrls = $result -> fetch_all( MYSQLI_NUM );
    $result -> free_result();

    return $theUrls;
}
  






/*
* 3/2025
* Retrieve all blog post URLs that are tagged with ALL the given tags.
* MUST MATCH ALL TAGS
*
* This function performs an optimized SQL query using INNER JOINs—one per tag—
* to ensure that only blog posts which are associated with all search tags 
* are returned. Unlike the previous implementation, it does not rely on 
* temporary tables, improving performance and ensuring compatibility 
* with shared hosting environments like GoDaddy.
*
* @param array              $safeTags processed for SQL safety in caller   
* @return array             An array of associative arrays representing the matched URLs.
* [ [1, '/TCS_POSTS/12121.html'], [2, '/TCS_POSTS/12345.html'], ... ]
* @throws Exception         If no tags are provided, or if DB is not properly configured.
*/
function getUrls_matchAllTags($safeTags) {

    try {
        // Get database connection
        $conn = getDBConnection();
        
        // Create the base query with placeholders
        $baseQuery = "SELECT DISTINCT urls.ID, urls.url FROM urls";
        
        // Add JOIN clauses with placeholders
        $params = [];
        $types = str_repeat('s', count($safeTags)); // 's' for string parameters
        $joinClauses = '';
        
        foreach ($safeTags as $index => $tag) {
            $joinClauses .= " INNER JOIN tags2urls t{$index} ON urls.ID = t{$index}.ID AND t{$index}.tagName = ?";
            $params[] = $tag;
        }
        
        $baseQuery .= $joinClauses . " ORDER BY urls.ID DESC";
        
        // Prepare the statement
        $stmt = $conn->prepare($baseQuery);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Bind parameters if we have any
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        // Execute the statement
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        // Get result
        $result = $stmt->get_result();
        
        // Fetch all rows
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        
        // convert to simple return format format
        $theUrls = [];
        foreach ($rows as $row) {
            $theUrls[] = [(int)$row['ID'], $row['url']];  // Using correct case for ID
        }

        // Clean up
        $result->free();
        $stmt->close();
        
        return $theUrls;
        
    } catch (Exception $e) {
        logError("Error in getUrls_matchAllTags: " . $e->getMessage());
        throw $e;
    }
}









/*
*
SELECT DISTINCT theTags.tagName, theTags.popularity
FROM testdb.tags as theTags
INNER JOIN testdb.tags2urls AS map ON map.tagName = theTags.tagName
INNER JOIN testdb.urls AS urls ON urls.ID = map.ID
WHERE urls.ID = 21 or urls.ID =2 or urls.ID = 24 or urls.ID = 23 or urls.ID = 8;
*
*/
function getTagsFromUrls($theUrls) {
    if (empty($theUrls)) {
        throw new Exception("Empty urls passed to getTagsFromUrls()");
    }

    try {
        $conn = getDBConnection();
        $placeholders = str_repeat('?,', count($theUrls) - 1) . '?';
        
        $sql = "SELECT DISTINCT theTags.tagName, theTags.popularity
                FROM tags as theTags
                INNER JOIN tags2urls AS map ON map.tagName = theTags.tagName
                INNER JOIN urls AS urls ON urls.ID = map.ID
                WHERE urls.ID IN ($placeholders)";
                
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $types = str_repeat('i', count($theUrls));
        $ids = array_column($theUrls, 0);  // Get first element of each [ID, url] pair
        $stmt->bind_param($types, ...$ids);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        
        // Convert to array of [tagName, popularity] pairs
        $theTags = [];
        foreach ($rows as $row) {
            $theTags[] = [$row['tagName'], (int)$row['popularity']];
        }
        
        $result->free();
        $stmt->close();
        
        return $theTags;
       
    } catch (Exception $e) {
        logError("Error in getTagsFromUrls: " . $e->getMessage());
        throw $e;
    }
}





/*
 *  rawTags is a big string with delimiters -- # or , we have to take them out
 *  and return an array of tags
 */
function processTags( $rawTags ) {

    $finalTags = [];

    if ( ! $rawTags || $rawTags == "" ) {
        return $finalTags;
    }
   
    // Split tags by commas and trim whitespace
    $tags = array_map('trim', preg_split('/[#,]+/', $rawTags, -1, PREG_SPLIT_NO_EMPTY));

    if (count($tags) == 0) {
        logError("processTags(): NO TAGS FOUND");
        throw new Exception("Tags cannot be parsed");
    }

    foreach ($tags as $theTag) {
        if (strlen($theTag) > 0) {  // Skip empty tags
            $safeTag = SQL_sanitize($theTag);
            if (!$safeTag) {
                logError("INVALID TAG DETECTED: " . $theTag);
                continue;
            }
            $finalTags[] = $safeTag;
        }
    }

    $finalTags = array_unique($finalTags, SORT_STRING); // remove duplicates
    return $finalTags;
}



/*
 * SANITIZE database credentials
 * throws Exception if validation fails
 */
function SQL_safe( $theCred ) {

    if (! $theCred) {
        logError("ERROR EMPTY CREDENTIAL");
        throw new Exception("Empty database credential");
    }

    $theCred = trim($theCred);
    $len = strlen($theCred);

    // too short?
    if ($len < 2) {
        logError("ERROR CREDENTIAL LENGTH: " . $theCred);
        throw new Exception("Invalid credential length");
    }

    // Basic SQL injection prevention
    if (in_array($theCred, $GLOBALS['BLACKLIST'])) {
        logError("ERROR BLACKLISTED CREDENTIAL: " . $theCred);
        throw new Exception("Invalid credential");
    }

    return $theCred;
}


/*
 * SANITIZE a tag
 * look for non-alpha / suspicious chars / SQL injection
 * returns false if error detected
 */
function SQL_sanitize( $theTag ) {

    if (! $theTag) {
        logError("ERROR EMPTY STRING");
        return false;
    }

    $theTag = trim(strtolower($theTag));
    $len = strlen($theTag);

    // too short?  too long?
    if ($len < 2 || $len > $GLOBALS['MAX_TAG_LENGTH'] ) {
        logError("ERROR STRING LENGTH: " . $theTag);
        return false;
    }

    // look for non-alphanum chars
    // to prevent SQL injection attack
    // be sure not to throw away tags with spaces
    if (! ctype_alnum(str_replace(' ', '', $theTag)) ) {
        logError("ERROR NOT ALPHANUM=" . $theTag);
        return false;
    }
        
    if (in_array($theTag, $GLOBALS['BLACKLIST'])) return false;
    
    $the_db = getDBConnection();
    if ($the_db) {
        $safeTag = $the_db->real_escape_string($theTag);
    } else {
        $safeTag = htmlspecialchars($theTag, ENT_QUOTES, 'UTF-8');
    }

    return $safeTag;
}




/*
 * helper function
 */
function debug_log($msg) {
    // error_log("[TCS_DEBUG] " . $msg . "\n", 3, __DIR__ . "/tcs_debug.log");
    echo("[TCS_DEBUG] " . $msg);
}

/*
 * helper function
 */
function printTags( $tags ) {
    debug_log("PRINTING TAGS");
    
    $index = 0; 
    foreach($tags as $eachTag) {
        
        $len = strlen($eachTag);
        debug_log( "TAGS[$index]==$eachTag==" . $len );
        
        $index++;
    }
}


/*
 * CALL THIS BEFORE REMOVING URL
 * 
 * get all tags associated with a urlID
 * for each old tag
 * tesdb.tags2urls -> remove tagName,ID mapping 
 * testdb.tags -> DECREMENT popularity 
 */
function removeTags( $db_name, $urlID ) {

        $urlID = intval($urlID);
        
        // these are the OLD tags
        $sql = "SELECT * FROM $db_name.tags2urls WHERE ID=$urlID;";
        $result = runSQL( $sql );
        $oldTags = $result->fetch_all(MYSQLI_NUM); // these are the tagNames we --popularity
        $result -> free_result();

        $theDB = getDBConnection();
        $len = sizeof($oldTags);
        $i = 0;
        for ($i; $i < $len; $i++) {

            $eachTag = $oldTags[$i][0]; // the tagName
            $safeTag = $theDB->real_escape_string($eachTag);

            $sql = "DELETE FROM $db_name.tags2urls WHERE tagName='$safeTag' AND ID=$urlID";
            runSQL( $sql );
            debug_log("DELETED TAG=$eachTag");
  
            $sql = "SELECT $db_name.tcs_lessPopular('$safeTag')";  
            runSQL( $sql );       
            debug_log("LESS POPULAR=$eachTag");     
        }

}



/*
 * DELETE a urlID from urls
 * DOES NOT remove actual .html from directory -- do that first
 */
function removeUrl( $db_name, $ID ) {

    $int_ID = intval($ID);

    // delete row ID in urls 
    $sql = "DELETE FROM $db_name.urls WHERE ID=$int_ID";
    $result = runSQL( $sql );
    if (! $result) return 0;

    return 1;
}


/*
 * will throw Exception on SQL error
 */
function runSQL( $sql ) {
    
    try {
        $conn = getDBConnection();
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        return $result;
    } catch (Exception $e) {
        logError("SQL Error: " . $e->getMessage());
        throw $e;
    }
}



/*
 * do not pass me a null $date
 * will rethrow db errors as exceptions
 * 
 * rely on stored DB functions created in initDB()
 *
 * returns new unique file ID
 */
function storeToDB($filename, $tags, $date) {
    try {
        $db_name = $GLOBALS['DB_NAME'];
        $theDB = getDBConnection();
        if (!$theDB) {
            throw new Exception("No database connection");
        }

        // Start transaction for atomicity
        $theDB->begin_transaction();

        // Store URL and get ID
        $sql = "SELECT $db_name.tcs_storeNewUrl(?, ?)";
        $stmt = $theDB->prepare($sql);
        $stmt->bind_param("ss", $filename, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $newID = $result->fetch_array(MYSQLI_NUM)[0];
        $stmt->close();

        // Store tags
        $tagStmt = $theDB->prepare("SELECT $db_name.tcs_createNewTag(?, ?)");
        foreach ($tags as $tag) {
            $tagStmt->bind_param("is", $newID, $tag);
            $tagStmt->execute();
        }
        $tagStmt->close();

        // Commit all changes
        $theDB->commit();
        
        return $newID;

    } catch (Exception $e) {
        error_log("ERROR STORING TO DB: " . $e->getMessage());
        if ($theDB) {
            $theDB->rollback();
        }
        throw $e;
    }
}


    


    /*
     *
     */
    function closeConnection() {

        $con = getDBConnection();

        if ($con == null) {
            throw new Exception("Not connected to DB");
        }

        try {
            mysqli_close( $con );
        } catch (Exception $e) {
            throw new Exception("Error closing connection: " . $e->getMessage());
        }

        $GLOBALS['DB_CONNECTION'] = null;
    }
    
    
    
    /*
     * CONNECT TO DB HOST SERVER
     * username and password must have ADMIN ROOT ACCESS
     * $connect = new mysqli("localhost", "username", "password");
     */
    function connectToDB( $DB_name, $DB_url, $DB_user, $DB_pwd) {

        try{
            
            $con = new mysqli( $DB_url, $DB_user, $DB_pwd );

            if($con->connect_errno ) {
                throw new Exception("Connection failed: " . $con->connect_error);
            }

                // Enable prepared statements
            $con->set_charset("utf8mb4");
            $con->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);


        } catch (Exception $error) {
            $GLOBALS['DB_CONNECTION'] = null;
            throw new Exception("connectToDB() cannot connect to DB: " . $error->getMessage());
        }
    

        // set important global variables before returning
        //
        $GLOBALS['DB_CONNECTION'] = $con;
        
        $GLOBALS["DB_NAME"] = $DB_name;
        $GLOBALS["DB_URL"] = $DB_url;
        $GLOBALS["DB_USER"] = $DB_user;
        $GLOBALS["DB_PWD"] = $DB_pwd;

        debug_log( "CONNECTED TO DB: " . $DB_name );
    }
    
    
    
    

    /*
     *
     */
    function printDBLogin() {
      
        $db_name = $GLOBALS[ 'DB_NAME' ];
        
        $db_url = $GLOBALS[ 'DB_URL' ];
  
        $db_user = $GLOBALS[ 'DB_USER' ] ;
       
        $db_pwd = $GLOBALS[ 'DB_PWD' ];

        debug_log("DB_NAME: " . $db_name);
        debug_log("DB_URL: " . $db_url);
        debug_log("DB_USER: " . $db_user);
        debug_log("DB_PWD: " . $db_pwd);

    }

    
    
    
    /*
     *
     */
    function todaysDate() {
    
        $today = new DateTime;
        $dateStr = $today->format('Y-m-d');
        return $dateStr;
    }
    

    /*
     * Proper error logging - include timestamp, message and context
     */

    function logError($message) {
        error_log(json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'context' => []
        ]));
    }

?>