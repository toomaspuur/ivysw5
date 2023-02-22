{extends file="parent:frontend/checkout/ajax_cart.tpl"}

{block name='frontend_checkout_ajax_cart_open_basket'}
    {$smarty.block.parent}
    {if $darkThemeOffCanva}
        {$theme = 'dark'}
    {else}
        {$theme = 'light'}
    {/if}
    {include file="frontend/ivy_payment_plugin/button.tpl" iviPrice=$sBasket.AmountNet theme=$theme}
{/block}