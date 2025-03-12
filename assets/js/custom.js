             
    jQuery(document).ready(function() {
        // Initially open only the first accordion content
        jQuery(".ai_accordion-content").not(':first').removeClass("show");  // Close all except the first
        jQuery(".ai_accordion-header").not(':first').children(".arrow").removeClass("ai_rotated"); // Reset arrow direction for all except the first
    
        // When the header is clicked, toggle the accordion content and arrow direction
        jQuery(".ai_accordion-header").click(function() {
        // Close other accordion items
        jQuery(".ai_accordion-content").not(jQuery(this).next()).removeClass("show");
        jQuery(".ai_accordion-header .arrow").not(jQuery(this).children(".arrow")).removeClass("ai_rotated");
        
        // Toggle the clicked accordion content
        jQuery(this).next(".ai_accordion-content").toggleClass("show");
        
        // Toggle the arrow direction
        jQuery(this).children(".arrow").toggleClass("ai_rotated");
        });
    });
   