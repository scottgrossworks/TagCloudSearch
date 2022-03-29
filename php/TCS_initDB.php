<!DOCTYPE html>
<html>
<body>

<!-- begin PHP -->
<?php require 'TCS_dbTools.php';



/*
 * Create a new DATABASE, TABLES, and STORED FUNCTIONS
 */
function initialize( $db_name, $db_url, $db_user, $db_pwd ) {

 
    if ( isDBConnected() && isDBInit( $db_name ) ) {
        echo "<BR>" . $GLOBALS["DB_NAME"] . " IS ALREADY INITIALIZED";
        return;
    }

    // MUST HAVE ADMIN PRIVLEGES TO CREATE DB
    // html form input data
    // some of these are mandatory -- will throw exception later if empty
    //

    if ( ($db_name == null) || 
        ($db_url == null) || 
        ($db_user == null) || 
        ($db_pwd == null) ) {

        throw new Exception("ALL 4 DB login fields must != null");
    } 

    
    // echo login credentials
    printDBLogin();

    // open connection to database
    connectToDB( $db_url, $db_user, $db_pwd );

    // create database tables and stored functions
    // stores flag in config table to indicate db initialized
    // rewrite existing table/function definitions
    initDB( $db_name );
}


/*
 * INCOMING from html
 * 
 * MUST have root / admin login to create DB
 *   
 */
    try {

        if (($_POST == null) || (count($_POST) == 0)) {
            throw new Exception("Did not receive POST request");
        }  
                
        $db_name = $_POST['db_name'];
        $db_url = $_POST['db_url'];
        $db_user  = $_POST['db_user'];
        $db_pwd= $_POST['db_pwd'];

        echo "TCS_initDB<BR>...";
        echo "<BR>Initializing new DATABASE";

        initialize( $db_name, $db_url, $db_user, $db_pwd );

    } catch (Exception $error) {
        die("<BR>TCS ERROR: " . $error->getMessage());
    }

    
echo "<BR>SUCCESS!";
?>

</body>
</html>