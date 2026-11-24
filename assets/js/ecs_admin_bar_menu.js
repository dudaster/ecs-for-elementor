function ECS_update_admin_bar_menu(items){
  
  for (var i = 0; i < items.length; i++) {
    if ( !document.getElementById( items[i].id ) ) {
 
        // Built with .attr() instead of string concatenation so no value can
        // break out of its attribute; title is escaped on the PHP side.
        var link = jQuery( '<a class="ab-item"></a>' ).attr( 'href', items[i].href ).html( items[i].title );
        jQuery( document.getElementById( items[i].parent ) ).append( jQuery( '<li></li>' ).attr( 'id', items[i].id ).append( link ) );
       
    }
  }
}
