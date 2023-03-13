//{namespace name="backend/log/main"}

//{block name="backend/log/view/main/window"}
//{$smarty.block.parent}
Ext.define('IvyPayment.apps.Log.view.main.Window', {
    override: 'Shopware.apps.Log.view.main.Window',
    initComponent: function () {
        var me = this;
        me.ivySore = Ext.create('Ivy.apps.Log.store.IvyLogs');
        me.callParent(arguments);
        console.log(me.items);
        me.items.items[0].add(Ext.create('Ivy.apps.Log.view.ivy.List',{
            title: '{s name="tabs/ivy"}Ivy log{/s}',
            store: me.ivySore
        }));
    }

});
//{/block}