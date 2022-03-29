<?php

/*
 * Create a TCS database using php and mysql
 * These functions create the tables and the stored functions used by TCS queries
 *  uses ROOT / ADMIN DB login -- modifies DB
 */

/* 
 * GLOBALS
 */

$DB_NAME = null;
$DB_URL = null;
$DB_USER = null;
$DB_PWD = null;

$DB_CONNECTION = null;


/*
 */
function getDBConnection() {

    return $GLOBALS[ "DB_CONNECTION" ];
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
        
        // does the database already exist?
        $sql = "SHOW DATABASES LIKE '$DB_name';"; // must be quoted!
        if (! empty (mysqli_fetch_array( $con->query($sql) ))) {
          
                // DB exists!
                // must delete first
                $sql = "DROP DATABASE $DB_name;";   // must be unquoted!
                $success = $con->query($sql);
                if (! $success) throw new Exception("Failed to $sql : " . $mysqli->error);
                
                echo "<BR>EXECUTED: $sql";
        }

        // create DB
        $sql = "CREATE DATABASE $DB_name;";  // must be unquoted!
        $dbCreated = $con->query( $sql );
        if (! $dbCreated) throw new Exception("Failed to create DB: $DB_name");

        echo "<BR>EXECUTED: $sql";

        // make this the selected DB
        $con->select_db($DB_name);

        echo "<BR>$DB_name IS SELECTED DB";

    } catch (Exception $error) {

        throw new Exception( "Cannot create DB: " . $error->getMessage() );
    }

}


/*
 *
 */
