$.plugin('ivyConfirm', {
    init: function() {
        var me = this;
        var scriptSrc = this.$el.find("script")[0].src;
        this.loadScript(scriptSrc, this.docLoaded);
        $('#confirm--form').submit(function(e){
            me.openIviPopup();
            e.preventDefault();
        });
    },
    refreshShopwareSession() {
        let me = this;
        $.ajax({
            type: 'GET',
            url: me.$el.data('refresh'),
            success : function(response) {
                setTimeout(function() {
                    me.refreshShopwareSession();
                }, 1000);
            }
        });
    },
    openIviPopup() {
        console.log('openIviPopup send ', this.$el.data('action'));
        let me = this;
        $.ajax({
            type: 'GET',
            url: this.$el.data('action'),
            success : function(response) {
                $.overlay.close();
                if (response.redirectUrl) {
                    if (typeof startIvyCheckout === 'function') {
                        startIvyCheckout(response.redirectUrl, 'popup');
                   //     me.refreshShopwareSession();
                    } else {
                        console.error('startIvyCheckout is not defined');
                    }
                } else {
                    console.error('cannot create ivy session');
                    var url = window.location.href;
                    if (url.indexOf('?') > -1){
                        url += '&ivyError=createSessionError'
                    }else{
                        url += '?ivyError=createSessionError'
                    }
                    window.location.href = url;
                }
            }
        });
    },
    loadScript(url, callback) {
        var div = this.$el[0];
        var script = document.createElement('script');
        script.async = false;
        script.type = 'text/javascript';
        script.src = url;

        // Then bind the event to the callback function.
        // There are several events for cross browser compatibility.
        script.onreadystatechange = callback;
        script.onload = callback;

        // Fire the loading
        div.appendChild(script);
    },
    docLoaded() {
        window.document.dispatchEvent(new Event("DOMContentLoaded", {
            bubbles: true,
            cancelable: true
        }));
    }
})

StateManager.addPlugin('.ivy-checkout-confirm-button','ivyConfirm');
