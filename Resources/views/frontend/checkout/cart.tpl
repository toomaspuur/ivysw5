{extends file="parent:frontend/checkout/cart.tpl"}

{block name="frontend_checkout_actions_confirm_bottom"}
    {$smarty.block.parent}
    {if $darkThemeCart}
        {$theme = 'dark'}
    {else}
        {$theme = 'light'}
    {/if}
    {include file="frontend/ivy_payment_plugin/button.tpl" iviPrice=$sBasket.AmountNet theme=$theme}
{/block}