//{namespace name="backend/ivi_payment_order/order"}

Ext.define('IvyPayment.form.Panel.Refund', {
    extend: 'Ext.form.Panel',
    bodyPadding: 5,
    style: 'margin-top: 10px;',
    cls: 'shopware-form',

    url: '{url controller=IvyPayment action=refund}',

    layout: 'anchor',
    defaults: {
        anchor: '100%'
    },
    initComponent: function () {
        var me = this;
        me.title = me.snippets.transactionrefund + ' ' + me.record.get('appId');
        me.items =[
            {
                fieldLabel: me.snippets.amount,
                name: 'amount',
                allowBlank: false,
                xtype: 'numberfield',
                value: me.record.get('refundable'),
                allowNegative: false,
                maxValue: me.record.get('refundable')
            },
            {
                xtype: 'hidden',
                name: 'id',
                value: me.record.get('id')
            }
        ];
        me.loadMask = new Ext.LoadMask(this, {
            msg: me.title
        });
        me.buttons =[
            {
                text: me.snippets.refundReset,
                cls: 'secondary',
                handler: function () {
                    this.up('form').getForm().reset();
                }
            }, {
                text: me.snippets.refundsend,
                cls: 'primary',
                formBind: true,
                disabled: true,
                handler: function () {
                    var form = this.up('form').getForm();
                    if (form.isValid()) {
                        form.submit({
                            success: function (form, action) {
                                Shopware.Notification.createGrowlMessage(me.snippets.success, me.snippets.refundsuccess, '');
                                me.grid.store.load();
                                Ext.destroy(me);
                            },
                            failure: function (form, action) {
                                var errorcode = action.result.data.errorcode;
                                var errormessage = action.result.data.errormessage;
                                var message = '';

                                if (errorcode === 1) {
                                    message = me.snippets.errorlogin;
                                } else if (errorcode === 2) {
                                    message = me.snippets.errortransaction;
                                } else {
                                    message = me.snippets.error + errormessage;
                                }
                                me.loadMask.hide();
                                Ext.Msg.alert(me.snippets.error, message);
                            }
                        });
                        me.loadMask.show();
                    }
                }
            }
        ];
        me.callParent(arguments);
    },
});
