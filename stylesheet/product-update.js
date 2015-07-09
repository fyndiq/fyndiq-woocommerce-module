jQuery(document).on('click', '#fyndiq-product-update', function(){
    var button = jQuery(this);
    var beforetext = button.text();
    button.text("Loading..");
    jQuery.ajax({
        url: wordpressurl + "/?fyndiq_products"
    }).done(function() {
        button.text("Done").delay(1400).queue(function(nxt) {
            jQuery(this).text(beforetext);
            nxt();
        });
    });
});
