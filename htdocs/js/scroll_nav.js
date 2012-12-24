// 
// From:
// 
// http://code.stephenmorley.org/about-this-site/copyright/
/* Handles the page being scrolled by ensuring the navigation is always in
 * view.
 */
function handleScroll(height, item_id){

  if (height == undefined) {
      height = 104;
  }
  
  if (item_id == undefined) {
      item_id = 'sidebar_left';
  }
    
  // check that this is a relatively modern browser
  if (window.XMLHttpRequest){

    // determine the distance scrolled down the page
    var offset = window.pageYOffset
               ? window.pageYOffset
               : document.documentElement.scrollTop;

    // set the appropriate class on the navigation
    document.getElementById(item_id).className =
        (offset > height ? 'fixed' : '');

  }

}

// add the scroll event listener
if (window.addEventListener){
  window.addEventListener('scroll', handleScroll, false);
}else{
  window.attachEvent('onscroll', handleScroll);
}