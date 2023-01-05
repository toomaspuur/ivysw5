$.subscribe('plugin/swCollapseCart/onMenuOpen', function() {
    setTimeout(function() {
        StateManager.addPlugin('.ivy--express-checkout-btn','ivyExpress');
    }, 500);
});
$.subscribe('plugin/swCollapseCart/onLoadCartFinished', function() {
    StateManager.addPlugin('.ivy--express-checkout-btn','ivyExpress');
});
$.subscribe('plugin/swShippingPayment/onInputChanged', function() {
    StateManager.addPlugin('.ivy-banner','ivyBanner');
});

$.subscribe('plugin/swAjaxVariant/onRequestData', function() {
    StateManager.addPlugin('.ivy--express-checkout-btn','ivyExpress');
    StateManager.addPlugin('.ivy-banner','ivyBanner');
});