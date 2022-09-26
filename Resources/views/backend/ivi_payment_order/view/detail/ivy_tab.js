//{namespace name="backend/ivi_payment_order/order"}
// This tab will be shown in the order details module

Ext.define('IvyPayment.view.list.Tab', {
    extend: 'Ext.container.Container',
    padding: 10,
    layout:'anchor',
    /**
     * Contains all snippets for the view component
     * @object
     */
    snippets: {
        title: '{s name="IvyTabTitle"}Ivy{/s}',
        gridTitle: '{s name="IvyGridTitle"}Ivy Transaktionen{/s}',
        appId: '{s name="IvyAppId"}App Id{/s}',
        ivySessionId: '{s name="IvySessionId"}Ivy Session Id{/s}',
        ivyOrderId: '{s name="IvyOrderId"}Ivy Order Id{/s}',
        amount: '{s name="Amount"}Betrag{/s}',
        reference: '{s name="IvyReference"}Reference ID{/s}',
        status: '{s name="IvyStatus"}Status{/s}',
        created: '{s name="IvyCreated"}erstellt{/s}',
        updated: '{s name="IvyUpdated"}aktualisiert{/s}',
        errorlogin: '{s name="NoLogin"}Keine gültige Backend Session, bitte Login prüfen{/s}',
        errortransaction: '{s name="NoTransactionFound"}Keine gültige Transaktion gefunden{/s}',
        error: '{s name="Error"}Fehler bei Verarbeitung{/s}',
        refundsend: '{s name="IvyRefundSend"}Betrag erstatten{/s}',
        refundsuccess: '{s name="IvyRefundSuccessMessage"}Die Erstattung wurde erfolgreich übertragen{/s}',
        refundReset: '{s name="IvyRefundReset"}Zurücksetzen{/s}',
        transactionrefund: '{s name="IvyTransactionRefund"}Rückbuchung für Transaktion{/s} ',
    },
    initComponent: function(params) {
        var me = this;
        me.title = me.snippets.title;
        me.callParent(arguments);
        me.grid = Ext.create('IvyPayment.view.list.Grid', { parent: me, order: me.record.data, snippets: me.snippets });
        me.add(me.grid);
    },
});
