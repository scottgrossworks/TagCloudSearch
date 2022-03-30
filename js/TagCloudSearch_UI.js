/*
// INIT.js builds the UI and initializes the data structures
//
//  UI.js contains the event callback code that INIT registers with TCS to handle sending search tags 
//  to the server and getting result tags back  
//    -- and then updating the TCS UI with collapsed tier/new bricks tier
//
//  [ TCS SEARCH UI inside html ] <---- [ TagCloudSearch.js ] <---- [ UI.js ] <-------> [ TagCloudSearch.php ]
//
*/

import { MAX_TAG_LENGTH,
         TOTAL_TAGS, 
         SEARCH_TAGS,
         clearSearchWelcome,
         printSearchTags, 
         } from "./TagCloudSearch_INIT.js"

import { sendSearchToServer } from "./TagCloudSearch_IO.js"

////////////////////////////////////////////////////////////////////////////




/*
 * clear the SEARCH_TAGS
 * clear the search bricks
 * reset the search bar
 */
export function resetSearch() {
   
    if (SEARCH_TAGS.length > 0) {

        SEARCH_TAGS.length = 0;

        clearSearchBricks();

        clearTierBricks();
    }
}
 


/*
// remove all bricks tiers
//
*/
export function removeTierBricks() {

    const main = document.getElementById("TCS_main");
    
    while (main.children.length > 1) { // do not remove the search tier -- only all bricks tiers
        
        main.removeChild( main.lastChild ); // pop the last child
    }

}








/*
 * listen for new tags from the server
 * collapse the current search tier
 * create new bricks tier search result new tags
 * 
 * TOTAL_TAGS = [][]
 * [0][first tier of tags added, tag, tag...]
 * [1][second tier of tags added, tag, tag...]  
 */
export const newTagsListener_callback = (rawTags) => {
  
    // did the search come up empty?
    if (rawTags == null || rawTags.length == 0) {
        return;
    }

    // update GLOBAL TOTALTAGS
    // rawTags is a [][tagName, popularity]
    // we only want to retrieve the [n][0] tagName string
    let newTags = []
    rawTags.forEach(function (eachSubarray) {
        newTags.push(eachSubarray[0])
    })
    TOTAL_TAGS.push( newTags )

    let TCS_main = document.getElementById("TCS_main");
    let lastTier = TCS_main.lastChild; // the current bricks tier

    // index of the last active tier
    let index = TCS_main.children.length - 1 - 1; // skip the search tier 

    TCS_main.removeChild(lastTier);
    let newTier = createCollapsedTier(TCS_main, index)
    TCS_main.appendChild(newTier)

    newTier = createBricksTier(index + 1, newTags)
    TCS_main.appendChild(newTier)
}



/*
// create each row underneath the search bar
//
*/
export function createTiers(TCS_main, startIndex) {
    let currentTier = startIndex
    let lastTier = TOTAL_TAGS.length - 1

    TOTAL_TAGS.forEach((theTags) => {
        let newTier = null

        if (currentTier < lastTier) {
            // collapsed tier -- no tag bricks
            newTier = createCollapsedTier(TCS_main, currentTier)
        } else {
            // bottom tier with tag bricks
            newTier = createBricksTier(currentTier, theTags)
        }

        // add it to TCS_main.children
        TCS_main.appendChild(newTier)
        currentTier++
    })
}

/*
// create collapsed tier -- no tag bricks
// <hr> with a tierIcon not pointed down
*/
function createCollapsedTier(TCS_main, tierIndex) {
    // create a TCS_tier
    let newTier = document.createElement("div")
    newTier.className = "row"
    newTier.classList.add("TCS_tier")
    newTier.id = "TCS_tier-" + tierIndex

    let theHR = document.createElement("hr")
    theHR.className = "column"
    theHR.classList.add("_9")
    theHR.classList.add("TCS_hr")

    newTier.appendChild(theHR)

    const icon = createTierIcon(false)
    newTier.appendChild(icon)

    // -- click callback
    // remove all the tiers
    // remove all the tags starting at the end until you get to the current tier
    newTier.addEventListener("click", function () {
        let removeMoreTags = true
        while (TCS_main.children.length > 1) {
            // the first child is the search UI
            let testTier = TCS_main.removeChild(TCS_main.lastChild) // pop the last child

            if (testTier.id == this.id) {
                removeMoreTags = false
            } else if (removeMoreTags) {
                TOTAL_TAGS.pop()
            }
        }

        // rebuild all the tiers
        createTiers(TCS_main, 0)

        // clear any bricks from search and reset SEARCH_TAGS
        resetSearch();

    })

    return newTier
}

