function hideDiv( id, anchor ) {
    if (!document.getElementById(id)) {
	return false;
    }
    if ( anchor.className == 'hide' ) {
	document.getElementById(id).style.display = 'none'; 
	anchor.title = 'Expand';
	hideText[id] = anchor.innerHTML;
	if (expandText[id] == undefined) {
	    expandText[id] = 'Expand';
	}
	anchor.innerHTML = expandText[id];
	anchor.className = 'expand';
    } else {
	document.getElementById(id).style.display = 'inline';
	anchor.title = 'Hide';
	expandText[id] = anchor.innerHTML;
	if (hideText[id] == undefined) {
	    hideText[id] = 'Hide';
	}
	anchor.innerHTML = hideText[id];
	anchor.className = 'hide';
    }
    return false;
}
var prevAnchor = false;
var hideText = [];
var expandText = [];
