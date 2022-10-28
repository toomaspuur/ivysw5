{extends file="parent:frontend/checkout/change_payment.tpl"}

{block name='frontend_checkout_payment_fieldset_input_label'}
    {if $payment_mean.name === 'ivy_payment'}
        <div class="method--label is--first">
            <label class="method--name is--strong" for="payment_mean{$payment_mean.id}">
                {if $ivyConfig.checkoutTitle == '0'}
                    {include file="string:{$payment_mean.additionaldescription}"}
                {elseif $ivyConfig.checkoutTitle == '1'}
                    {$payment_mean.description}
                {else}
                    {$ivyConfig.checkoutTitleCustom}
                {/if}
            </label>
        </div>
        <div class="method--label is--first">
            <label class="method--name is--strong" for="payment_mean{$payment_mean.id}">
                {if $ivyConfig.checkoutSubTitle == '0'}
                    {include file="string:{$payment_mean.additionaldescription}"}
                {elseif $ivyConfig.checkoutSubTitle == '1'}
                    {s namespace="frontend/ivi_payment/checkout" name="labelText"}Bezahle sicher und einfach per Bankkonto{/s}
                {else}
                    {$ivyConfig.checkoutSubTitleCustom}
                {/if}
            </label>
        </div>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}
{block name="frontend_checkout_payment_fieldset_description"}
    {if $payment_mean.name === 'ivy_payment'}
        {if $ivyConfig.checkoutBanner == '1'}
            {include file="frontend/ivy_payment_plugin/banner.tpl" iviBannerType='payment' addClass='method--label' iviPrice=$sBasket.AmountNet}
        {/if}
    {else}
        {$smarty.block.parent}
    {/if}
{/block}