{if $ivyEnabled}
<div class="ivy--express-checkout-btn"
     data-action="{url controller=IvyExpress action=start expres='true'}"
     data-refresh="{url controller=IvyExpress action=refresh}"
     {if $ivyAddToCart}data-add-to-ivycart="true"{/if}>
    <div
        class="ivy-checkout-button"
        style="visibility: hidden;"
        data-cart-value="{$iviPrice|replace:',':'.'}"
        data-shop-category="{$ivyMcc}"
        data-locale="{$ivyLocale}"
        data-currency-code="{$currency}"
    ></div>
    <script src="{$ivyButtonUrl}"></script>
</div>
{/if}