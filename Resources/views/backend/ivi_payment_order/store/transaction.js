//{namespace name="backend/ivi_payment_order/order"}

Ext.define('IvyPayment.store.Transaction',{
    extend: 'Ext.data.Store',
    autoLoad: false,
    remoteSort: true,
    remoteFilter: true,
    pageSize: 20,
    model: 'IvyPayment.model.Transaction',
    proxy: {
        type: 'ajax',
        api: {
            read: '{url controller="IvyPayment" action="getData"}'
        },
        reader: {
            type: 'json',
            root: 'data'
        }
    },
});