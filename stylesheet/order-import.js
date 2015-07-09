jQuery(document).on('click', '#fyndiq-order-import', function(){
        var button = jQuery(this);
        button.text("Loading..");
        jQuery.ajax({
           url: wordpressurl + "/?fyndiq_orders"
        }).done(function() {
           button.text("Done").delay(1400).queue(function(nxt) {
               jQuery(this).text("Import From Fyndiq");
               nxt();
           });
        });
    });
