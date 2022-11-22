{extends file="parent:frontend/checkout/cart.tpl"}

{block name="frontend_checkout_actions_confirm_bottom"}
    {$smarty.block.parent}
    <div class="ivy-banner-wrapper">
        {include file="frontend/ivy_payment_plugin/banner.tpl" iviPrice=$sBasket.AmountNet}
    </div>
    <div style="clear: both;"></div>
{/block}