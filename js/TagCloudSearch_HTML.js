/*
// Handy helper functions to make working with TCS easier inside your HTML
//
//       TCS_searchForTags() submits a query through the UI 
//
*/
import { searchButtonListener_callback,
         resetSearch,
         addSearchText } from "./TagCloudSearch_UI.js"


    
    /*
    // Given an [] of search tags-- add them to the TCS UI and submit a search
    // simulate a click on the search button -- returned tags will go to any registered newTags_listeners
    //
    */
    export function TCS_searchForTags( tags ) {

        if (tags == null) throw new Error("null argument to TCS_searchForTags"); 
        
        if (tags.length == 0) throw new Exception("Empty tags sent to TCS_searchForTags");

        // clear search
        // resetSearch("");

        // reload search tags
        tags.forEach( eachTag => { 
            addSearchText(eachTag) }
        );
    
        // simulate push on search button
        const searchBar = document.getElementById("TCS_searchInput");
        searchButtonListener_callback(searchBar, null);
    }


    /*
    // CLEAR EVERYTHING -- reset all data strucures and UI
    //
    */
    export function TCS_resetSearch(message) {

        resetSearch();

        const searchBar = document.getElementById("TCS_searchInput");
        if (searchBar != null) {
           
            searchBar.innerHTML = (message == null) ? "" : message; 
            searchBar.classList.add("welcome");
            searchBar.focus();

          }
    }




    /*
    // Used by the scrolling UI to display new posts
    //   theResults is the DOM element to add new content to
    //   theUrls is an [] of URLS to GET html content from
    //
    // REMEMBER
    // theUrls ==  array[][] -- [ID][url] 
    //
    */
    export function TCS_getNewPosts( theResults, theUrls ) {
        
        if (theUrls == null) {
            throw new Error("null URLS passed to TCS_getNewPosts()");
        }

        if (theUrls.length == 0) {
            throw new Error("empty URLS passed to TCS_getNewPosts()");
        }

        // clear the current blog posts to make way for the new results
        theResults.innerHTML = "";

        theUrls.forEach( eachUrl => {
                TCS_getSinglePost( theResults, eachUrl[1] );
        });

    }


    /*
    //  Get a single piece of html content at theURL and add it to theResults DOM element
    //
    //  append returned html to theResults
    //
    */
    export function TCS_getSinglePost( theResults, theURL ) {

        // console.log("GET: " + theURL);
        // use try/catch -- do not let one 404 spoil the bunch
        try {
                let request = new XMLHttpRequest();

                request.open("GET", theURL, false);
                setRequestHeaders(request);
                
                request.send();

                // ....http....
                let newNode = html2DOM( request.responseText );
                theResults.appendChild( newNode );

            } catch (exception) {
                console.log("ERROR RECEIVED: " + exception.message);
            }
    }



    /*
    //
    //
    */
    function setRequestHeaders( request ) {
    
        // make sure we get FRESH copies from the web server 
        request.setRequestHeader("Cache-Control", "no-cache, no-store, max-age=0");
        // fallbacks for IE and older browsers:
        request.setRequestHeader("Expires", "Tue, 01 Jan 1980 1:00:00 GMT");
        request.setRequestHeader("Pragma", "no-cache"); // required for Chrome 
    }
    
    
    /*
    //
    //
   
    function addNewPost( theResults, newPost ) {

        let newNode = html2DOM( newPost.trim() );
        theResults.appendChild( newNode );
    }
     */
    
    /* 
    // return HTML DOM element(s) from HTML
    //
    */
    function html2DOM( html ) {
    
        let temp = document.createElement('template');
          temp.innerHTML = html.trim();

          return temp.content.firstChild; // the TCS_blogPost that wraps the entire page of content

    }
    


