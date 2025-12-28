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
//           TCS protocol in PHP
//
            if everything is OK
                $response = array(
                    'tcs_status' => true,
                    'tcs_message' => 'OK',
                    'tcs_tags' => $theTags,
                    'tcs_urls' => $theUrls
                );
//
            if there is an error
                $response = array( 
                'tcs_status' => false,
                'tcs_message' => $errStr
                );
*/
export async function sendSearchToServer() {
    if (SEARCH_TAGS.length == 0) throw new Error("Cannot send empty SEARCH_TAGS to server");

    // Lowercase all tags before sending to server (database stores tags in lowercase)
    let lowercaseTags = SEARCH_TAGS.map(tag => tag.toLowerCase());
    let formData = lowercaseTags.join(',');

    try {
        const response = await fetch( SEARCH_URL, {
            method: 'POST',
            headers: { 'Content-type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'include',
            body: 'tcs_tags=' + formData
        });


    
        // Get raw response as text FIRST 
        const rawText = await response.text();
        // console.log("RAW RESPONSE FROM SERVER:" , rawText);
        
        // THEN parse JSON
        let returnData;
        try {
            returnData = JSON.parse(rawText);
        } catch (parseError) {
            console.error("JSON PARSING ERROR:", parseError, "RAW RESPONSE:", rawText);
            throw new Error("JSON parsing error: " + parseError.message);
        }

        if (!returnData) {
            throw new Error("no JSON data received from server");
        }

        if (returnData.tcs_status === false) {
            throw new Error("ERROR returned from TagCloudSearch.php: " + returnData.tcs_message);
        }

        // CALLBACKS
        NEW_TAGS_LISTENERS.forEach((listener) => {
            try {
                listener(returnData.tcs_tags);
            } catch (listenerError) {
                console.error("Error in tags listener:", listenerError);
            }
        });

        NEW_URLS_LISTENERS.forEach((listener) => {
            try {
                listener(returnData.tcs_urls);
            } catch (listenerError) {
                console.error("Error in urls listener:", listenerError);
            }
        });

    } catch (fetchError) {
        console.error("TCS ERROR in sendSearchToServer:", fetchError);
    }
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

