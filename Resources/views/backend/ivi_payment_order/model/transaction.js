//{namespace name="backend/ivi_payment_order/order"}

Ext.define('IvyPayment.model.Transaction', {
    extend: 'Shopware.data.Model',
    idProperty: 'id',
    fields: [
        { name: 'id', type: 'int' },
        { name: 'reference', type: 'string' },
        { name: 'appId', type: 'string' },
        { name: 'ivyOrderId', type: 'string' },
        { name: 'amount', type: 'float' },
        { name: 'status', type: 'string' },
        { name: 'refundable', type: 'float' },
        { name: 'cancelable', type: 'boolean' },
        { name: 'created', type: 'date' },
        { name: 'updated', type: 'date' },
    ],
});