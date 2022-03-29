/*
//
// The TCS Protocol:
//
//   -- .JS -- sendSearchToServer()-------------------- ->
//       SEARCH_TAGS --> comma,delimited,list
//       HTTP POST
//         body: tcs_tags=comma,delimited,list 
//
//       <- ------------------------------
//       JSON data
//          2 JSON objects -- tags, urls
//               tags[] = [ tagName, popularity ]
//               urls[] = [ ID , url ]
//
//             passed to each listener callback function
//
*/


import {  MAX_TAG_LENGTH,
          NEW_TAGS_LISTENERS,
          NEW_URLS_LISTENERS,

          SEARCH_TAGS,
          SEARCH_URL,

       } from "./TagCloudSearch_INIT.js";

/*
//

// use async api
// send search -- wait for reply data in a function with a callback
// callback - parse data
// separate URLS from new tags
// send URLS to main page
// rebuild tiers
//
// implement a callback function that delivers the URLS upon request from main page
//
*/
export async function sendSearchToServer() {

    if (SEARCH_TAGS.length == 0) throw new Error("Cannot send empty SEARCH_TAGS to server");

    let response = null;
    let formData = SEARCH_TAGS.join(',');
    let returnData = null;


    try {

        // ASYNC wait for HTTP response
        response = await fetch( SEARCH_URL, {
            method: 'POST',
            headers: { 'Content-type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'include',
            body: 'tcs_tags=' + formData
        });

            // parse using JSON
            // looking for json errors? try response.text() for raw data
            returnData  = await response.json();

            // check for null data
            if (returnData == null) {
                throw new Error("no JSON data received from server");
            }

    } catch ( fetchError ) {

        if (fetchError == null) {
            console.log("TCS ERROR in sendSearchToServer: fetchError == null");
            console.log("RESPONSE=" + response.body);

        } else {
            console.log("TCS ERROR in sendSearchToServer: " + SEARCH_URL);
            console.log("RESPONSE=" + response.body);
            console.error( fetchError );
        }
        return;
    }

    // OTHERWISE....WE GOT RETURN DATA.....
    // returnData attributes should be arrays -- even empty ones -- not nulls
    //
    // tcs_tags = array[] -- [tagName , popularity]
    // tcs_urls ==  array[] -- [ID , url] 
    //
    // OPTIMIZATION:  make this round-trip faster -- get it right in the php and skip the validate
    /*
    try {
        validateTAGS( returnData.tcs_tags );
        validateURLS( returnData.tcs_urls );
    } catch (parseError) {
        console.error("Cannot parse return data: " + parseError.message);
        return;
    }
    */

    /*
    //
    // CALLBACKS
    // call each registered callback function with the new tags / urls
    // don't let any individual error derail the group
    //
    */

    /*
     * GOT new TAGS from server
     */
    NEW_TAGS_LISTENERS.forEach( (listener) => {
        // pass the new tags to each registered callback     
        try {
            listener( returnData.tcs_tags );
      
        } catch (listenerError) {
            console.error("Error in tags listener function: " + listener);
            console.log("ERROR: " + listenerError.message);
        }
    });

    
    /*
     * GOT new URLS from server
     */
    NEW_URLS_LISTENERS.forEach( (listener) => {

        // pass the new urls to each registered callback 
        try {
            listener( returnData.tcs_urls );
      
        } catch (listenerError) {
            console.error("ERROR in url listener function: " + listener);
            console.log("ERROR: " + listenerError.message);
        }
    });

  
    // change the cursor back
    document.body.style.cursor = "default";

}



/*
// Check each tag to make sure it's correctly formatted, and won't cause trouble
// in the UI later on
// can be passed String "a,b,c,d" or Array object [ a, b, c, d ]
*/
export function validateTAGS( theTags ) {

    if (theTags == null) throw new Error("Received null tags");

    if (typeof theTags === "array") {

        theTags.forEach((tag) => {

            // tag too long
            if (tag.length > MAX_TAG_LENGTH) throw new Error("Tag(s) exceed max tag length: " + MAX_TAG_LENGTH);
            
            // cannot contain a ','
            if (tag.indexOf(',') >= 0) throw new Error("Tag cannot contain a comma");

        });

    } else if (typeof theTags === "string") {
        
        let theArray = theTags.split(",");
        validateTAGS( theArray );      
    }
}



/*
//  Check each of the 'URL' strings to make sure each is properly formatted and won't 
//  cause trouble later on
*/
export function validateURLS( theURLS ) {
    // FIXME FIXME FIXME -- no good criteria for this -- doesn't have to start with http:// 
}

