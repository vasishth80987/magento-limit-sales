define([
        "jquery"
    ], function($){
        "use strict";
        return function(config, element) {
            var qty_allowed = config.allowed_sales;
            var cart_items = config.cart_entries;
            $('#qty').change(function(){
                var qty_selected = parseInt(this.value);
                console.log(qty_allowed,cart_items,qty_selected);
                if(qty_selected+cart_items>qty_allowed){
                    this.value = Math.max(0,qty_allowed-cart_items);
                    $('#LimitSalesWarning1').show();
                    setInterval(function () {
                        $('#LimitSalesWarning1').hide();
                    },10000);
                }
                else $('#LimitSalesWarning1').hide();
            });
            $('#product-addtocart-button').click(function(e){
                var qty_selected = parseInt($('#qty').val());
                var swatches = $(".swatch-attribute");
                var swatch_selected = $(".swatch-attribute").map(function(){return $(this).attr("option-selected");}).get();
                if(swatch_selected.length!=swatches.length) return;
                console.log(cart_items,qty_selected,qty_allowed,qty_selected+cart_items>qty_allowed,swatch_selected,swatches);
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