/*
// create the bottom tier under the search bar
// the expanded tier containing the bricks
*/
function createBricksTier(tierIndex, tagSet) {
    // create a TCS_tier
    const newTier = document.createElement("div")
    newTier.className = "row"
    newTier.classList.add("TCS_tier")
    newTier.id = "TCS_tier-" + tierIndex

    const theUL = document.createElement("ul")
    theUL.className = "column"
    theUL.classList.add("_95")
    theUL.classList.add("TCS_bricks")
    theUL.id = "TCS_tierBricks"

    // for each brick name in the current tier
    // TOTAL_TAGS[currentTier].forEach((brickName) => {
    tagSet.forEach((brickName) => {
        // create theLI inside theUL
        let theLI = document.createElement("li")
        theLI.innerText = brickName
        theLI.classList.add("TCS_inactive") 

        theLI.addEventListener("click", function () {
            clickBrick_tier(theLI)
        })

        theUL.appendChild(theLI)
    })

    newTier.appendChild(theUL)

    // create the aleternate tier icon indicating the active bricks tier
    // let tierIcon = createTierIcon(true)
    // newTier.appendChild(tierIcon)

    return newTier
}

//
// shared by all tiers, bricks or no bricks
// rotate for bricks
//
function createTierIcon(active) {
    let tierIcon = document.createElement("div")
    tierIcon.classList.add("column")
    tierIcon.classList.add("_1")
    tierIcon.classList.add("TCS_tierIcon")


    return tierIcon
}

/////////////////////////////////////////////////////////////////////////////////////

/*
// Called on every keystroke inside the the search bar
//
*/
const SEARCH_ADJUST_RATIO = 0.96;

export function searchBarListener_callback( wrapper, theUL, searchBar, pressEvent ) {


    // let wrapper = document.getElementById("TCS_bricksWrapper");
    // let theUL = document.getElementById("TCS_searchBricks");
    let nowShowing = Number( theUL.getAttribute("nowShowing") );

    // ratio = how much of the total space is currently being used
    // we don't want to break the row or push the search icon to the right
    let ratio = ((theUL.clientWidth + searchBar.clientWidth) / wrapper.clientWidth);

    // get the specific key
    let key = pressEvent.keyCode || pressEvent.charCode;    
    //console.log({key});

    // backspace / delete
    // look to add back a brick if possible and remove the left arrow
    if (( key == 8 ) || ( key == 46 )) {
   
        if (nowShowing > 0) {
            adjustSearchBar(theUL, ratio);
        }
        
        return true;
    }



    // ISOLATE A SEARCH TERM,
    // CREATE A BRICK
    // ADJUST THE SEARCH INPUT IF NECESSARY
    // 188 == comma
    // 13 == enter
    let text = searchBar.innerHTML;
    // console.log({text});

    let trimText = "";
    if ( key == 188 ) { // COMMA
        trimText = text.replace(",","").trim();
    
    } else if (key == 13 ) {  // ENTER
        trimText = text.replaceAll("<div><br></div>", "").trim();
        
    }

    // console.log({trimText});

    if (trimText != "") { 
        
        // WE GOT ONE!

        if ( addBrickToSearch( theUL, trimText) ) {
            
            toggleTierBrick(theUL, trimText);
            
            adjustSearchBar(theUL, null);
        }

        // clear search bar text
        searchBar.innerHTML = "";
        return true;
    }

    
    
     // is the search text already too long?
     // stop adding new chars and return
     if (searchBar.innerHTML.length >= MAX_TAG_LENGTH) {
    
        pressEvent.preventDefault();
        return false;
     }

    
    // grow the search 
    // remove a brick
    // show the back arrow
    // update nowShowing
    if ( ratio >= SEARCH_ADJUST_RATIO) {
        
        adjustSearchBar(theUL, ratio);

        return true;
    }




  return true;
};



/*
// called whenever the search button is clicked
//
*/
export function searchButtonListener_callback(searchBar, event) {

    // read the value from the search bar?
    let searchTxt = searchBar.innerHTML;

    if ( (searchTxt == null) || (searchTxt == "") )  { 

        // no search text, look for bricks
        if (SEARCH_TAGS.length == 0) {
            // no bricks either? 
            // no tags inserted using addSearchText
            return
        }
        
        // else 
        // the bricks will already be added to SEARCH_TAGS
        // sendSearchToServer will read SEARCH_TAGS
        

    } else {
        // add whatever is in the search bar to SEARCH_TAGS
        addSearchText(searchTxt)
    }

    // change the cursor
    document.body.style.cursor = "wait";
    
    // SEND THE SEARCH!
    // search for everything in SEARCH_TAGS
    // async html --> php --> DB and back
    sendSearchToServer().then(() => {
        // clear any search bricks and reset SEARCH_TAGS
        resetSearch();
    })

    // re-establish focus for more typing
    searchBar.focus();
}







/////////////////////////////////////////////////////////////////////////////////////

// add a new tag to SEARCH_TAGS if it doesn't exist already
// return false if it does
export function addSearchText(theText) {
    
    if (theText == null) return false;
    
    theText = theText.trim();
    if (theText == "") return false;

    // is this a duplicate
    if (SEARCH_TAGS.indexOf(theText) > -1) {
        return false
    }

    SEARCH_TAGS.push(theText)

    return true
}


/*
 *
 */
