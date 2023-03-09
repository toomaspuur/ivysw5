//{block name="backend/log/store/log_files"}
//{$smarty.block.parent}
Ext.define('Ivy.apps.Log.store.IvyFiles', {

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
    model: 'Ivy.apps.Log.model.IvyFiles',

    pageSize: 10
});
//{/block}