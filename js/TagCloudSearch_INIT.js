/*
// Build the TCS UI -- the search bar / search bricks and the tiers of bricks below
// --> see TCS_components.css for structure
//    TCS_main is the root DOM Node -- this will build the TCS search UI
//              <div id="TCS_main" search-url="http://...../TagCloudSearch.php"></div>
//
// registers newTagsListener_callback with TCS to 
//
*/
import { TCS_add_newTagsListener } from "./TagCloudSearch.js";

import {
    newTagsListener_callback,
    searchBarListener_callback,
    searchButtonListener_callback,
    createTiers,
    removeTierBricks,
} from "./TagCloudSearch_UI.js";

import { validateTAGS } from "./TagCloudSearch_IO.js";

/*
// GLOBAL DATA STRUCTURES
//
*/
export const MAX_TAG_LENGTH = 20; // chars 

/*
* TOTAL_TAGS = [][]
* [0][first tier of tags added, tag, tag...]
* [1][second tier of tags added, tag, tag...]  
*/
export let TOTAL_TAGS = [];

/*
 * [] = CURRENT search tags in the search bar
 */
export let SEARCH_TAGS = [];

export const NEW_TAGS_LISTENERS = [];
export const NEW_URLS_LISTENERS = [];

export let SEARCH_URL = null;

/*
// 
//
*/
export function TCS_init() {

    // find main root div defined in the html -- this is the container element
    // for the entire tag cloud search component tree
    const TCS_main = document.getElementById("TCS_main")

    // allow 1 and only 1 element class=TCS_main
    if (TCS_main == null) {
        throw new Error("Cannot create TagCloudSearch - must declare 1 'TCS_main' in your .html")
    }

    // does the UI already exist?
    if (TCS_main.children.length > 0) {
        // we are RE-INITIALIZING
        
        TCS_re_init(TCS_main);
         
        let searchBar = document.getElementById("TCS_searchInput");

        setSearchWelcome(searchBar);
        searchBar.focus();
    
    } else {

        // else -- build the UI from scratch
        //

        //
        // look for search-url html attribute and set SEARCH_URL
        //
        SEARCH_URL = TCS_main.getAttribute("search-url");
        if (SEARCH_URL == null) {
            throw new Error("TCS_main must include a 'search-url' attribute");
        } else if (!SEARCH_URL.startsWith("http://")) {
            throw new Error("search-url must start with 'http://'");
        }

        initTags(TCS_main);

        let searchBar = initSearchBar(TCS_main);
        if (searchBar == null) throw new Error("Cannot create TCS search bar"); 
            
        createTiers(TCS_main, 0);

        // set myself as a listener for new search tags
        TCS_add_newTagsListener(newTagsListener_callback);

        
        // searchBar.focus();
        setSearchWelcome(searchBar);


    }
 
}


/*
// RE-initialize everything 
//    -- after a reload / refresh
*/
export function TCS_re_init( TCS_main ) {
    
    // clear any search bricks
    let theUL = document.getElementById("TCS_searchBricks");
    if (theUL != null) theUL.innerHTML = "";

    // clear the search input
    let searchBar = document.getElementById("TCS_searchInput");
    if (searchBar != null) searchBar.innerHTML = "";


    // clear the tier bricks
    removeTierBricks();

    // re-init data structs
    TOTAL_TAGS = [];
    SEARCH_TAGS = [];
    initTags(TCS_main);

    // create the first search tier
    createTiers(TCS_main, 0);   
}



/*
// initialize the global [] that holds the tags data from the server
// will throw error if html attributes are incorrectly formatted
*/
function initTags(TCS_main) {
    let startTagsString = TCS_main.getAttribute("start-tags")
    if (startTagsString != null && startTagsString != "") {
        try {
            validateTAGS(startTagsString) // will throw an error
        } catch (parseError) {
            throw new Error("Cannot parse 'start-tags' attribute")
        }

        TOTAL_TAGS[0] = startTagsString.split(",")
    }
}

