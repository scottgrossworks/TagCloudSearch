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

        if (tags.length == 0) throw new Error("Empty tags sent to TCS_searchForTags");

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

        // clear any tags left over from a previous search (e.g. one that failed)
        // otherwise they pile up and the match-ALL query returns nothing
        resetSearch();

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
    export async function TCS_getNewPosts( theResults, theUrls ) {

        if (theUrls == null) {
            throw new Error("null URLS passed to TCS_getNewPosts()");
        }

        if (theUrls.length == 0) {
            throw new Error("empty URLS passed to TCS_getNewPosts()");
        }

        // take a ticket -- cancels any posts still in flight for theResults
        const ticket = newTicket( theResults );

        // fetch ALL posts in parallel, then append them in their original order
        const newNodes = await Promise.all( theUrls.map( eachUrl => fetchPost( eachUrl[1] ) ) );

        // a newer request replaced this one while we were waiting -- drop these
        if (theResults.dataset.tcsTicket !== ticket) return;

        // clear the current blog posts (or loading placeholders) only now -- no blank flash
        theResults.innerHTML = "";
        newNodes.forEach( newNode => {
            if (newNode != null) theResults.appendChild( newNode );
        });
    }


    /*
    //  Empty theResults and cancel any posts still in flight for it
    //  returns the new ticket
    */
    export function TCS_clearPosts( theResults ) {

        theResults.innerHTML = "";
        return newTicket( theResults );
    }

    let POST_REQUESTS = 0;
    function newTicket( theResults ) {
        theResults.dataset.tcsTicket = String( ++POST_REQUESTS );
        return theResults.dataset.tcsTicket;
    }


    /*
    //  Get a single piece of html content at theURL and add it to theResults DOM element
    //
    //  append returned html to theResults
    //
    */
    export async function TCS_getSinglePost( theResults, theURL ) {

        const ticket = theResults.dataset.tcsTicket;

        const newNode = await fetchPost( theURL );

        // results were cleared while we were waiting -- drop it
        if (theResults.dataset.tcsTicket !== ticket) return;

        if (newNode != null) theResults.appendChild( newNode );
    }



    /*
    //  GET one post -- return its DOM node, or null on any error
    //  use try/catch -- do not let one 404 spoil the bunch
    //  normal HTTP caching applies (.htaccess sets how long)
    */
    async function fetchPost( theURL ) {

        try {
            const response = await fetch( theURL );
            if (! response.ok) throw new Error("HTTP " + response.status + " for " + theURL);

            let newNode = html2DOM( await response.text() );

            // add click listeners to all blog post bricks
            let theBricks = newNode.querySelectorAll('.TCS_inactive');
            Array.from( theBricks ).forEach( eachBrick => {
                eachBrick.addEventListener("click", function () {
                    TCS_clickBrick_post( eachBrick );
                });
            });

            return newNode;

        } catch (exception) {
            console.log("ERROR RECEIVED: " + exception.message);
            return null;
        }
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
    


