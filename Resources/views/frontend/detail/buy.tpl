{extends file="parent:frontend/detail/buy.tpl"}

{block name="frontend_detail_buy"}
    {$smarty.block.parent}
    <div class="ivy-banner-wrapper">
    {include file="frontend/ivy_payment_plugin/banner.tpl"
        addDivClass='buybox--form'
        addButtonClass='buybox--button'
        iviPrice=$sArticle.price
        ivyAddToCart=true}
    </div>
    <div style="clear: both;"></div>
{/block}