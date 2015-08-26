jQuery(document).on('click', '#fyndiq-order-import', function(){
    var button = jQuery(this);
    var beforetext = button.text();
    button.text(trans_loading);
    jQuery.ajax({
       url: wordpressurl + '/?fyndiq_orders'
   }).success(function() {
       button.text(trans_done).delay(1400).queue(function(nxt) {
           jQuery(this).text(beforetext);
           location.reload();
       });
    }).fail(function() {
        button.text(trans_error).delay(1400).queue(function(nxt) {
            jQuery(this).text(beforetext);
            nxt();
        });
    });
});
