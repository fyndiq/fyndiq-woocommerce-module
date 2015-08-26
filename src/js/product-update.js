jQuery(document).on('click', '#fyndiq-product-update', function(){
    var button = jQuery(this);
    var beforetext = button.text();
    button.text('Loading..');
    jQuery.ajax({
        url: wordpressurl + "/?fyndiq_products"
    }).success(function() {
        button.text("Done").delay(1400).queue(function(nxt) {
            jQuery(this).text(beforetext);
            location.reload();
        });
    }).fail(function() {
        button.text('Error!').delay(1400).queue(function(nxt) {
            jQuery(this).text(beforetext);
            nxt();
        });
    });
});
