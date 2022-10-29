{extends file="parent:frontend/checkout/confirm.tpl"}

{block name='frontend_checkout_confirm_left_payment_method'}
    {if $sUserData.additional.payment.name === 'ivy_payment'}
        {$smarty.block.parent}
        <p>
            {include file="frontend/ivy_payment_plugin/banner.tpl" iviBannerType='payment' iviPrice=$sBasket.AmountNet}
        </p>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}

{block name='frontend_checkout_confirm_form'}
    {if $sUserData.additional.payment.name === 'ivy_payment'}
        <div class="ivy-checkout-confirm-button"
             data-action="{url controller=IvyExpress action=start express='false'}"
             data-refresh="{url controller=IvyExpress action=refresh}"
        >
            {$smarty.block.parent}
            <script src="{$ivyButtonUrl}"></script>
        </div>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}