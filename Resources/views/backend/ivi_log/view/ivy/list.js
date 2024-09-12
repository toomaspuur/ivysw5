//{namespace name="backend/log/ivy"}

//{block name="backend/log/view/system/list"}
//{$smarty.block.parent}
Ext.define('Ivy.apps.Log.view.ivy.List', {
    extend: 'Shopware.apps.Log.view.system.List',
    alias: 'widget.log-ivy-list',
    store: 'Ivy.apps.Log.store.IvyLogs',

    getColumns: function () {
        var me = this;

        var columns = [{
            xtype: 'datecolumn',
            header: '{s name="model/field/date"}Date{/s}',
            dataIndex: 'date',
            width: 150,
            format: Ext.Date.defaultFormat + ' H:i:s'
        }, {
            header: '{s name="model/field/level"}Level{/s}',
            dataIndex: 'level',
            width: 100,
            sortable: false
        }, {
            header: '{s name="model/field/message"}Message{/s}',
            dataIndex: 'message',
            flex: 1,
            sortable: false,
            allowHtml: true,
            renderer: function (value) {
                return Ext.String.htmlEncode(value);
            }
        }, {
            xtype: 'actioncolumn',
            width: 30,
            items: [{
                iconCls: 'sprite-magnifier',
                action: 'openLog',
                tooltip: '{s name="grid/action/tooltip/open_log"}Open log{/s}',
                handler: function (view, rowIndex, colIndex, item, event, record) {
                    me.fireEvent('openLog', record);
                }
            }]
        }];

        return columns;
    },

    createLogFileCombo: function () {
        var me = this;
        me.ivyFilesStore = Ext.create('Ivy.apps.Log.store.IvyFiles');

        var combo = Ext.create('Shopware.form.field.PagingComboBox', {
            name: 'categoryId',
            pageSize: 15,
            labelWidth: 155,
            forceSelection: true,
            width: 400,
            fieldLabel: '{s name="toolbar/file"}File{/s}',
            store: me.ivyFilesStore,
            valueField: 'name',
            displayField: 'name',
            disableLoadingSelectedName: true
        });

        combo.store.on('load', function (store) {
            var record = store.findRecord('default', true);
            if (record) {
                combo.setValue(record.get('name'));
            }
        }, this, {
            single: true
        });

        combo.store.load();

        combo.on('select', function () {
            var value = combo.getValue();
            if (value) {
                me.downloadButton.enable();
                me.store.getProxy().extraParams = {
                    logFile: combo.getValue()
                };
                me.store.loadPage(1);
            } else {
                me.downloadButton.disable();
            }
        }, this);

        return combo;
    },
    createToolbar: function () {
        var me = this;

        me.downloadButton = Ext.create('Ext.Button', {
            iconCls: 'sprite-drive-download',
            text: '{s name="toolbar/download"}Download{/s}',
            action: 'download',
            disabled: true,
            handler: function () {
                var file = me.logFileCombo.getValue(),
                    link = "{url controller=IvyLog action=downloadLogFile}"
                        + "?logFile=" + encodeURIComponent(file);
                window.open(link, '_blank');
            }
        });

        me.logFileCombo = me.createLogFileCombo();

        return {
            xtype: 'toolbar',
            ui: 'shopware-ui',
            dock: 'top',
            border: false,
            items: [me.logFileCombo, me.downloadButton]
        };
    },

});
//{/block}