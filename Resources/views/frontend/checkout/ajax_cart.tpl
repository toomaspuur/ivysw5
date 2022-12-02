{extends file="parent:frontend/checkout/ajax_cart.tpl"}

{block name='frontend_checkout_ajax_cart_open_basket'}
    {include file="frontend/ivy_payment_plugin/banner.tpl" iviPrice=$sBasket.AmountNet}
    {$smarty.block.parent}
{/block}