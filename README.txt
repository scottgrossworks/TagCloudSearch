###################################################################################################
#
#  Tag Cloud Search
#
#  An effective way to organize related website content spanning multiple categories
#  and make it searchable
#
#  Ideal for someone building a website for career that contains
#  many disparate projects and endeavors
#
#  See TCS in action on my website:
#       Scott Gross
#       scottgross.works
#       scottgrossworks@gmail.com  
#
###################################################################################################

TCS is a Javascript UI component consisting of a search bar and a tag cloud containing
clickable bricks, and a system of PHP scripts for adding and searching for website content
in a backend SQL db.  Bricks are the searchable units in the TCS system.  Each piece of 
website content in the TCS system is tagged with one or more bricks corresponding to 
the bricks in the search.  

TCS step-by-step:

1)  User click-adds bricks to search bar, or types a search term, and presses the search button.
      all search bar terms are comma,delimited or separated<Enter>by<Enter>

2)  TCS client sends search tags to TCS server
      HTTP POST request
      tags=comma,delimited,list,....

3) TCS responds with two JSON-encoded lists:

    urls[] = [ID, url]

        A list of all the webpages in the TCS system with tags matching the search tags from (1)
            ID -- the unique ID for this website content in the TCS database
            url -- the URL for this html (blog post)

    tags[] = [tagName, popularity]

        A union of all the tags from all the pages in urls[] -- this provides further context for another search
        the TCS UI will display these return tags in a new tier beneath the original search tier
            tagName -- the tag text
            popularity -- how many pages in the TCS db contain this tag



QUICK START:

1) Add the UI to any HTML :

   <div
        id="TCS_main"
        search-url="http://...your-url-here.../TagCloudSearch.php"
        start-tags="the, start, tags"
        search-welcome="What can I show you? "
    ></div>

TCS_main         -required- the root DOM element for the TCS module.  DO NOT CHANGE THIS  
search_url       -required- location of the TCS server, where TCS will go for tag requests
start-tags       -optional- some high-level default tags to show when the page first opens
search-welcome   -optional- a default welcome message for the search bar


2) Configure the TCS server /php/TagCloudSearch.php

THESE CREDENTIALS allow TCS to log into mysql database and run queries
read-only -'guest' privileges are OK
does NOT have to be root / admin 

$DB_URL = "database url (localhost?) ";
$DB_NAME = "database name";
$DB_USER = "testUser";
$DB_PWD = "testPwd";


**************************************************************************************************************

Your mileage on TCS_initDB may vary -- for GoDaddy for instance, you cannot create a database programmatically.
You must use the create DB wizard to create an empty DB -- and add the root user to that DB -- 
and THEN you can run TCS_initDB and create the TCS tables and stored functions and the rest of the .html files
to add and edit TCS posts

**************************************************************************************************************


The TCS search UI can work independently, sending search requests and displaying the return tags in tiers under the
search bar.  The website author can also register callbacks with TCS to do something with the return URL data -- 
i.e. fetch the URLS in a scrolling blog format in the main body of the page.  TCS includes API tools to listen
 for new urls and submit search requests through the UI.

    ** /js/TagCloudSearch.js  

    Contains the functions you call from html to update your UI
        -- TCS_add_newUrlsListener( listener_function )  -- add a callback function to listen for new URL data
        -- An implementation of listener_function might, for instance, iterate through the list of URLS,
            execute an HTTP GET on each URL, and then append the return HTML data to the page 'blog' body 


    ** /js/TagCloudSearch_HTML.js

    Helper functions to add TCS functionality to your website
        -- Contains funcitons to submit a search through the UI automatically and trigger a registered callback upon
            receipt of return data
        -- Use these functions to program your website buttons and tabs
        -- Make the bricks in your returned blog posts clickable and searchable


    ** /js/TagCloudSearch_INIT.js

    Initialize the TCS UI component
        -- look for <div id="TCS_main" search-url="http://...your-url-here.../TagCloudSearch.php">
        -- build the search bar and search brick UI components 
        -- initialize data structures
        -- throw errors if setup doesn't work


    ** /js/TagCloudSearch_UI.js

        Callbacks for all the TCS UI components --
        -- functions for the search bricks and search button
        -- Error-checking on the search bar -- algo for adjusting the search bar if there are too many terms
        -- Submit a search to IO.js on search button click


    ** /js/TagCloudSearch_IO.js

        Submit a search to the TCS server
        -- throw Errors if something goes wrong
        -- process JSON return data (urls / tags)
        -- submit return data to registered callbacks



//
// The TCS Protocol:
//
//    TCS .JS -- sendSearchToServer()-------------------- ->  TCS server
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

** /php/TagCloudSearch.php

    The main TCS server interface
        -- ACCEPT an HTTP POST request from the TCS Javascript client
        -- calls mysql functions to query TCS db
        -- formats and returns JSON data to TCS client
        -- MUST INCLUDE read-only, guest DB login -- NOT root / admin -- cannot change DB 


** /php/TCS_dbTools.php
        -- These functions create the tables and the stored functions used by TCS queries
        -- can re-initialize a database / check if it is already initialized

        -- implements the MAIN functions for running TCS queries
            --- getTagsFromUrls()
            --- adding and removing tags / urls from DB
        
        -- must be passed ROOT / ADMIN DB login -- modifies DB

        
** /php/TCS_initDB.php
    TCS_initDB.html
        -- WEB tool for creating a TCS database using php and mysql
        -- user must enter ROOT / ADMIN login 
        -- will throw Errors if cannot create DB



** /php/TCS_post.php
    TCS_post.html

    -- WEB tool for adding a new post to the TCS database
    -- this will make the required connections in the TCS database to allow for queries by the TCS client
    -- associates a URL with its tags -- increments the popularity of the tags used by this piece of content
    -- generates a unique ID for this URL that will be returned in later JSON data
    -- calls TCS_dbTools functions
    -- USER must enter root / admin login to modify DB


** /php/TCS_edit.php
    TCS_edit.html

    -- WEB tool for editing or deleting an existing TCS post in the DB
    -- a post exitst, an is tagged with 'a', 'b', and 'c' -- you want to add 'd' and remove 'b' -- use TCS_edit
    -- calls TCS_dbTools functions
    -- USER must enter root / admin login to modify DB







