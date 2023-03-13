//{block name="backend/log/model/system_log"}
//{$smarty.block.parent}
Ext.define('Ivy.apps.Log.model.IvyLog', {

    /**
     * Extends the standard ExtJS 4
     * @string
     */
    extend: 'Ext.data.Model',

    fields: [
        { name: 'id', type: 'int' },
        { name: 'date', type: 'date' },
        { name: 'level', type: 'string' },
        { name: 'message', type: 'string' },
        { name: 'context', type: 'string' },
    ],

    /**
     * Configure the data communication
     * @object
     */
    proxy: {
        type: 'ajax',
        api: {
            read: '{url controller="IvyLog" action="getLogList"}'
        },
        /**
         * Configure the data reader
         * @object
         */
        reader: {
            type: 'json',
            root: 'data',
            totalProperty: 'count'
        }
    }
});
//{/block}