//{namespace name="backend/ivi_payment_order/order"}

Ext.define('IvyPayment.view.list.Grid', {
    extend: 'Shopware.apps.Order.view.list.Document',
    anchor: '100% 50%',
    cancelForm: null,
    refundForm: null,
    getColumns: function () {
        var me = this;
        return [
            {
                header: '',
                dataIndex: 'amount',
                renderer: function(val) {
                    return val > 0 ? 'payment' : 'refund'
                },
                flex: 2,
            },
            {
                header: me.snippets.reference,
                dataIndex: 'reference',
                flex: 2,
            },
            {
                header: me.snippets.appId,
                dataIndex: 'appId',
                flex: 2,
            },
            {
                header: me.snippets.ivyOrderId,
                dataIndex: 'ivyOrderId',
                flex: 2
            },
            {
                header: me.snippets.amount,
                dataIndex: 'amount',
                flex: 1,
                renderer: function (value) {
                    return Ext.util.Format.currency(value);
                }
            },
            {
                header: me.snippets.status,
                dataIndex: 'status',
                flex: 2
            },
            {
                header: me.snippets.created,
                dataIndex: 'created',
                type: 'date',
                renderer: Ext.util.Format.dateRenderer('d.m.Y H:i:s'),
                flex: 2,
            },
            {
                header: me.snippets.updated,
                dataIndex: 'updated',
                type: 'date',
                renderer: Ext.util.Format.dateRenderer('d.m.Y H:i:s'),
                flex: 2,
            },
            Ext.create('Ext.grid.column.Action', {
                width: 80,
                items: [
                    me.createRefundColumn(),
                ]
            })

        ];
    },

    createRefundColumn: function () {
        var me = this;
        return {
            tooltip: me.snippets.refundsend,
            iconCls: 'sprite-arrow-return-090-left',
            handler: function (view, rowIndex, colIndex, item, opts, record) {
                me.openRefundForm(record);
            },
            getClass: function (html, metadata, record) {
                if (record.get('refundable') <= 0) {
                    return 'x-hide-display';
                }
            }
        };
    },

    openRefundForm: function (record) {
        var me = this;
        me.closeOpenForms();
        me.refundForm = Ext.create('IvyPayment.form.Panel.Refund',{
            record: record, snippets: me.snippets, grid: me
        });
        me.parent.add(me.refundForm);
    },

    closeOpenForms: function () {
        var me = this;
        if (me.refundForm !== null) {
            Ext.destroy(me.refundForm);
        }
    },

    initComponent : function(){
        var me = this;
        me.title = me.snippets.gridTitle;
        me.store =  Ext.create('IvyPayment.store.Transaction');
        me.parent.setDisabled(true);
        me.store.filter("orderId",  me.order.id);
        me.store.addListener('load', function () {
            if (me.store.getTotalCount()) {
                me.parent.setDisabled(false);
            }
        });
        me.bbar = {
            xtype: 'pagingtoolbar',
            displayInfo: true,
            store: me.store,
        };
        me.callParent(arguments);
    },
});