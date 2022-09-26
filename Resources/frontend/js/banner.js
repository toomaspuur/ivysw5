$.plugin('ivyBanner', {
    init: function() {
        const me = this;
        if (typeof window.initIvy !== 'function') {
            const scriptEle = document.createElement("script");
            scriptEle.setAttribute("src", me.$el.attr('data-banner-url'));
            document.body.appendChild(scriptEle);
            scriptEle.addEventListener("load", () => {
                window.initIvy();
            });
        } else {
            window.initIvy();
        }

        me.$el.parent().find('#sQuantity').on('change', function() {
            let quantity = $(this).val();
            let price = me.$el.attr('data-unitprice');
            me.$el.attr('data-value', price * quantity);
            me.$el.html('');
            let newBanner = me.$el.clone();
            me.$el.hide();
            newBanner.insertAfter(me.$el);
            me.$el.remove();
            StateManager.addPlugin('.ivy-banner','ivyBanner');
        });
    },
})

StateManager.addPlugin('.ivy-banner','ivyBanner');
