/*
 * This is the TagCloudSearch API
 *
 * When TCS submits a search the server responds with JSON-encoded URL / tag data
 * Contains the functions you call from html to update your UI with the return data
 *
 */
 
import { SEARCH_TAGS,
         NEW_TAGS_LISTENERS,
         NEW_URLS_LISTENERS,
         TCS_init,
} from "./TagCloudSearch_INIT.js";





/*
// API - return the search tags as [tag1, tag2, tag3]
*/
export function TCS_getSearchTags() {
    return SEARCH_TAGS;
}


/*
// API - register a function to listen for new tags incoming
// from the server 
//
//  Each function should take an Array Object:  func( [Array] )
*/
export function TCS_add_newTagsListener( listener_function ) {
    
    if (listener_function == null) throw new Error("Cannot add null tags listener function");
    
    if (typeof listener_function !== 'function') throw new Error("Argument must be typeof function");
    
    NEW_TAGS_LISTENERS.push( listener_function );
} 
// remove a listener function
export function TCS_remove_newTagsListener( listener_function ) {
    
    if (listener_function == null) throw new Error("Cannot remove null tags listener function");
    
    NEW_TAGS_LISTENERS.remove( listener_function );
} 

/*
// Removes last function added
// returns it
*/
export function TCS_remove_lastTagsListener() {
   
    let lastAdded = NEW_TAGS_LISTENERS.pop();
    return lastAdded;
}


/*
// API - register a function to listen for new urls incoming
// from the server 
//
//   value - listener function
//
*/
export function TCS_add_newUrlsListener( listener_function ) {
    
    if (listener_function == null) throw new Error("Cannot add null urls listener function");

    if (typeof listener_function != 'function') throw new Error("Argument must be typeof function");

    NEW_URLS_LISTENERS.push( listener_function );
} 

/*
// Removes last function added
// returns it
*/
export function TCS_remove_lastUrlsListener() {
   
    let lastAdded = NEW_URLS_LISTENERS.pop();
    return lastAdded;
}


/*
// Removes any matching listener function from the list
// that gets notified when new URLS come in from the TCS server
*/
export function TCS_remove_newUrlsListener( listener_function ) {
   
    if (listener_function == null) throw new Error("Cannot remove null urls listener function");

    NEW_URLS_LISTENERS.forEach( arrayRow => {
        if (arrayRow == listener_function) {
 
            // remove all instances
            removeFromArray( NEW_URLS_LISTENERS, arrayRow );
            return;
        }
    });
}





//
// helper function -- remove all instances of value from array
//
function removeFromArray(arr, value) {
  var i = 0;
  while (i < arr.length) {
    if (arr[i] === value) {
      arr.splice(i, 1);
    } else {
      ++i;
    }
  }
  return arr;
}



/*
//////////////////////////////////////////////////////////////////////////////////
//
// Called from HTML
//
window.addEventListener('load', (event) => {
});
*/

window.addEventListener('DOMContentLoaded', (event) => {
    TCS_init();
    window.sessionStorage.setItem("tcs_need_refresh", "false");
});

/*
 * Important listener
 * pageShow event will get called even when user hits back arrow in browser
 * and then forward arrow to return to page -- in this case
 * reinitialize the search bar
 */
window.addEventListener('pageshow', (event) => {
    
    let need_refresh = window.sessionStorage.getItem("tcs_need_refresh");

    if ( need_refresh == "true" ) {
        TCS_init();
        // do not reset the need_refresh flag
    } else {
            window.sessionStorage.setItem("tcs_need_refresh", "true");
    }
});
