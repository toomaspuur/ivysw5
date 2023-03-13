//{block name="backend/log/store/system_logs"}
//{$smarty.block.parent}
Ext.define('Ivy.apps.Log.store.IvyLogs', {
    /**
     * Extend for the standard ExtJS 4
     * @string
     */
    extend: 'Ext.data.Store',
    /**
     * Auto load the store after the component
     * is initialized
     * @boolean
     */
    autoLoad: false,

    /**
     * Define the used model for this store
     * @string
     */
    model: 'Ivy.apps.Log.model.IvyLog',
    remoteSort: true,
    remoteFilter: true,
    pageSize: 25
});
//{/block}