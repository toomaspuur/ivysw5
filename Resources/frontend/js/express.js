$.plugin('ivyExpress', {
    init: function() {
        var scriptSrc = this.$el.find("script")[0].src;
        this.loadScript(scriptSrc, this.docLoaded);
        this.$el.on('click', this.createSession.bind(this));
        this.referenceId = null;
    },
    createSession() {
        $('.close--off-canvas').trigger('click');
        $.overlay.open()
        if (this.$el.data('addToIvycart')) {
            const form = document.querySelector('[name="sAddToBasket"]');
            if (form) {
                fetch(form.action, {
                    method: form.method,
                    body: new FormData(form)
                }).then(function(response) {
                    if (response.ok) {
                        this.openIviPopup();
                    } else {
                        $.overlay.close();
                    }
                }.bind(this));
            }
        } else {
            this.openIviPopup();
        }
    },
    refreshShopwareSession() {
        let me = this;
        $.ajax({
            type: 'GET',
            url: me.$el.data('refresh') + '?reference=' + me.referenceId,
            success : function(response) {
                setTimeout(function() {
                    me.refreshShopwareSession();
                }, 1000);
            }
        });
    },
    openIviPopup() {
        let me = this;
        $.ajax({
            type: 'GET',
            url: this.$el.data('action'),
            success : function(response) {
                $.overlay.close();
                if (response.redirectUrl) {
                    if (typeof startIvyCheckout === 'function') {
                        startIvyCheckout(response.redirectUrl, 'popup');
                        me.referenceId = response.referenceId;
                        me.refreshShopwareSession();
                    } else {
                        console.error('startIvyCheckout is not defined');
                    }
                } else {
                    console.error('cannot create ivy session');
                    location.reload();
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

StateManager.addPlugin('.ivy--express-checkout-btn','ivyExpress');
