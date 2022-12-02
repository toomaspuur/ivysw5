{extends file="parent:frontend/register/login.tpl"}
{block name='frontend_register_login_input_form_submit'}
    {$smarty.block.parent}
    {if $ivyBasket && $ivyBasket.AmountNet}
        {include file="frontend/ivy_payment_plugin/button.tpl" iviPrice=$ivyBasket.AmountNet}
    {/if}
{/block}