/*
// BUILD THE SEARCH BAR / BUTTON / START BRICKS
//
// div -- TCS_main
//  - div TCS_tier
//    -- wrapper
//      - ul -- TCS_bricks
//      - input -- TCS_search
//   - icon -- svg
*/
function initSearchBar(TCS_main) {

    const newTier = document.createElement("div");
    newTier.className = "row";
    newTier.classList.add("TCS_tier");
    newTier.id = "TCS_searchTier";
    TCS_main.appendChild(newTier);


    const backArrow = document.createElement("div");
    backArrow.classList.add("column");
    backArrow.classList.add("_05");
    backArrow.classList.add("TCS_hidden");
    backArrow.id = "TCS_backArrow";
    newTier.appendChild( backArrow );


    const wrapper = document.createElement("div");
    wrapper.classList.add("column");
    wrapper.classList.add("_85");
    wrapper.id="TCS_bricksWrapper";
    newTier.appendChild( wrapper );

    const theUL = document.createElement("ul");
    theUL.classList.add("TCS_bricks");
    theUL.id = "TCS_searchBricks";
    theUL.nowShowing = 0;
    wrapper.appendChild( theUL );

    const searchBar = document.createElement("span")
    searchBar.classList.add("input");
    searchBar.id = "TCS_searchInput";
    searchBar.type = "text";
    searchBar.contentEditable = "true";
    wrapper.appendChild( searchBar );


    const searchButton = document.createElement("button");
    searchButton.classList.add("column");
    searchButton.classList.add("_1");
    searchButton.classList.add("TCS_searchButton");
    searchButton.id = "TCS_searchBarButton";
    newTier.appendChild( searchButton );

   
    // BLANK SPACE AROUND SEARCH BAR
    //
    newTier.addEventListener("click", function (event) {
        clearSearchWelcome(searchBar);
        searchBar.focus();
    })

    
    // SEARCH BAR KEY DOWN
    //
    searchBar.addEventListener("keydown", (pressEvent) => {
        let key = pressEvent.keyCode || pressEvent.charCode;
        
        // Prevent default action for Enter to avoid inserting a new line
        if (key === 13) {
            pressEvent.preventDefault();
        }
        
        // Existing check for maximum tag length
        if (searchBar.innerHTML.length >= MAX_TAG_LENGTH) {
            if ((key == 8) || (key == 46)) {  // allow backspace / delete
                return true;
            } else {
                pressEvent.preventDefault();
                return false;
            }
        }
        return true;
    });


    // SEARCH BAR KEY UP
    //
    searchBar.addEventListener("keyup", function (event) {

         // this gets called on EVERY keystroke
         // check for <enter> or ',' to delineate tag
         // add tag to SEARCH_LIST
         //
        clearSearchWelcome(searchBar);
        
        return searchBarListener_callback(wrapper, theUL, searchBar, event)
    });

    // SEARCH BAR CLICK
    //
    searchBar.addEventListener("click", function (event) {
        
        clearSearchWelcome(searchBar);
        searchBar.focus();
        return true;
    });

    // SEARCH BUTTON
    //
    searchButton.addEventListener("click", function (event) {
        
        clearSearchWelcome(searchBar);
        searchButtonListener_callback(searchBar, event);
        searchBar.focus();
        return true;
    });

    return searchBar;
}

/*
 * look for search-welcome attribute of TagCloudSearch tag
 */
function setSearchWelcome(searchBar) {

    // is there a default search input value?
    let searchWelcome = TCS_main.getAttribute("search-welcome")
    if (searchWelcome != null && searchWelcome != "") {
        searchBar.classList.add("welcome");
        searchBar.innerHTML = searchWelcome;
    }
}







/*
 *
 */
export function clearSearchWelcome(searchBar) {

    if (searchBar.classList.contains("welcome")) {       
        searchBar.classList.remove("welcome");
        searchBar.innerHTML = "";
    }
}

/////////////////////////// DEBUG UTILS ////////////////////////////

export function printTags(msg) {
    if (msg != null && msg != "") {
        console.log(msg)
    }

    let s
    let tagNum = 0
    TOTAL_TAGS.forEach((theTags) => {
        s = `[${tagNum}]-`
        theTags.forEach((tag) => {
            s += tag + ","
        })

        console.log(s)
        tagNum++
    })
}

// DEBUG DEBUG DEBUG
export function printSearchTags(msg) {
    if (msg != null && msg != "") {
        console.log(msg)
    }

    let s = ""

    SEARCH_TAGS.forEach((tag) => {
        s += tag + ","
    })

    console.log(s)
}