function addBrickToSearch( theUL, tagName ) {
    
    // only add a brick if it's a new search term not already in the list
    let unique = addSearchText(tagName);
    if (!unique) {
        return false;
    }

    let li = document.createElement("li");
    li.innerHTML = tagName;
    li.classList.add("TCS_active");
    li.classList.add("TCS_searchBrick");

    li.addEventListener("click", function () {
        clickBrick_search(theUL, this);
    })

    theUL.appendChild(li);

    return true;
}

/*
 * remove a brick from the search bar and SEARCH_TAGS
 */
function removeBrickFromSearch(theUL, tagName) {

    let index = 0;
    // search by tagName
    for (index = 0; index < theUL.children.length; index++) {
        let theLI = theUL.children[index]
        if (theLI.innerText == tagName) {
            theUL.removeChild(theLI)
            break
        }
    }

    // remove name from SEARCH_TAGS array
    index = SEARCH_TAGS.indexOf(tagName)
    if (index == -1) {
        throw new Error("tier bricks are not being added to search tags")
    } else {
        SEARCH_TAGS.splice(index, 1) // 2nd parameter means remove one item only
    }
}




/*
 * Remove all search bricks if any
 */
function clearSearchBricks() {

    let theUL = document.getElementById("TCS_searchBricks");
    if (theUL == null) return;
    
    while (theUL.firstChild) {
        theUL.removeChild(theUL.firstChild)
    }
    theUL.setAttribute("nowShowing", 0);
}



/*
 * Turn off tier bricks if any
 */
function clearTierBricks() {
    let theUL = document.getElementById("TCS_tierBricks");
    if (theUL == null) return;
    
    for (let i = 0; i < theUL.children.length; i++) {
        let theLI = theUL.children[i];
        // turn it off
        theLI.classList.replace("TCS_active", "TCS_inactive");
    }
}


/*
// ADJUST THE SEARCH BAR
//
// push / pop bricks off the left side of the search bar
// so that it doesn't grow too large, start a second row,
// mess up the layout
//
// show left arrow
*/
function adjustSearchBar(theUL, ratio) {

    if (ratio == null) {
        // we must compute it
        let wrapper = theUL.parentElement;
        let searchBar = document.getElementById("TCS_searchInput");
        
        ratio = ((theUL.clientWidth + searchBar.clientWidth) / wrapper.clientWidth);
    }

    let nowShowing = Number(theUL.nowShowing);
    
    // the search input / bricks are growing
    // hide bricks on the left
    if ( ratio >= SEARCH_ADJUST_RATIO ) {

        // show left arrow
        let backArrow = document.getElementById("TCS_backArrow");
        backArrow.classList.remove("TCS_hidden");
            
        // hide nowShowing brick
        theUL.children[ nowShowing ].classList.add("TCS_hidden");
        theUL.nowShowing = ++nowShowing;


     // we are shrinking the search input / bricks
     // can we add back hidden bricks from the left  
    } else if (ratio <= SEARCH_ADJUST_RATIO) {

        if (nowShowing > 0) { // there are hidden bricks
            // add back hidden brick
            nowShowing--;
            let testBrick = theUL.children[nowShowing];
            testBrick.classList.remove("TCS_hidden");
            theUL.nowShowing = nowShowing;
        
            // disappear left arrow
            if (nowShowing == 0) {
                let backArrow = document.getElementById("TCS_backArrow");
                backArrow.classList.add("TCS_hidden");
            }
        }
    }

}


 



// called when a brick in the search bar is clicked
// turn brick inactive and remove it to the search
function clickBrick_search(theUL, theLI) {
    let tagName = theLI.innerText;

    removeBrickFromSearch(theUL, tagName);
    adjustSearchBar(theUL, null);

    toggleTierBrick(tagName);

    // let searchBar = document.getElementById("TCS_searchInput");
    let searchBar = theUL.parentElement.children[1];
    searchBar.focus();
}



// called when a brick in the tag cloud is clicked
// add / remove it from the search, depending on whether it's already there
// and toggle the color
function clickBrick_tier(theLI) {
    const theUL = document.getElementById("TCS_searchBricks")
    const searchBar = theUL.parentElement.children[1]

    clearSearchWelcome( searchBar );

    let tagName = theLI.innerText

    let brickAdded = addBrickToSearch( theUL, tagName );
    if (brickAdded) {
        adjustSearchBar(theUL, null);
    } else {
        removeBrickFromSearch( theUL, tagName );
        adjustSearchBar(theUL, null);
    }

    toggleTierBrick(tagName)

    searchBar.focus()
}






//
// toggle between TCS_active and TCS_inactive
//
function toggleTierBrick(tagName) {
    const theUL = document.getElementById("TCS_tierBricks")

    for (let i = 0; i < theUL.children.length; i++) {
        let theLI = theUL.children[i]
        let testTag = theLI.innerText
        if (testTag == tagName) {
            // FOUND IT
            let isActive = theLI.classList.contains("TCS_active")
            if (isActive) {
                // turn it off
                theLI.classList.replace("TCS_active", "TCS_inactive")
            } else {
                // turn it on
                theLI.classList.replace("TCS_inactive", "TCS_active")
            }
            break
        }
    }
}
