/*
// Handy helper functions to make working with TCS easier inside your HTML
//
//       TCS_searchForTags() submits a query through the UI 
//
*/
import { searchButtonListener_callback,
         resetSearch,
         clickBrick_tier,
         addSearchText,
      } from "./TagCloudSearch_UI.js"




    /*
    // A brick inside a blog post is clicked
    // treat it like a tier brick
    */
   export function TCS_clickBrick_post( theLI ) {
        // pass the click on to the search tier and click that brick too
        // this will call togglePageBricks() on ALL the rest of the
        // occurrences of the brick on the page
        clickBrick_tier( theLI );
   }




   /*
   // helper functions for integrating blog post bricks with search bricks
   //
   */
   export function togglePageBricks( tagText ) {

        let theULs = document.querySelectorAll('.TCS_bricks');
        if (theULs == null) return; // should NOT happen;
        
        Array.from( theULs ).forEach( eachUL => { 

            if (eachUL.id == "TCS_searchBricks") return; // skip the search bricks
            if (eachUL.id == "TCS_tierBricks") return; // skip the tier bricks

            // THESE are the blog post bricks
            let numBricks = eachUL.children.length;
            let i = 0;
            for (i; i < numBricks; i++) {
                
                let theBrick = eachUL.children[ i ];

                 // we have a matching brick to the one clicked
                if (theBrick.innerText == tagText) {
                    // console.log("MATCH=" + theBrick.innerText + "==" + tagText + "->" + (theBrick.innerText == tagText));
                    toggleBrickColor( theBrick );
                    break;
                }
            }

        });
   }



   function toggleBrickColor( theBrick ) {
    // toggle the brick color
    let isActive = theBrick.classList.contains("TCS_active")
        if (isActive) {
            // console.log("turning it off");
            theBrick.classList.replace("TCS_active", "TCS_inactive")
        } else {
            // console.log("turning it on");
            theBrick.classList.replace("TCS_inactive", "TCS_active")
        }
   }



    /*
    // Given an [] of search tags-- add them to the TCS UI and submit a search
    // simulate a click on the search button -- returned tags will go to any registered newTags_listeners
    //
    */
    export function TCS_searchForTags( tags ) {

        if (tags == null) throw new Error("null argument to TCS_searchForTags");

        if (tags.length == 0) throw new Exception("Empty tags sent to TCS_searchForTags");

        // Ensure the search column is visible before performing search
        // This fixes the issue where masthead links don't show results when search column is hidden
        let searchColumn = document.getElementById("search_column");
        if (searchColumn != null && searchColumn.classList.contains("TCS_hidden")) {
            searchColumn.classList.replace("TCS_hidden", "TCS_showing");
        }

        // Clear previous results to provide a clean slate for new search
        let resultsWrapper = document.getElementById("results_wrapper");
        if (resultsWrapper != null) {
            resultsWrapper.innerHTML = "";
        }

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
            

                // add click listeners to all blog post bricks
                let theBricks = newNode.querySelectorAll('.TCS_inactive');
                Array.from( theBricks ).forEach( eachBrick => { 
        
                    eachBrick.addEventListener("click", function () {
                        TCS_clickBrick_post( eachBrick );              
                     });
                });

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
    


