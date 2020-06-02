define([
        "jquery"
    ], function($){
        "use strict";
        return function(config, element) {
            console.log(config.allowed_sales,config.cart_entries,config.cart_item_id);
            var qty_allowed = config.allowed_sales;
            var cart_items = config.cart_entries;
            $('#qty').change(function(){
                var qty_selected = this.value;
                if(qty_selected+cart_items>qty_allowed){
                    this.value = qty_allowed-cart_items;
                    $('#LimitSalesWarning1').show();
                    setInterval(function () {
                        $('#LimitSalesWarning1').hide();
                    },10000);
                }
                else $('#LimitSalesWarning1').hide();
            });
            $('#product-addtocart-button').click(function(e){
                var qty_selected = parseInt($('#qty').val());
                console.log(cart_items,qty_selected,qty_allowed,qty_selected+cart_items>qty_allowed);
                if(qty_selected+cart_items>qty_allowed){
                    $('#LimitSalesWarning2').show();
                    setInterval(function () {
                        $('#LimitSalesWarning2').hide();
                    },10000);
                    e.preventDefault();
                }
                else {
                    cart_items += qty_selected;
                    $('#LimitSalesWarning2').hide();
                }
            });
        }
    }
)