function createTables( $DB_name ) {

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
                `postDate` date NOT NULL DEFAULT current_timestamp(),
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
 */
function createMorePopular( $DB_name ) {

    try {  
        $sql =
        "CREATE OR REPLACE FUNCTION $DB_name.tcs_morePopular (eachTag varchar(32))
        RETURNS INT DETERMINISTIC
        BEGIN
        
        DECLARE pop INT;
        SET @pop := 0;
        
        UPDATE $DB_name.tags 
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
function createLessPopular( $DB_name ) {
    //
    try {
        // functon lessPopular(tagName) -> decrements popularity col for tagName
        // keep popularity ABOVE 0
        // - returns popularity
        $sql =
        "CREATE OR REPLACE FUNCTION $DB_name.tcs_lessPopular (eachTag varchar(32))
        RETURNS INT DETERMINISTIC
        BEGIN
        
        DECLARE pop INT;
        SET @pop := 0;
        
        UPDATE $DB_name.tags 
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
function createInsertOrPopular( $DB_name ) {

    try {
            $sql =
            "CREATE OR REPLACE FUNCTION $DB_name.tcs_insertOrPopular(eachTag varchar(32))
            RETURNS INT DETERMINISTIC
            
            BEGIN
            DECLARE popularity INT;
            SET popularity = 1;
            
            IF EXISTS (SELECT * FROM $DB_name.tags WHERE tags.tagName = eachTag) 
            THEN
            SELECT $DB_name.tcs_morePopular(eachTag) INTO @popularity;
            ELSE
            INSERT INTO $DB_name.tags(tagName) VALUES (eachTag);
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
function createStoreNewUrl( $DB_name ) {

    try {

            $sql =
            "CREATE OR REPLACE FUNCTION $DB_name.tcs_storeNewUrl( inUrl VARCHAR(128), inDate VARCHAR(10) )
            RETURNS INT DETERMINISTIC
            BEGIN
                DECLARE retID INT;
                SET retID = 0;
                SELECT (ID) INTO retID
                    FROM $DB_name.urls
                    WHERE url = inUrl;
            
                IF retID = 0 THEN
                    INSERT INTO $DB_name.urls( url, postDate )
                    VALUES (inUrl, inDate);
                    SET retID = LAST_INSERT_ID();
                ELSE
                    UPDATE $DB_name.urls
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
function createCreateNewTag( $DB_name ) {

    try {
            $sql =
            "CREATE OR REPLACE FUNCTION $DB_name.tcs_createNewTag( newID INT, eachTag VARCHAR(32) )
            RETURNS INT DETERMINISTIC
            BEGIN
                DECLARE popularity INT;
                SET popularity = 0;
                
                SELECT $DB_name.tcs_insertOrPopular( eachTag ) INTO @popularity;
                
                INSERT IGNORE INTO $DB_name.tags2urls( tags2urls.tagName, tags2urls.ID )
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
    echo "<BR>DATABASE CREATED: $DB_name";

    // create tables
    createTables( $DB_name );
    echo "<BR>TABLES CREATED";

    // create morePopular stored function
    createMorePopular( $DB_name );
    echo "<BR>FUNCTION tcs_morePopular CREATED";

    // create lessPopular stored function
    createLessPopular( $DB_name );
    echo "<BR>FUNCTION tcs_lessPopular CREATED";
    
    // insert a new tag into tags
    //  of if it exists -- increment popularity
    createInsertOrPopular( $DB_name );
    echo "<BR>FUNCTION tcs_insertOrPopular CREATED";
    
    // create mapping in urls database and tags2urls junction table
    createStoreNewUrl( $DB_name );
    echo "<BR>FUNCTION tcs_storeNewUrl CREATED";

    // must be called AFTER tcs_storeNewUrl() so url ID exists
    // store a new tag and assoc it with the ID in urls
    createCreateNewTag( $DB_name );
    echo "<BR>FUNCTION tcs_createNewTag CREATED";

    // set the DB config to indicate functions have been initialized
    // will throw exception if malformed sql
    $sql = "INSERT IGNORE INTO $DB_name.config (initDone) VALUES (1);";
    $result = runSQL( $sql );
    
    echo "<BR>DATABASE INITIALIZED!";
}





 /*
  *
    SELECT DISTINCT urls.ID, urls.url
    FROM testdb.urls as urls
    INNER JOIN testdb.tags2urls AS map ON urls.ID = map.ID
    INNER JOIN testdb.tags AS tags ON map.tagName = tags.tagName
    WHERE tags.tagName = "$eachTag" OR tags.tagName=....;
    
  * MUST have at least one tag
  * $tags is an array of tagNames from processTags()
  *
  * --> returns array[][] -- [ID][url] 
  */
function getUrlsFromTags( $tags ) {

    if (count($tags) == 0) throw new Exception("Empty tags passed to getUrlsFromTags()");

    $theDB = $GLOBALS['DB_NAME'];
    if ($theDB == null) throw new Exception("DB_NAME global variable not set");


    
    // $firstTag = $tags[0];
    // echo "<BR>tags[0]=" . $firstTag . " COUNT=" . count($tags);
    $allTagsSQL = "WHERE tags.tagName='$tags[0]'";

    foreach ($tags as $eachTag) {
        $allTagsSQL .= " OR tags.tagName='$eachTag'";
    } 

    $allTagsSQL .= ";";
    //echo "<BR>GET URLS FROM TAGS: $allTagsSQL";


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
*
SELECT DISTINCT theTags.tagName, theTags.popularity
FROM testdb.tags as theTags
INNER JOIN testdb.tags2urls AS map ON map.tagName = theTags.tagName
INNER JOIN testdb.urls AS urls ON urls.ID = map.ID
WHERE urls.ID = 21 or urls.ID =2 or urls.ID = 24 or urls.ID = 23 or urls.ID = 8;
*
*/
function getTagsFromUrls( $theUrls ) {
    
    $whereSql = "WHERE urls.ID=";

    $numRows = count($theUrls);
    if ( $numRows == 0 ) { // no rows -- bad
        throw new Exception("Empty urls passed to getTagsFromUrls()");

    } else if ( $numRows == 1 ) { // only one url
        $whereSql .= $theUrls[0][0];
    
    } else { // multiple urls
        $whereSql .= $theUrls[0][0];
    
        $rowNum = 0;
        foreach ($theUrls as $eachUrl) {
            
            if ($rowNum == 0) {
                $rowNum++;
                continue;
            }

            $whereSql .= " OR urls.ID=$eachUrl[0]";
        } 
    }

    $whereSql .= ";";

    $theDB = $GLOBALS['DB_NAME'];

    // build the query -- insert the db name
    $sql = "SELECT DISTINCT theTags.tagName, theTags.popularity
    FROM $theDB.tags as theTags
    INNER JOIN $theDB.tags2urls AS map ON map.tagName = theTags.tagName
    INNER JOIN $theDB.urls AS urls ON urls.ID = map.ID
     ";

    // append the WHERE clause that we built from the urls
    // WHERE urls.ID = 21 or urls.ID =2 or ....
    $sql .= $whereSql;

    // DB call
    // will throw Exception if not connected to DB
    $result = runSQL( $sql );

    // process results
    $theTags = $result -> fetch_all(MYSQLI_NUM);
    $result -> free_result();
    
    return $theTags;
}





/*
 *
 */
function processTags( $rawTags ) {

    $tags = [];

    if ( $rawTags == null || $rawTags == "" ) {
        return $tags;
    }
    
    if ($rawTags[0] == '#') {
        $tags = explode("#", $rawTags);
    
        // does $tags also contain commas?
        if (str_contains( $rawTags, ",")) {
            $newTags = explode(",", $tags[1]);
            $tags = $newTags;
        }

    } else {
        $tags = explode(",", $rawTags);
    }

    if (count($tags) == 0) {
        throw new Exception("tags cannot be parsed");

    } else {
        while (( $tags[0] == "" ) || ($tags[0] == " ")) {
            array_shift($tags);
        }

        $index = 0;
        foreach($tags as $eachTag) {
            $tags[ $index ] = trim($eachTag);
            $index++;
        }
        
        asort( $tags, SORT_STRING);
    }

   return $tags;
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

        // these are the OLD tags
        $sql = "SELECT * FROM $db_name.tags2urls WHERE ID=$urlID;";
        $result = runSQL( $sql );
        $oldTags = $result->fetch_all(MYSQLI_NUM); // these are the tagNames we --popularity
        $result -> free_result();

        $len = sizeof($oldTags);
        $i = 0;
        for ($i; $i < $len; $i++) {

            $eachTag = $oldTags[$i][0]; // the tagName

            $sql = "DELETE FROM $db_name.tags2urls WHERE tagName='$eachTag' AND ID=$urlID";
            runSQL( $sql );
            echo "<BR>DELETED TAG=$eachTag";
  
            $sql = "SELECT $db_name.tcs_lessPopular('$eachTag')";  
            runSQL( $sql );       
            echo "<BR>LESS POPULAR=$eachTag";     
        }

}



/*
 * DELETE a urlID from urls
 * DOES NOT remove actual .html from directory -- do that first
 */
function removeUrl( $db_name, $ID ) {

    // delete row ID in urls 
    $sql = "DELETE FROM $db_name.urls WHERE ID=$ID";
    $result = runSQL( $sql );
    if (! $result) return 0;

    return 1;
}


/*
 * will throw Exception on SQL error
 */
function runSQL( $sql ) {
    
    $con = $GLOBALS['DB_CONNECTION'];
    if ($con == null) throw new Exception("No DB connection");

    $result = $con->query( $sql );

    // a zero result will indicate an error
    // NOT zero rows affected
    if ( (! $result) || ($result == null) || ($con->error)  ) {
        $errStr = "ERROR in SQL: " . $sql . " -> " . $con->error;
        throw new Exception( $errStr );
    }
    
    return $result;
}


/*
 * do not pass me a null $date
 * will rethrow db errors as exceptions
 * 
 * rely on stored DB functions created in initDB()
 *
 */
function storeToDB( $filename, $tags, $date ) {

    $db_name = $GLOBALS['DB_NAME'];

    /*
     * will throw an Exception if not connected to DB
     * creates new row in urls table or updates existing info
     * returns unique ID for url 
     */
    $sql = "SELECT $db_name.tcs_storeNewUrl('$filename','$date')";

    $result = runSQL( $sql );
   
    $newID = $result->fetch_array(MYSQLI_NUM)[0];

    $result -> free_result();


     /*
      * for each tag to be assoc. with filename .html
      * tags: create new tag or increment popularity
      * tags2urls: create new mapping tag --> ID from tcs_storeNewUrl
      */
    foreach ($tags as $eachTag) {
        $sql = "SELECT $db_name.tcs_createNewTag($newID,'$eachTag')";
        runSQL( $sql );
    }

}


    


    /*
     *
     */
    function closeConnection() {

        $con = $GLOBALS['DB_CONNECTION'];

        if ($con == null) {
            throw new Exception("Not connected to DB");
        }

        try {
            mysqli_close( $con );
        } catch (Exception $e) {
            throw new Exception("Error closing connection: " + $e->getMessage());
        }

        $GLOBALS['DB_CONNECTION'] = null;
    }
    
    
    
    /*
     * CONNECT TO DB HOST SERVER
     * username and password must have ADMIN ROOT ACCESS
     * $connect = new mysqli("localhost", "username", "password");
     */
    function connectToDB( $DB_url, $DB_user, $DB_pwd) {

        try{
            
            $con = new mysqli( $DB_url, $DB_user, $DB_pwd );

            if($con->connect_errno ) {
                throw new Exception("Connection failed: " . $con->connect_error);
            }

    
        } catch (Exception $error) {
            $GLOBALS['DB_CONNECTION'] = null;
            throw new Exception("Cannot connect to DB: " . $error->getMessage());
        }
            
        // set important global variables before returning
        //
        $GLOBALS['DB_CONNECTION'] = $con;
        $GLOBALS["DB_URL"] = $DB_url;
        $GLOBALS["DB_USER"] = $DB_user;
        $GLOBALS["DB_PWD"] = $DB_pwd;

    }
    
    
    
    

    /*
     *
     */
    function printDBLogin() {
      
        $db_name = $GLOBALS[ 'DB_NAME' ];
        
        $db_url = $GLOBALS[ 'DB_URL' ];
  
        $db_user = $GLOBALS['DB_USER'];
       
        $db_pwd = $GLOBALS['DB_PWD'];
    
        echo "<BR>DB_NAME: " . $db_name;
        echo "<BR>DB_URL: " . $db_url;
        echo "<BR>DB_USER: " . $db_user;
        echo "<BR>DB_PWD: " . $db_pwd;

    }

    
    
    
    /*
     *
     */
    function todaysDate() {
    
        $today = new DateTime;
        $dateStr = $today->format('Y-m-d');
        return $dateStr;
    }
    

?>