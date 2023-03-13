//{block name="backend/log/model/log_file"}
//{$smarty.block.parent}
Ext.define('Ivy.apps.Log.model.IvyFiles', {

    /**
     * Extends the standard ExtJS 4
     * @string
     */
    extend: 'Ext.data.Model',

    fields: [
        { name: 'name', type: 'string' },
        { name: 'date', type: 'date' },
        { name: 'channel', type: 'string' },
        { name: 'default', type: 'boolean' }
    ],

    /**
     * Configure the data communication
     * @object
     */
    proxy: {
        type: 'ajax',
        api: {
            read: '{url controller="IvyLog" action="getLogFileList"}'
        },
        /**
         * Configure the data reader
         * @object
         */
        reader: {
            type: 'json',
            root: 'data'
        }
    }
});
//{/block}
