//{block name="backend/order/view/detail/window"}
//{$smarty.block.parent}

Ext.define('IvyPayment.view.detail.Window', {
    override: 'Shopware.apps.Order.view.detail.Window',

    createTabPanel: function () {
        var me = this,
            result = me.callParent();
        result.add(me.createIvyTab());
        return result;
    },
    createIvyTab: function() {
        var me = this;
        me.ivyTab = Ext.create('IvyPayment.view.list.Tab', { parent: me, record: me.record, taxStore: me.taxStore });
        return me.ivyTab;
    }

});
//{/block}